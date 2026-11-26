<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Booking;
use App\Entity\Room;
use App\Repository\BookingRepository;
use App\Service\AvailabilityService;
use App\Service\ICalSyncService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AvailabilityServiceTest extends TestCase
{
    private BookingRepository&MockObject $bookingRepository;
    private ICalSyncService&MockObject $icalSyncService;
    private AvailabilityService $service;

    protected function setUp(): void
    {
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->icalSyncService = $this->createMock(ICalSyncService::class);
        $this->service = new AvailabilityService(
            $this->bookingRepository,
            $this->icalSyncService,
            new NullLogger()
        );
    }

    public function testFreeRoomIsAvailableWithInternalCheckOnly(): void
    {
        $room = $this->createRoom();
        $this->bookingRepository->method('findConflicts')->willReturn([]);

        $result = $this->service->checkRoomAvailability(
            $room,
            new \DateTime('2030-05-01'),
            new \DateTime('2030-05-05'),
            false
        );

        $this->assertTrue($result['available']);
        $this->assertSame('internal_database', $result['checks_performed'][0]);
        $this->assertArrayHasKey('last_checked', $result);
    }

    public function testInternalConflictMarksRoomUnavailable(): void
    {
        $room = $this->createRoom();
        $conflict = new Booking();
        $conflict->setRoom($room);
        $conflict->setStartDate(new \DateTime('2030-05-01'));
        $conflict->setEndDate(new \DateTime('2030-05-05'));
        $conflict->setGuestName('Gast');
        $conflict->setGuestEmail('gast@example.com');
        $conflict->setTotalPrice('100');
        $conflict->setSource('website');
        $conflict->setStatus('confirmed');
        $conflict->setBookingHash(bin2hex(random_bytes(16)));

        $this->bookingRepository->method('findConflicts')->willReturn([$conflict]);

        $result = $this->service->checkRoomAvailability(
            $room,
            new \DateTime('2030-05-03'),
            new \DateTime('2030-05-07'),
            false
        );

        $this->assertFalse($result['available']);
        $this->assertSame('internal_booking', $result['conflict_reason']);
        $this->assertSame('website', $result['conflict_details'][0]['source']);
        $this->assertArrayNotHasKey('last_checked', $result, 'Bei Konflikt wird kein Zeitstempel gesetzt');
    }

    public function testBookingComConflictMarksRoomUnavailable(): void
    {
        $room = $this->createRoom();
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');

        $this->bookingRepository->method('findConflicts')->willReturn([]);
        $this->icalSyncService->method('checkAvailability')->willReturn(false);

        $result = $this->service->checkRoomAvailability(
            $room,
            new \DateTime('2030-05-01'),
            new \DateTime('2030-05-05'),
            true
        );

        $this->assertFalse($result['available']);
        $this->assertSame('booking_com_reservation', $result['conflict_reason']);
    }

    public function testHomeawayConflictMarksRoomUnavailable(): void
    {
        $room = $this->createRoom();
        $room->setIcalUrlHomeaway('https://homeaway.example.com/feed.ics');

        $this->bookingRepository->method('findConflicts')->willReturn([]);
        $this->icalSyncService->method('checkAvailability')->willReturn(false);

        $result = $this->service->checkRoomAvailability(
            $room,
            new \DateTime('2030-05-01'),
            new \DateTime('2030-05-05'),
            true
        );

        $this->assertFalse($result['available']);
        $this->assertSame('homeaway_reservation', $result['conflict_reason']);
    }

    public function testFailingIcalCheckDoesNotBlockAvailability(): void
    {
        $room = $this->createRoom();
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');

        $this->bookingRepository->method('findConflicts')->willReturn([]);
        $this->icalSyncService->method('checkAvailability')
            ->willThrowException(new \RuntimeException('timeout'));

        $result = $this->service->checkRoomAvailability(
            $room,
            new \DateTime('2030-05-01'),
            new \DateTime('2030-05-05'),
            true
        );

        $this->assertTrue($result['available'], 'Ein iCal-Fehler darf nicht sperren (fail-open)');
        $this->assertContains('booking_com_ical_failed', $result['checks_performed']);
    }

    private function createRoom(): Room
    {
        $room = new Room();

        // UUID simulieren, die sonst erst die Datenbank vergibt
        $reflection = new \ReflectionProperty(Room::class, 'id');
        $reflection->setAccessible(true);
        $reflection->setValue($room, new \Symfony\Component\Uid\UuidV7());
        $room->setName('Testzimmer');
        $room->setSlug('testzimmer-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);

        return $room;
    }
}
