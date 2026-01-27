<?php
declare(strict_types=1);

namespace App\Command;

use App\Repository\RoomRepository;
use App\Service\ICalSyncService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync-calendars',
    description: 'Sync external calendars from Booking.com and HomeAway',
)]
class SyncCalendarsCommand extends Command
{
    public function __construct(
        private RoomRepository $roomRepository,
        private ICalSyncService $icalSyncService,
        private LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'room-id',
                null,
                InputOption::VALUE_OPTIONAL,
                'Sync specific room only'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force sync even if recently synced'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $startTime = microtime(true);

        $io->title('iCal Calendar Synchronization');

        // Determine which rooms to sync
        $roomId = $input->getOption('room-id');
        if ($roomId) {
            $rooms = [$this->roomRepository->find($roomId)];
            if (!$rooms[0]) {
                $io->error("Room with ID {$roomId} not found");
                return Command::FAILURE;
            }
        } else {
            $rooms = $this->roomRepository->findBy(['isActive' => true]);
        }

        $stats = [
            'total_rooms' => count($rooms),
            'synced' => 0,
            'errors' => 0,
            'new_bookings' => 0,
            'skipped' => 0,
        ];

        $io->progressStart(count($rooms));

        foreach ($rooms as $room) {
            try {
                // Skip if recently synced (unless forced)
                if (!$input->getOption('force')) {
                    $lastSync = $room->getLastSyncBooking() ?? $room->getLastSyncHomeaway();
                    if ($lastSync && $lastSync > new \DateTime('-15 minutes')) {
                        $stats['skipped']++;
                        $io->progressAdvance();
                        continue;
                    }
                }

                $newBookings = $this->icalSyncService->syncRoomCalendar($room);
                $stats['new_bookings'] += count($newBookings);
                $stats['synced']++;

                if (!empty($newBookings)) {
                    $io->writeln(sprintf(
                        '  → Room "%s": %d new bookings',
                        $room->getName(),
                        count($newBookings)
                    ));
                }

            } catch (\Exception $e) {
                $this->logger->error('Sync failed for room', [
                    'room_id' => $room->getId(),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $stats['errors']++;

                $io->writeln(sprintf(
                    '  ✗ Room "%s": %s',
                    $room->getName(),
                    $e->getMessage()
                ));
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        // Summary
        $duration = round(microtime(true) - $startTime, 2);

        $io->newLine();
        $io->success('Synchronization complete');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total rooms', $stats['total_rooms']],
                ['Successfully synced', $stats['synced']],
                ['Skipped (recent sync)', $stats['skipped']],
                ['New bookings imported', $stats['new_bookings']],
                ['Errors', $stats['errors']],
                ['Duration', $duration . 's'],
            ]
        );

        // Log summary
        $this->logger->info('Calendar sync completed', $stats);

        return $stats['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
