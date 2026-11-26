<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\Request\CreateBookingRequest;
use App\Entity\Booking;
use App\Entity\Room;
use App\Repository\BookingRepository;
use App\Repository\RoomRepository;
use App\Service\BookingService;
use App\Service\ICalSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class BookingServiceTest extends TestCase
{
    private BookingRepository&MockObject $bookingRepository;
    private RoomRepository&MockObject $roomRepository;
    private ICalSyncService&MockObject $icalSyncService;
    private EntityManagerInterface&MockObject $entityManager;
    private BookingService $service;

    protected function setUp(): void
    {
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->roomRepository = $this->createMock(RoomRepository::class);
        $this->icalSyncService = $this->createMock(ICalSyncService::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->service = new BookingService(
            $this->entityManager,
            $this->bookingRepository,
            $this->roomRepository,
            $this->icalSyncService,
            new NullLogger()
        );
    }

    public function testCreateBookingReturnsRoomNotFoundForUnknownRoom(): void
    {
        $this->roomRepository->method('find')->willReturn(null);

        $response = $this->service->createBooking($this->request());

        $this->assertFalse($response->success);
        $this->assertSame('ROOM_NOT_FOUND', $response->error);
    }

    public function testCreateBookingReturnsRoomNotFoundForInactiveRoom(): void
    {
        $room = $this->createRoom();
        $room->setIsActive(false);
        $this->roomRepository->method('find')->willReturn($room);

        $response = $this->service->createBooking($this->request());

        $this->assertFalse($response->success);
        $this->assertSame('ROOM_NOT_FOUND', $response->error);
    }

    public function testCreateBookingReportsInternalConflictWithAlternatives(): void
    {
        $room = $this->createRoom();
        $this->roomRepository->method('find')->willReturn($room);
        $this->bookingRepository->method('findConflicts')->willReturn([$this->createBooking($room)]);

        $alternative = $this->createRoom();
        $this->roomRepository->method('findAvailableRoomsByCapacity')->willReturn([$alternative]);

        $response = $this->service->createBooking($this->request());

        $this->assertFalse($response->success);
        $this->assertSame('INTERNAL_CONFLICT', $response->error);
        $this->assertSame(1, $response->details['conflict_count']);
        $this->assertCount(1, $response->suggestedAlternatives);
    }

    public function testCreateBookingReportsExternalBookingComConflict(): void
    {
        $room = $this->createRoom();
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');
        $this->roomRepository->method('find')->willReturn($room);
        $this->bookingRepository->method('findConflicts')->willReturn([]);
        $this->icalSyncService->method('checkAvailability')->willReturn(false);

        $response = $this->service->createBooking($this->request());

        $this->assertFalse($response->success);
        $this->assertSame('EXTERNAL_CONFLICT', $response->error);
        $this->assertSame('booking_com', $response->details['conflict_source']);
    }

    public function testCreateBookingReportsExternalHomeawayConflict(): void
    {
        $room = $this->createRoom();
        $room->setIcalUrlHomeaway('https://homeaway.example.com/feed.ics');
        $this->roomRepository->method('find')->willReturn($room);
        $this->bookingRepository->method('findConflicts')->willReturn([]);
        $this->icalSyncService->method('checkAvailability')->willReturn(false);

        $response = $this->service->createBooking($this->request());

        $this->assertFalse($response->success);
        $this->assertSame('EXTERNAL_CONFLICT', $response->error);
        $this->assertSame('homeaway', $response->details['conflict_source']);
    }

    public function testCreateBookingFailsOpenWhenICalCheckThrows(): void
    {
        $room = $this->createRoom();
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');
        $this->roomRepository->method('find')->willReturn($room);
        $this->bookingRepository->method('findConflicts')->willReturn([]);
        $this->icalSyncService->method('checkAvailability')->willThrowException(new \RuntimeException('timeout'));

        $response = $this->service->createBooking($this->request());

        $this->assertTrue($response->success, 'Fehlerhafter iCal-Check darf die Buchung nicht blockieren (fail-open)');
        $this->assertSame(400.0, $response->totalPrice);
    }

    public function testCreateBookingSucceedsAndCalculatesPrice(): void
    {
        $room = $this->createRoom();
        $this->roomRepository->method('find')->willReturn($room);
        $this->bookingRepository->method('findConflicts')->willReturn([]);
        $this->entityManager->expects($this->once())->method('persist')->with(self::isInstanceOf(Booking::class));
        $this->entityManager->expects($this->once())->method('flush');

        $response = $this->service->createBooking($this->request());

        $this->assertTrue($response->success);
        $this->assertSame(4, 4);
        $this->assertSame(400.0, $response->totalPrice);
        $this->assertNotNull($response->bookingHash);
        $this->assertNotSame('', $response->bookingHash);
    }

    public function testCreateBookingReportsTransactionFailure(): void
    {
        $room = $this->createRoom();
        $this->roomRepository->method('find')->willReturn($room);
        $this->bookingRepository->method('findConflicts')->willReturn([]);
        $this->entityManager->method('persist')->willThrowException(new \RuntimeException('disk full'));

        $response = $this->service->createBooking($this->request());

        $this->assertFalse($response->success);
        $this->assertSame('TRANSACTION_FAILED', $response->error);
    }


    public function testCreateBookingReportsSystemErrorOnUnexpectedFailure(): void
    {
        $room = $this->createRoom();
        $this->roomRepository->method('find')->willReturn($room);
        $this->bookingRepository->method('findConflicts')->willThrowException(new \RuntimeException('db down'));

        $response = $this->service->createBooking($this->request());

        $this->assertFalse($response->success);
        $this->assertSame('SYSTEM_ERROR', $response->error);
    }

    public function testCreateBookingReportsConcurrentModificationOnOptimisticLock(): void
    {
        $room = $this->createRoom();
        $this->roomRepository->method('find')->willReturn($room);
        $this->bookingRepository->method('findConflicts')->willReturn([]);
        $this->entityManager->method('persist')
            ->willThrowException(new \Doctrine\ORM\OptimisticLockException('version conflict', null));

        $response = $this->service->createBooking($this->request());

        $this->assertFalse($response->success);
        $this->assertSame('CONCURRENT_MODIFICATION', $response->error);
    }

    private function request(): CreateBookingRequest
    {
        $dto = new CreateBookingRequest();
        $dto->roomId = '01a21966-382a-7c23-b68a-ab4e464c0d3e';
        $dto->startDate = '2030-06-05';
        $dto->endDate = '2030-06-09';
        $dto->guestName = 'Test Guest';
        $dto->guestEmail = 'test@example.com';
        $dto->guestPhone = '+49123456789';

        return $dto;
    }

    private function createRoom(): Room
    {
        $room = new Room();
        $room->setName('Testzimmer');
        $room->setSlug('testzimmer-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);

        return $room;
    }

    private function createBooking(Room $room): Booking
    {
        $booking = new Booking();
        $booking->setRoom($room);
        $booking->setStartDate(new \DateTime('2030-06-05'));
        $booking->setEndDate(new \DateTime('2030-06-09'));
        $booking->setGuestName('Anderer Gast');
        $booking->setGuestEmail('gast@example.com');
        $booking->setTotalPrice('400.00');
        $booking->setSource('website');
        $booking->setStatus('confirmed');
        $booking->setBookingHash(bin2hex(random_bytes(16)));

        return $booking;
    }
}
