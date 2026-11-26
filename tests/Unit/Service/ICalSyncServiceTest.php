<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\BookingRepository;
use App\Service\ICalSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ICalSyncServiceTest extends TestCase
{
    public function testServiceInitialization(): void
    {
        $service = $this->createService();

        $this->assertInstanceOf(ICalSyncService::class, $service);
    }

    public function testIcalContentParsing(): void
    {
        $icalContent = file_get_contents(__DIR__ . '/../../Fixtures/ical/booking_com_valid.ics');
        if ($icalContent === false) {
            $this->fail('Could not read iCal fixture file');
        }

        $this->assertStringContainsString('BEGIN:VCALENDAR', $icalContent);
        $this->assertStringContainsString('test-booking-1@booking.com', $icalContent);
        $this->assertStringContainsString('20250320', $icalContent);
        $this->assertStringContainsString('20250323', $icalContent);
    }

    public function testFetchAndParseICalReturnsEvents(): void
    {
        $icalContent = file_get_contents(__DIR__ . '/../../Fixtures/ical/booking_com_valid.ics');
        if ($icalContent === false) {
            $this->fail('Could not read iCal fixture file');
        }

        $httpClient = new MockHttpClient(new MockResponse($icalContent));
        $service = $this->createService($httpClient);

        $events = $service->fetchAndParseICal('https://example.com/booking.ics');

        $this->assertCount(1, $events);
        $this->assertSame('test-booking-1@booking.com', (string) $events[0]->UID);
    }

    public function testCheckAvailabilityDetectsConflict(): void
    {
        $service = $this->createServiceWithFixtureFeed();

        $available = $service->checkAvailability(
            'https://example.com/booking.ics',
            new \DateTime('2025-03-21'),
            new \DateTime('2025-03-22')
        );

        $this->assertFalse($available, 'Der Zeitraum überschneidet sich mit der externen Buchung');
    }

    public function testCheckAvailabilityAllowsAdjacentDates(): void
    {
        $service = $this->createServiceWithFixtureFeed();

        // Check-out am 2025-03-23 = neuer Check-in, keine Überschneidung
        $available = $service->checkAvailability(
            'https://example.com/booking.ics',
            new \DateTime('2025-03-23'),
            new \DateTime('2025-03-25')
        );

        $this->assertTrue($available, 'Angrenzende Termine dürfen keinen Konflikt erzeugen');
    }

    public function testCheckAvailabilityAllowsFreePeriod(): void
    {
        $service = $this->createServiceWithFixtureFeed();

        $available = $service->checkAvailability(
            'https://example.com/booking.ics',
            new \DateTime('2025-04-01'),
            new \DateTime('2025-04-05')
        );

        $this->assertTrue($available);
    }

    private function createServiceWithFixtureFeed(): ICalSyncService
    {
        $icalContent = file_get_contents(__DIR__ . '/../../Fixtures/ical/booking_com_valid.ics');
        if ($icalContent === false) {
            $this->fail('Could not read iCal fixture file');
        }

        return $this->createService(new MockHttpClient(new MockResponse($icalContent)));
    }

    private function createService(?\Symfony\Contracts\HttpClient\HttpClientInterface $httpClient = null): ICalSyncService
    {
        return new ICalSyncService(
            $this->createMock(BookingRepository::class),
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
            $httpClient
        );
    }

    public function testSyncRoomCalendarImportsFromHomeaway(): void
    {
        $room = new \App\Entity\Room();
        $room->setName('R');
        $room->setSlug('r-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);
        $room->setIcalUrlHomeaway('https://homeaway.example.com/feed.ics');

        $bookingRepository = $this->createMock(BookingRepository::class);
        $bookingRepository->method('findOneBy')->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with(self::isInstanceOf(\App\Entity\Booking::class));
        $entityManager->expects($this->once())->method('flush');

        $service = new ICalSyncService(
            $bookingRepository,
            $entityManager,
            new NullLogger(),
            new MockHttpClient(new MockResponse($this->fixtureContent()))
        );

        $newBookings = $service->syncRoomCalendar($room);

        $this->assertCount(1, $newBookings);
        $this->assertSame('homeaway', $newBookings[0]->getSource());
        $this->assertSame('Test Guest', $newBookings[0]->getGuestName());
        $this->assertNotNull($room->getLastSyncHomeaway());
    }

    public function testSyncSkipsCancelledEvents(): void
    {
        $room = new \App\Entity\Room();
        $room->setName('R');
        $room->setSlug('r-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');

        $bookingRepository = $this->createMock(BookingRepository::class);
        $bookingRepository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $service = new ICalSyncService(
            $bookingRepository,
            $entityManager,
            new NullLogger(),
            new MockHttpClient(new MockResponse($this->fixtureWith('STATUS:CANCELLED')))
        );

        $this->assertSame([], $service->syncRoomCalendar($room));
    }

    public function testSyncSkipsEventsWithoutDtend(): void
    {
        $room = new \App\Entity\Room();
        $room->setName('R');
        $room->setSlug('r-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');

        $bookingRepository = $this->createMock(BookingRepository::class);
        $bookingRepository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $content = "BEGIN:VCALENDAR\nVERSION:2.0\nBEGIN:VEVENT\nUID:no-dtend@booking.com\nDTSTART;VALUE=DATE:20250320\nEND:VEVENT\nEND:VCALENDAR";
        $service = new ICalSyncService(
            $bookingRepository,
            $entityManager,
            new NullLogger(),
            new MockHttpClient(new MockResponse($content))
        );

        $this->assertSame([], $service->syncRoomCalendar($room));
    }

    public function testSyncIsIdempotentForKnownExternalIds(): void
    {
        $room = new \App\Entity\Room();
        $room->setName('R');
        $room->setSlug('r-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');

        $existing = new \App\Entity\Booking();
        $bookingRepository = $this->createMock(BookingRepository::class);
        $bookingRepository->method('findOneBy')->willReturn($existing);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        $service = new ICalSyncService(
            $bookingRepository,
            $entityManager,
            new NullLogger(),
            new MockHttpClient(new MockResponse($this->fixtureContent()))
        );

        $this->assertSame([], $service->syncRoomCalendar($room), 'Bereits importierte Buchungen dürfen nicht erneut angelegt werden');
    }

    public function testGuestNameExtractionFromReservationSummary(): void
    {
        $room = new \App\Entity\Room();
        $room->setName('R');
        $room->setSlug('r-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);
        $room->setIcalUrlBooking('https://booking.example.com/feed.ics');

        $bookingRepository = $this->createMock(BookingRepository::class);
        $bookingRepository->method('findOneBy')->willReturn(null);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(function ($booking) { return $booking; });
        $entityManager->method('flush');

        $service = new ICalSyncService(
            $bookingRepository,
            $entityManager,
            new NullLogger(),
            new MockHttpClient(new MockResponse($this->fixtureWith('SUMMARY:Reservation: Jane Smith')))
        );

        $newBookings = $service->syncRoomCalendar($room);

        $this->assertSame('Jane Smith', $newBookings[0]->getGuestName());
    }


    public function testSyncHomeawayFailureIsLoggedAndSwallowed(): void
    {
        $room = new \App\Entity\Room();
        $room->setName('R');
        $room->setSlug('r-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice('100.00');
        $room->setIsActive(true);
        $room->setIcalUrlHomeaway('https://error.example.com/feed.ics');

        $service = new ICalSyncService(
            $this->createMock(BookingRepository::class),
            $this->createMock(EntityManagerInterface::class),
            new NullLogger(),
            new MockHttpClient(new MockResponse('', ['http_code' => 500]))
        );

        $this->assertSame([], $service->syncRoomCalendar($room), 'Feed-Fehler darf keine Exception nach aussen geben');
        $this->assertNull($room->getLastSyncHomeaway(), 'Bei Fehler darf kein Sync-Zeitstempel gesetzt werden');
    }

    private function fixtureContent(): string
    {
        $content = file_get_contents(__DIR__ . '/../../Fixtures/ical/booking_com_valid.ics');
        if ($content === false) {
            $this->fail('Could not read iCal fixture file');
        }

        return $content;
    }

    private function fixtureWith(string $replacement): string
    {
        return str_replace('SUMMARY:Reserved for: Test Guest', $replacement, $this->fixtureContent());
    }
}
