<?php
declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Booking;
use App\Entity\Room;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Kompletter E2E-Nachweis der Kernfunktion:
 * Cron-Import einer externen Portalbuchung -> Verfügbarkeits-API meldet belegt ->
 * überlappende Direktbuchung wird abgelehnt -> angrenzende Buchung geht durch.
 *
 * Der iCal-Feed wird über den im Test-Env konfigurierten
 * http_client.mock_response_factory deterministisch geliefert
 * (tests/Fixtures/ical/booking_com_valid.ics: belegt 2025-03-20 bis 2025-03-23).
 */
class ICalEndToEndTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($this->entityManager);
        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadatas);
        $schemaTool->createSchema($metadatas);

        $this->createAndLoginTestUser();
    }

    public function testExternalImportBlocksOverlappingDirectBooking(): void
    {
        // 1. Zimmer mit externem Booking.com-Kalender anlegen
        $room = new Room();
        $room->setName('E2E Zimmer');
        $room->setSlug('e2e-zimmer-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice("100.00");
        $room->setIsActive(true);
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');
        $this->entityManager->persist($room);
        $this->entityManager->flush();

        // 2. Cron-Sync importiert die externe Buchung (2025-03-20 bis 2025-03-23)
        $application = new Application($this->client->getKernel());
        $tester = new CommandTester($application->find('app:sync-calendars'));
        $tester->execute(['--room-id' => $room->getId()->toRfc4122(), '--force' => true]);
        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        /** @var Booking $imported */
        $imported = $this->entityManager->getRepository(Booking::class)->findOneBy([
            'externalId' => 'test-booking-1@booking.com',
        ]);
        $this->assertNotNull($imported, 'Die externe Buchung muss importiert worden sein');

        // 3. Verfügbarkeits-API meldet den Zeitraum als belegt
        $this->client->request('GET', sprintf(
            '/api/rooms/%s/availability?start_date=2025-03-21&end_date=2025-03-22&real_time=1',
            $room->getId()->toRfc4122()
        ));
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $availability = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($availability['available'], 'Der importierte Zeitraum muss als belegt gelten');
        $this->assertSame('internal_booking', $availability['conflict_reason']);

        // 4. Überlappende Direktbuchung wird abgelehnt -> keine Doppelbuchung
        $this->client->request('POST', '/api/bookings', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'room_id' => $room->getId()->toRfc4122(),
            'start_date' => '2025-03-22',
            'end_date' => '2025-03-26',
            'guest_name' => 'Direktgast',
            'guest_email' => 'direkt@example.com',
            'guest_phone' => '+49123456789',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $conflict = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($conflict['success']);
        $this->assertSame('INTERNAL_CONFLICT', $conflict['error']);

        // 5. Angrenzende Buchung (Check-out-Tag der Importbuchung) ist erlaubt
        $this->client->request('POST', '/api/bookings', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'room_id' => $room->getId()->toRfc4122(),
            'start_date' => '2025-03-23',
            'end_date' => '2025-03-27',
            'guest_name' => 'Folgegast',
            'guest_email' => 'folge@example.com',
            'guest_phone' => '+49123456789',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $success = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($success['success']);

        // Am Ende existiert genau eine Direktbuchung plus eine Importbuchung
        $directBookings = $this->entityManager->getRepository(Booking::class)
            ->count(['source' => 'website']);
        $this->assertSame(1, $directBookings);
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function createAndLoginTestUser(): void
    {
        $passwordHasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('e2e@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'e2e@example.com',
            'password' => 'password123',
        ]));

        foreach ($this->client->getResponse()->headers->all('Set-Cookie') as $header) {
            if (str_starts_with($header, 'BEARER=')) {
                $parts = explode(';', $header);
                [$name, $value] = explode('=', $parts[0], 2);
                $this->client->getCookieJar()->set(new Cookie(
                    $name,
                    $value,
                    null,
                    '/',
                    'localhost',
                    false,
                    true,
                    false,
                    'lax'
                ));
                break;
            }
        }
    }
}
