<?php
declare(strict_types=1);

namespace App\Tests\HttpMock;

use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Liefert im Test-Environment deterministische iCal-Feeds für alle
 * HTTP-Anfragen des ICalSyncService (via framework.http_client.mock_response_factory).
 *
 * - URLs, die "error" enthalten, antworten mit HTTP 500.
 * - Alle anderen URLs erhalten die Fixture booking_com_valid.ics.
 */
class IcalMockResponseFactory
{
    public function __invoke(string $method, string $url, array $options = []): MockResponse
    {
        if (str_contains($url, 'error')) {
            return new MockResponse('', ['http_code' => 500]);
        }

        $icalContent = file_get_contents(__DIR__ . '/../Fixtures/ical/booking_com_valid.ics');
        if ($icalContent === false) {
            throw new \RuntimeException('Could not read iCal fixture file');
        }

        return new MockResponse($icalContent);
    }
}
