<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Room;
use App\Repository\BookingRepository;
use Psr\Log\LoggerInterface;

class AvailabilityService
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private ICalSyncService $icalSyncService,
        private LoggerInterface $logger,
    ) {}

    public function checkRoomAvailability(
        Room $room,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        bool $realTimeCheck = false
    ): array {
        $result = [
            'available' => true,
            'room_id' => $room->getId()?->toRfc4122(),
            'room_name' => $room->getName(),
            'start_date' => $start->format('Y-m-d'),
            'end_date' => $end->format('Y-m-d'),
            'checks_performed' => [],
        ];

        // 1. Check internal database
        $conflicts = $this->bookingRepository->findConflicts(
            $room->getId()?->toRfc4122(),
            $start,
            $end
        );

        $result['checks_performed'][] = 'internal_database';

        if (!empty($conflicts)) {
            $result['available'] = false;
            $result['conflict_reason'] = 'internal_booking';
            $result['conflict_details'] = array_map(
                fn($b) => [
                    'booking_id' => $b->getId()?->toRfc4122(),
                    'source' => $b->getSource(),
                    'start' => $b->getStartDate()->format('Y-m-d'),
                    'end' => $b->getEndDate()->format('Y-m-d'),
                ],
                $conflicts
            );
            return $result;
        }

        // 2. Real-time iCal check (if requested)
        if ($realTimeCheck) {
            if ($room->getIcalUrlBooking()) {
                try {
                    $bookingComAvailable = $this->icalSyncService->checkAvailability(
                        $room->getIcalUrlBooking(),
                        $start,
                        $end
                    );

                    $result['checks_performed'][] = 'booking_com_ical';

                    if (!$bookingComAvailable) {
                        $result['available'] = false;
                        $result['conflict_reason'] = 'booking_com_reservation';
                        return $result;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Booking.com availability check failed', [
                        'room_id' => $room->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    $result['checks_performed'][] = 'booking_com_ical_failed';
                }
            }

            if ($room->getIcalUrlHomeaway()) {
                try {
                    $homeawayAvailable = $this->icalSyncService->checkAvailability(
                        $room->getIcalUrlHomeaway(),
                        $start,
                        $end
                    );

                    $result['checks_performed'][] = 'homeaway_ical';

                    if (!$homeawayAvailable) {
                        $result['available'] = false;
                        $result['conflict_reason'] = 'homeaway_reservation';
                        return $result;
                    }
                } catch (\Exception $e) {
                    $this->logger->error('HomeAway availability check failed', [
                        'room_id' => $room->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    $result['checks_performed'][] = 'homeaway_ical_failed';
                }
            }
        }

        $result['last_checked'] = (new \DateTime())->format('c');
        return $result;
    }
}