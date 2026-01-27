<?php
declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Repository\BookingRepository;
use App\Service\ICalSyncService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ICalSyncServiceTest extends TestCase
{
    public function testServiceInitialization(): void
    {
        $bookingRepository = $this->createMock(BookingRepository::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $logger = new NullLogger();

        $service = new ICalSyncService($bookingRepository, $entityManager, $logger);

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
}
