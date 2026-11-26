<?php
declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Repository\RoomRepository;
use App\Service\ICalSyncService;
use App\Command\SyncCalendarsCommand;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Tester\CommandTester;

class SyncCalendarsCommandUnitTest extends TestCase
{
    public function testSyncErrorIsCountedAndCommandFailsGracefully(): void
    {
        $room = new \App\Entity\Room();
        $room->setName('Fehlerzimmer');
        $room->setSlug('fehlerzimmer-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);

        $roomRepository = $this->createMock(RoomRepository::class);
        $roomRepository->method('findBy')->willReturn([$room]);

        $icalSyncService = $this->createMock(ICalSyncService::class);
        $icalSyncService->method('syncRoomCalendar')
            ->willThrowException(new \RuntimeException('Feed nicht erreichbar'));

        $command = new SyncCalendarsCommand($roomRepository, $icalSyncService, new NullLogger());
        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(1, $tester->getStatusCode(), 'Ein Fehler beim Sync muss Command::FAILURE ergeben');
        $this->assertStringContainsString('Fehlerzimmer', $tester->getDisplay());
    }

    public function testSyncWithoutRoomsSucceeds(): void
    {
        $roomRepository = $this->createMock(RoomRepository::class);
        $roomRepository->method('findBy')->willReturn([]);

        $command = new SyncCalendarsCommand(
            $roomRepository,
            $this->createMock(ICalSyncService::class),
            new NullLogger()
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Synchronization complete', $tester->getDisplay());
    }
}
