<?php
declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Entity\Room;
use App\Entity\User;
use App\Service\ICalSyncService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BookingControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        // Ensure no kernel leftover exists
        self::ensureKernelShutdown();

        // Create client
        $this->client = static::createClient();

        /** @var EntityManagerInterface $entityManager */
        $container = $this->client->getContainer();
        $this->entityManager = $container->get('doctrine')->getManager();

        // Set up clean database schema
        $this->setUpDatabase();

        // Mock ICalSyncService to avoid network calls
        $this->mockICalSyncService();

        // Create and login test user
        $this->createAndLoginTestUser();
    }

    public function testSuccessfulBookingCreationWithValidData(): void
    {
        $room = $this->createTestRoom();

        $this->createBooking($room, '2030-06-05', '2030-06-10');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
        $this->assertArrayHasKey('booking_id', $response);
        $this->assertArrayHasKey('booking_hash', $response);
        $this->assertEquals(500.0, $response['total_price']);
    }

    public function testBookingConflictDetectionWithOverlappingDates(): void
    {
        $room = $this->createTestRoom();

        $this->createBooking($room, '2030-06-05', '2030-06-10');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        $this->createBooking($room, '2030-06-08', '2030-06-12');

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('INTERNAL_CONFLICT', $response['error']);
        $this->assertArrayHasKey('details', $response);
        $this->assertEquals(1, $response['details']['conflict_count']);
    }

    public function testBookingAdjacentDatesDoNotConflict(): void
    {
        $room = $this->createTestRoom();

        $this->createBooking($room, '2030-06-05', '2030-06-10');
        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Check-out day of the first booking is the check-in day of the second (no overlap)
        $this->createBooking($room, '2030-06-10', '2030-06-14');

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($response['success']);
    }

    public function testValidationErrors(): void
    {
        $this->client->request('POST', '/api/bookings', [], [],
            [
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode([
                'room_id' => 'invalid-uuid',
                'start_date' => '2025-06-05',
                'end_date' => '2025-06-01', // End before start!
                'guest_name' => '',
                'guest_email' => 'invalid-email',
                'guest_phone' => '',
            ])
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $response = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($response['success']);
        $this->assertEquals('VALIDATION_ERROR', $response['error']);
        $this->assertArrayHasKey('errors', $response);
    }

    protected function tearDown(): void
    {
        // Close client & shutdown kernel between tests
        $this->client = null;
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function setUpDatabase(): void
    {
        // Get all metadata
        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();

        if (!empty($metadatas)) {
            $schemaTool = new SchemaTool($this->entityManager);
            // Clean database: Drop + Create
            $schemaTool->dropSchema($metadatas);
            $schemaTool->createSchema($metadatas);
        }
    }

    private function mockICalSyncService(): void
    {
        $icalMock = $this->createMock(ICalSyncService::class);
        $icalMock->method('checkAvailability')->willReturn(true);

        static::getContainer()->set(ICalSyncService::class, $icalMock);
    }

    private function createAndLoginTestUser(): void
    {
        /** @var EntityManagerInterface $container */
        $container = $this->client->getContainer();
        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail('testuser@example.com');
        $hashedPassword = $passwordHasher->hashPassword($user, 'password123');
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Login the user
        $this->client->request('POST', '/api/login', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'testuser@example.com',
                'password' => 'password123'
            ])
        );

        // Manually inject the cookie to ensure it's set
        $response = $this->client->getResponse();
        $setCookieHeaders = $response->headers->all('Set-Cookie');

        if (!empty($setCookieHeaders)) {
            foreach ($setCookieHeaders as $header) {
                if (strpos($header, 'BEARER=') === 0) {
                    $parts = explode(';', $header);
                    $cookieParts = explode('=', $parts[0], 2);
                    if (count($cookieParts) === 2 && $cookieParts[0] === 'BEARER') {
                        $domain = $this->client->getRequest()->getHost();
                        $path = '/';

                        $cookie = new \Symfony\Component\BrowserKit\Cookie(
                            $cookieParts[0],
                            $cookieParts[1],
                            null,
                            $path,
                            $domain,
                            false,
                            true,
                            false,
                            'lax'
                        );

                        $this->client->getCookieJar()->set($cookie);
                        break;
                    }
                }
            }
        }
    }

    // Helper methods
    private function createTestRoom(): Room
    {
        $room = new Room();
        $room->setName('Test Room');
        $room->setSlug('test-room-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice("100.00");
        $room->setIsActive(true);
        // Don't set iCal URLs to avoid the checkAvailability call

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        return $room;
    }

    private function createBooking(Room $room, string $startDate, string $endDate): void
    {
        $this->client->request('POST', '/api/bookings', [], [],
            [
                'CONTENT_TYPE' => 'application/json'
            ],
            json_encode([
                'room_id' => $room->getId()->toRfc4122(),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'guest_name' => 'Test Guest',
                'guest_email' => 'test@example.com',
                'guest_phone' => '+49123456789',
            ])
        );
    }
}