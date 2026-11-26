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
}
