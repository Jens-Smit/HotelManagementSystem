<?php
declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Room;
use App\Repository\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Eluceo\iCal\Domain\Entity\Event;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Presentation\Factory\CalendarFactory;

class ICalSyncService
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function fetchAndParseICal(string $icalUrl): array
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $icalUrl);
        $icalContent = $response->getContent();

        $calendar = (new CalendarFactory())->createCalendar($icalContent);
        return $calendar->getEvents();
    }

    public function syncRoomCalendar(Room $room): array
    {
        $newBookings = [];

        // Sync Booking.com
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

        // Sync HomeAway
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

    private function processEvents(Room $room, array $events, string $source): array
    {
        $newBookings = [];

        foreach ($events as $event) {
            $uid = (string)$event->getUniqueIdentifier();

            // Check if booking already exists
            $existing = $this->bookingRepository->findOneBy([
                'externalId' => $uid,
                'source' => $source,
            ]);

            if ($existing) {
                continue; // Already imported
            }

            // Check for cancellation
            $status = $event->getStatus();
            if ($status !== null && strtoupper($status->getValue()) === 'CANCELLED') {
                continue; // Skip cancelled events
            }

            try {
                $startDate = $event->getStartDate()->getDateTime();
                $endDate = $event->getEndDate()->getDateTime();
                $summary = (string)$event->getSummary();

                // Extract guest name from summary if possible
                $guestName = $this->extractGuestName($summary);

                $booking = new Booking();
                $booking->setRoom($room);
                $booking->setStartDate($startDate);
                $booking->setEndDate($endDate);
                $booking->setGuestName($guestName ?? 'External Guest');
                $booking->setGuestEmail('external@' . $source . '.com');
                $booking->setTotalPrice("0.0"); // Unknown for external bookings
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
        // Common patterns: "Reserved for: John Doe" or "Reservation: Jane Smith"
        if (preg_match('/(?:reserved for|reservation):\s*(.+)/i', $summary, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    /**
     * Prüft, ob ein Raum in einem bestimmten Zeitraum verfügbar ist,
     * basierend auf den Daten in den externen iCal-Feeds.
     */
    public function checkAvailability(Room $room, \DateTimeInterface $startDate, \DateTimeInterface $endDate): bool
    {
        // 1. Zuerst die URLs prüfen
        $urls = array_filter([
            $room->getIcalUrlBooking(),
            $room->getIcalUrlHomeaway()
        ]);

        foreach ($urls as $url) {
            try {
                $events = $this->fetchAndParseICal($url);
                
                foreach ($events as $event) {
                    $eventStart = $event->getStartDate()->getDateTime();
                    $eventEnd = $event->getEndDate()->getDateTime();

                    // Standard-Logik für Datumsüberschneidungen:
                    // (StartA < EndeB) UND (EndeA > StartB)
                    if ($startDate < $eventEnd && $endDate > $eventStart) {
                        $this->logger->info('ICal-Konflikt gefunden', [
                            'room' => $room->getId(),
                            'conflict_start' => $eventStart->format('Y-m-d'),
                            'conflict_end' => $eventEnd->format('Y-m-d')
                        ]);
                        return false; // Zeitraum ist belegt
                    }
                }
            } catch (\Exception $e) {
                $this->logger->error('Verfügbarkeitsprüfung via iCal fehlgeschlagen', [
                    'error' => $e->getMessage()
                ]);
                // Im Zweifel: true oder false? Meistens true, um Buchung nicht zu blockieren, 
                // oder false für maximale Sicherheit.
            }
        }

        return true; // Keine Konflikte gefunden
    }
}