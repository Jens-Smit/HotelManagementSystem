<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Room;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Sabre\VObject\Reader;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\Component\VEvent;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ICalSyncService
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private ?HttpClientInterface $httpClient = null,
    ) {
    }

    /**
     * @return VEvent[]
     */
    public function fetchAndParseICal(string $icalUrl): array
    {
        $client = $this->httpClient ?? HttpClient::create();
        $response = $client->request('GET', $icalUrl);
        $icalContent = $response->getContent();

        /** @var VCalendar $calendar */
        $calendar = Reader::read($icalContent);

        $events = $calendar->VEVENT ?? [];

        if (!is_array($events)) {
            $events = [$events];
        }

        return array_values($events);
    }

    /**
     * @return Booking[]
     */
    public function syncRoomCalendar(Room $room): array
    {
        $newBookings = [];

        if ($room->getIcalUrlBooking()) {
            try {
                $events = $this->fetchAndParseICal($room->getIcalUrlBooking());
                $newBookings = array_merge(
                    $newBookings,
                    $this->processEvents($room, $events, 'booking_com')
                );
                $room->setLastSyncBooking(new \DateTime());
            } catch (\Exception $e) {
                $this->logger->error('Booking.com sync failed', [
                    'room_id' => $room->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($room->getIcalUrlHomeaway()) {
            try {
                $events = $this->fetchAndParseICal($room->getIcalUrlHomeaway());
                $newBookings = array_merge(
                    $newBookings,
                    $this->processEvents($room, $events, 'homeaway')
                );
                $room->setLastSyncHomeaway(new \DateTime());
            } catch (\Exception $e) {
                $this->logger->error('HomeAway sync failed', [
                    'room_id' => $room->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $newBookings;
    }

    /**
     * @param VEvent[] $events
     * @return Booking[]
     */
    private function processEvents(Room $room, array $events, string $source): array
    {
        $newBookings = [];

        foreach ($events as $event) {
            $uid = (string) ($event->UID ?? '');

            if ($uid === '') {
                continue;
            }

            $existing = $this->bookingRepository->findOneBy([
                'externalId' => $uid,
                'source' => $source,
            ]);

            if ($existing) {
                continue;
            }

            if (strtoupper((string) ($event->STATUS ?? '')) === 'CANCELLED') {
                continue;
            }

            try {
                $startDate = $event->DTSTART?->getDateTime();
                $endDate = $event->DTEND?->getDateTime();

                if (!$startDate || !$endDate) {
                    continue;
                }

                $summary = (string) ($event->SUMMARY ?? '');
                $guestName = $this->extractGuestName($summary);

                $booking = new Booking();
                $booking->setRoom($room);
                $booking->setStartDate($startDate);
                $booking->setEndDate($endDate);
                $booking->setGuestName($guestName ?? 'External Guest');
                $booking->setGuestEmail('external@' . $source . '.com');
                $booking->setTotalPrice("0.0");
                $booking->setSource($source);
                $booking->setStatus('confirmed');
                $booking->setExternalId($uid);
                $booking->setBookingHash(bin2hex(random_bytes(32)));

                $this->entityManager->persist($booking);
                $newBookings[] = $booking;

                $this->logger->info('External booking imported', [
                    'room_id' => $room->getId(),
                    'source' => $source,
                    'external_id' => $uid,
                ]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to process event', [
                    'uid' => $uid,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (!empty($newBookings)) {
            $this->entityManager->flush();
        }

        return $newBookings;
    }

    private function extractGuestName(string $summary): ?string
    {
        if (preg_match('/(?:reserved for|reservation):\s*(.+)/i', $summary, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Prüft anhand eines externen iCal-Feeds, ob der Zeitraum frei ist.
     */
    public function checkAvailability(string $icalUrl, \DateTimeInterface $startDate, \DateTimeInterface $endDate): bool
    {
        try {
            $events = $this->fetchAndParseICal($icalUrl);

            foreach ($events as $event) {
                if (strtoupper((string) ($event->STATUS ?? '')) === 'CANCELLED') {
                    continue;
                }

                $eventStart = $event->DTSTART?->getDateTime();
                $eventEnd = $event->DTEND?->getDateTime();

                if (!$eventStart || !$eventEnd) {
                    continue;
                }

                if ($startDate < $eventEnd && $endDate > $eventStart) {
                    $this->logger->info('ICal-Konflikt gefunden', [
                        'ical_url' => $icalUrl,
                        'conflict_start' => $eventStart->format('Y-m-d'),
                        'conflict_end' => $eventEnd->format('Y-m-d'),
                    ]);

                    return false;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Verfügbarkeitsprüfung via iCal fehlgeschlagen', [
                'ical_url' => $icalUrl,
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }
}
