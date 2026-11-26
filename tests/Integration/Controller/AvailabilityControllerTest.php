<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Booking;
use App\Entity\Room;
use App\Entity\User;
use App\Service\ICalSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AvailabilityControllerTest extends WebTestCase
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

    public function testAvailabilityForFreePeriodReturnsPricing(): void
    {
        $room = $this->createTestRoom('Zimmer Seeblick', '100.00');

        $this->client->request('GET', sprintf(
            '/api/rooms/%s/availability?start_date=2030-08-01&end_date=2030-08-05',
            $room->getId()->toRfc4122()
        ));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['available']);
        $this->assertSame('100', (string) $data['price_per_night']);
        $this->assertEquals(400.0, $data['total_price']);
        $this->assertSame(4, (int) $data['nights']);
        $this->assertContains('internal_database', $data['checks_performed']);
    }

    public function testAvailabilityDetectsInternalBookingConflict(): void
    {
        $room = $this->createTestRoom('Zimmer Seeblick', '100.00');

        $booking = new Booking();
        $booking->setRoom($room);
        $booking->setStartDate(new \DateTime('2030-08-02'));
        $booking->setEndDate(new \DateTime('2030-08-06'));
        $booking->setGuestName('Gast');
        $booking->setGuestEmail('gast@example.com');
        $booking->setTotalPrice('400.00');
        $booking->setSource('website');
        $booking->setStatus('confirmed');
        $booking->setBookingHash(bin2hex(random_bytes(16)));
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf(
            '/api/rooms/%s/availability?start_date=2030-08-04&end_date=2030-08-08',
            $room->getId()->toRfc4122()
        ));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['available']);
        $this->assertSame('internal_booking', $data['conflict_reason']);
        $this->assertArrayNotHasKey('total_price', $data, 'Bei Konflikt darf kein Preis berechnet werden');
    }

    public function testAvailabilityDetectsExternalIcalConflict(): void
    {
        $room = $this->createTestRoom('Zimmer mit Portal', '120.00');
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');
        $this->entityManager->flush();

        // Die Fixture (booking_com_valid.ics) blockiert 2025-03-20 bis 2025-03-23
        $this->client->request('GET', sprintf(
            '/api/rooms/%s/availability?start_date=2025-03-21&end_date=2025-03-22&real_time=1',
            $room->getId()->toRfc4122()
        ));

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['available']);
        $this->assertSame('booking_com_reservation', $data['conflict_reason']);
    }

    public function testAvailabilityWithCancelledBookingIsAvailable(): void
    {
        $room = $this->createTestRoom('Zimmer Seeblick', '100.00');

        $booking = new Booking();
        $booking->setRoom($room);
        $booking->setStartDate(new \DateTime('2030-08-01'));
        $booking->setEndDate(new \DateTime('2030-08-05'));
        $booking->setGuestName('Gast');
        $booking->setGuestEmail('gast@example.com');
        $booking->setTotalPrice('400.00');
        $booking->setSource('website');
        $booking->setStatus('cancelled');
        $booking->setBookingHash(bin2hex(random_bytes(16)));
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->client->request('GET', sprintf(
            '/api/rooms/%s/availability?start_date=2030-08-01&end_date=2030-08-05',
            $room->getId()->toRfc4122()
        ));

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['available'], 'Stornierte Buchungen dürfen keine Verfügbarkeit blockieren');
    }

    public function testAvailabilityRequiresDates(): void
    {
        $room = $this->createTestRoom('Zimmer Seeblick', '100.00');

        $this->client->request('GET', '/api/rooms/' . $room->getId()->toRfc4122() . '/availability');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('MISSING_DATES', $data['error']);
    }

    public function testAvailabilityRejectsInvalidDateRange(): void
    {
        $room = $this->createTestRoom('Zimmer Seeblick', '100.00');

        $this->client->request('GET', sprintf(
            '/api/rooms/%s/availability?start_date=2030-08-05&end_date=2030-08-01',
            $room->getId()->toRfc4122()
        ));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('INVALID_DATE_RANGE', $data['error']);
    }

    public function testAvailabilityReturns404ForUnknownRoom(): void
    {
        $this->client->request('GET', '/api/rooms/00000000-0000-0000-0000-000000000000/availability?start_date=2030-08-01&end_date=2030-08-05');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function createTestRoom(string $name, string $basePrice): Room
    {
        $room = new Room();
        $room->setName($name);
        $room->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice($basePrice);
        $room->setIsActive(true);

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        return $room;
    }

    private function createAndLoginTestUser(): void
    {
        $passwordHasher = $this->client->getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('testuser@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'testuser@example.com',
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
