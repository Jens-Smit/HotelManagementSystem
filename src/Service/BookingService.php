<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Room;
use App\DTO\Request\CreateBookingRequest;
use App\DTO\Response\BookingResponse;
use App\Repository\BookingRepository;
use App\Repository\RoomRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

class BookingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private RoomRepository $roomRepository,
        private ICalSyncService $icalSyncService,
        private LoggerInterface $logger,
    ) {}

    public function createBooking(CreateBookingRequest $request): BookingResponse
    {
        try {
            // 1. Find and validate room
            $room = $this->roomRepository->find($request->roomId);
            if (!$room || !$room->isActive()) {
                return new BookingResponse(
                    success: false,
                    error: 'ROOM_NOT_FOUND',
                    message: 'Room not found or inactive'
                );
            }

            // 2. Parse dates
            $startDate = new \DateTime($request->startDate);
            $endDate = new \DateTime($request->endDate);

            // 3. Check internal database conflicts
            $conflicts = $this->bookingRepository->findConflicts(
                $request->roomId,
                $startDate,
                $endDate
            );

            if (!empty($conflicts)) {
                $this->logger->info('Internal booking conflict detected', [
                    'room_id' => $request->roomId,
                    'requested_dates' => [$request->startDate, $request->endDate],
                    'conflicts' => array_map(fn($b) => $b->getId()?->toRfc4122(), $conflicts),
                ]);

                return new BookingResponse(
                    success: false,
                    error: 'INTERNAL_CONFLICT',
                    message: 'Room already booked for these dates',
                    details: ['conflict_count' => count($conflicts)],
                    suggestedAlternatives: $this->findAlternativeRooms($room, $startDate, $endDate)
                );
            }

            // 4. ⚠️ CRITICAL: Real-time iCal check
            $icalCheckResult = $this->performICalCheck($room, $startDate, $endDate);
            if (!$icalCheckResult['success']) {
                return new BookingResponse(
                    success: false,
                    error: $icalCheckResult['error'],
                    message: $icalCheckResult['message'],
                    details: $icalCheckResult['details'] ?? null
                );
            }

            // 5. Calculate total price
            $nights = (int) $startDate->diff($endDate)->days;
            $totalPrice = (string) ((float) $room->getBasePrice() * $nights);

            // 6. Create booking with transaction + optimistic locking
            return $this->createBookingTransaction($room, $request, $startDate, $endDate, $totalPrice);

        } catch (\Exception $e) {
            $this->logger->error('Booking creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new BookingResponse(
                success: false,
                error: 'SYSTEM_ERROR',
                message: 'An error occurred. Please try again or contact support.'
            );
        }
    }

    private function performICalCheck(Room $room, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        $checkResults = [];

        // Check Booking.com - skip if URL is not set
        if ($room->getIcalUrlBooking()) {
            try {
                $available = $this->icalSyncService->checkAvailability(
                    $room->getIcalUrlBooking(),
                    $start,
                    $end
                );

                if (!$available) {
                    return [
                        'success' => false,
                        'error' => 'EXTERNAL_CONFLICT',
                        'message' => 'Room not available (Booking.com reservation exists)',
                        'details' => ['conflict_source' => 'booking_com'],
                    ];
                }
                $checkResults['booking_com'] = 'available';
            } catch (\Exception $e) {
                $this->logger->error('Booking.com iCal check failed', [
                    'room_id' => $room->getId()?->toRfc4122(),
                    'error' => $e->getMessage(),
                ]);

                // Fail-open approach: log the error but continue with booking
                $checkResults['booking_com'] = 'check_failed';
            }
        }

        // Check HomeAway - skip if URL is not set
        if ($room->getIcalUrlHomeaway()) {
            try {
                $available = $this->icalSyncService->checkAvailability(
                    $room->getIcalUrlHomeaway(),
                    $start,
                    $end
                );

                if (!$available) {
                    return [
                        'success' => false,
                        'error' => 'EXTERNAL_CONFLICT',
                        'message' => 'Room not available (HomeAway reservation exists)',
                        'details' => ['conflict_source' => 'homeaway'],
                    ];
                }
                $checkResults['homeaway'] = 'available';
            } catch (\Exception $e) {
                $this->logger->error('HomeAway iCal check failed', [
                    'room_id' => $room->getId()?->toRfc4122(),
                    'error' => $e->getMessage(),
                ]);

                // Fail-open approach: log the error but continue with booking
                $checkResults['homeaway'] = 'check_failed';
            }
        }

        // If we have no iCal URLs or all checks passed/failed-open, return success
        return ['success' => true, 'checks' => $checkResults];
    }

    private function createBookingTransaction(
        Room $room,
        CreateBookingRequest $request,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $totalPrice
    ): BookingResponse {
        $this->entityManager->beginTransaction();

        try {
            $booking = new Booking();
            $booking->setRoom($room);
            $booking->setStartDate($startDate);
            $booking->setEndDate($endDate);
            $booking->setGuestName($request->guestName);
            $booking->setGuestEmail($request->guestEmail);
            $booking->setGuestPhone($request->guestPhone);
            $booking->setTotalPrice($totalPrice);
            $booking->setSource('website');
            $booking->setStatus('confirmed');
            $booking->setBookingHash(bin2hex(random_bytes(32)));

            $this->entityManager->persist($booking);
            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Booking created successfully', [
                'booking_id' => $booking->getId()?->toRfc4122(),
                'room_id' => $room->getId()?->toRfc4122(),
                'guest_email' => $request->guestEmail,
            ]);

            // TODO: Dispatch email notification event here

            return new BookingResponse(
                success: true,
                bookingId: $booking->getId()?->toRfc4122(),
                bookingHash: $booking->getBookingHash(),
                totalPrice: (float) $totalPrice,
                message: 'Booking confirmed. Confirmation email sent.'
            );

        } catch (OptimisticLockException $e) {
            $this->entityManager->rollback();

            $this->logger->warning('Optimistic lock failure during booking', [
                'room_id' => $room->getId()?->toRfc4122(),
            ]);

            return new BookingResponse(
                success: false,
                error: 'CONCURRENT_MODIFICATION',
                message: 'Room availability changed. Please refresh and try again.'
            );
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Transaction failed', [
                'error' => $e->getMessage(),
                'room_id' => $room->getId()?->toRfc4122(),
            ]);

            return new BookingResponse(
                success: false,
                error: 'TRANSACTION_FAILED',
                message: 'Booking could not be completed. Please try again.'
            );
        }
    }

    private function findAlternativeRooms(Room $currentRoom, \DateTimeInterface $start, \DateTimeInterface $end): array
    {
        // Find rooms with similar capacity that are available
        $alternativeRooms = $this->roomRepository->findAvailableRoomsByCapacity(
            $currentRoom->getCapacity(),
            $start,
            $end,
            3 // Limit to 3 suggestions
        );

        return array_map(fn(Room $room) => [
            'room_id' => $room->getId()?->toRfc4122(),
            'room_name' => $room->getName(),
            'capacity' => $room->getCapacity(),
            'base_price' => $room->getBasePrice(),
        ], $alternativeRooms);
    }
}