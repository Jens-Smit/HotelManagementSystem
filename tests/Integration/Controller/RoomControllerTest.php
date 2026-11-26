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

class RoomControllerTest extends WebTestCase
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

        $icalMock = $this->createMock(ICalSyncService::class);
        $icalMock->method('checkAvailability')->willReturn(true);
        static::getContainer()->set(ICalSyncService::class, $icalMock);

        $this->createAndLoginTestUser();
    }

    public function testListRoomsReturnsPaginatedData(): void
    {
        $this->createTestRoom('Zimmer Nord');
        $this->createTestRoom('Zimmer Süd');

        $this->client->request('GET', '/api/rooms?page=1&limit=10');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(2, $data['data']);
        $this->assertSame(1, $data['meta']['current_page']);
        $this->assertSame(10, $data['meta']['items_per_page']);
        $this->assertSame(2, $data['meta']['total_items']);
    }

    public function testListRoomsFiltersInactiveRooms(): void
    {
        $room = $this->createTestRoom('Aktives Zimmer');
        $inactive = new Room();
        $inactive->setName('Inaktives Zimmer');
        $inactive->setSlug('inaktiv-' . uniqid());
        $inactive->setCapacity(2);
        $inactive->setBasePrice('80.00');
        $inactive->setIsActive(false);
        $this->entityManager->persist($inactive);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/rooms?is_active=0');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertCount(1, $data['data']);
        $this->assertSame('Inaktives Zimmer', $data['data'][0]['name']);
    }

    public function testShowRoomReturnsRoomData(): void
    {
        $room = $this->createTestRoom('Zimmer Bergblick');

        $this->client->request('GET', '/api/rooms/' . $room->getId()->toRfc4122());

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('Zimmer Bergblick', $data['name']);
    }

    public function testShowRoomReturns404ForUnknownId(): void
    {
        $this->client->request('GET', '/api/rooms/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testListAvailableRoomsExcludesBookedRooms(): void
    {
        $freeRoom = $this->createTestRoom('Freies Zimmer');
        $bookedRoom = $this->createTestRoom('Belegtes Zimmer');

        $booking = new Booking();
        $booking->setRoom($bookedRoom);
        $booking->setStartDate(new \DateTime('2030-07-01'));
        $booking->setEndDate(new \DateTime('2030-07-05'));
        $booking->setGuestName('Gast');
        $booking->setGuestEmail('gast@example.com');
        $booking->setTotalPrice('400.00');
        $booking->setSource('website');
        $booking->setStatus('confirmed');
        $booking->setBookingHash(bin2hex(random_bytes(16)));
        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/rooms/available?checkIn=2030-07-02&checkOut=2030-07-04&capacity=1');

        $this->assertResponseStatusCodeSame(Response::HTTP_OK);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $names = array_column($data['rooms'], 'name');
        $this->assertContains('Freies Zimmer', $names);
        $this->assertNotContains('Belegtes Zimmer', $names);
    }

    public function testListAvailableRoomsRequiresDates(): void
    {
        $this->client->request('GET', '/api/rooms/available');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertSame('MISSING_DATES', $data['error']);
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function createTestRoom(string $name): Room
    {
        $room = new Room();
        $room->setName($name);
        $room->setSlug(strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name)) . '-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice("100.00");
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
