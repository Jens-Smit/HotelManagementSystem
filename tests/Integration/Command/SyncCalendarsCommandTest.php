<?php
declare(strict_types=1);

namespace App\Tests\Integration\Command;

use App\Entity\Booking;
use App\Entity\Room;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class SyncCalendarsCommandTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($this->entityManager);
        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadatas);
        $schemaTool->createSchema($metadatas);
    }

    public function testSyncImportsExternalBookingIntoDatabase(): void
    {
        $room = $this->createTestRoom();

        $tester = $this->createCommandTester();
        $tester->execute(['--room-id' => $room->getId()->toRfc4122(), '--force' => true]);

        $this->assertSame(0, $tester->getStatusCode(), $tester->getDisplay());

        /** @var Booking $imported */
        $imported = $this->entityManager->getRepository(Booking::class)->findOneBy([
            'externalId' => 'test-booking-1@booking.com',
            'source' => 'booking_com',
        ]);

        $this->assertNotNull($imported, 'Die externe Buchung muss importiert worden sein');
        $this->assertSame('Test Guest', $imported->getGuestName());
        $this->assertSame('confirmed', $imported->getStatus());
        $this->assertSame('2025-03-20', $imported->getStartDate()->format('Y-m-d'));
        $this->assertSame('2025-03-23', $imported->getEndDate()->format('Y-m-d'));
        $this->assertNotNull($room->getLastSyncBooking(), 'Sync-Zeitstempel muss gesetzt sein');
    }

    public function testSyncIsIdempotentForAlreadyImportedBookings(): void
    {
        $room = $this->createTestRoom();

        $tester = $this->createCommandTester();
        $tester->execute(['--room-id' => $room->getId()->toRfc4122(), '--force' => true]);
        $this->assertSame(0, $tester->getStatusCode());

        $tester = $this->createCommandTester();
        $tester->execute(['--room-id' => $room->getId()->toRfc4122(), '--force' => true]);
        $this->assertSame(0, $tester->getStatusCode());

        $count = $this->entityManager->getRepository(Booking::class)
            ->count(['externalId' => 'test-booking-1@booking.com', 'source' => 'booking_com']);

        $this->assertSame(1, $count, 'Der zweite Sync darf keine Dublette anlegen');
    }

    public function testSyncWithBrokenFeedImportsNothing(): void
    {
        $room = $this->createTestRoom();
        $room->setIcalUrlBooking('https://error.example.com/feed.ics');
        $this->entityManager->flush();

        $tester = $this->createCommandTester();
        $tester->execute(['--room-id' => $room->getId()->toRfc4122(), '--force' => true]);

        $count = $this->entityManager->getRepository(Booking::class)->count([]);
        $this->assertSame(0, $count, 'Bei Fehlerhaftem Feed darf nichts importiert werden');
    }

    public function testSyncSkipsRoomsSyncedRecently(): void
    {
        $room = $this->createTestRoom();
        $room->setLastSyncBooking(new \DateTime('-5 minutes'));
        $this->entityManager->flush();

        $tester = $this->createCommandTester();
        $tester->execute(['--room-id' => $room->getId()->toRfc4122()]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Skipped (recent sync)', $tester->getDisplay());
        $this->assertSame(0, $this->entityManager->getRepository(Booking::class)->count([]),
            'Kürzlich synchronisierte Zimmer dürfen nicht erneut abgerufen werden');
    }

    public function testSyncUnknownRoomIdFails(): void
    {
        $tester = $this->createCommandTester();
        $tester->execute(['--room-id' => '00000000-0000-0000-0000-000000000000']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function createTestRoom(): Room
    {
        $room = new Room();
        $room->setName('Sync Zimmer');
        $room->setSlug('sync-zimmer-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice("100.00");
        $room->setIsActive(true);
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        return $room;
    }

    private function createCommandTester(): CommandTester
    {
        $application = new Application($this->client->getKernel());

        return new CommandTester($application->find('app:sync-calendars'));
    }
}
