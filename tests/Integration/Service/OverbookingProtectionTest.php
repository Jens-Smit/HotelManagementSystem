<?php
declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Entity\Booking;
use App\Entity\Room;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Nachweis der unteren Schichten des Doppelbuchungsschutzes:
 * 1. DB-Unique-Constraint (Schicht 5)
 * 2. Optimistic Locking (Schicht 3)
 */
class OverbookingProtectionTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        static::bootKernel();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($this->entityManager);
        $metadatas = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadatas);
        $schemaTool->createSchema($metadatas);
    }

    public function testDatabaseUniqueConstraintBlocksDuplicateBooking(): void
    {
        $room = $this->createTestRoom();

        $this->persistBooking($room, '2030-09-01', '2030-09-05', 'hash-a');
        $this->entityManager->flush();

        // Identische Konstellation (Raum, Zeitraum, Status) ein zweites Mal direkt
        // über den Entity-Manager einfügen: Der Unique-Index muss das verhindern.
        $this->persistBooking($room, '2030-09-01', '2030-09-05', 'hash-b');

        try {
            $this->entityManager->flush();
            $this->fail('Der Unique-Constraint hätte einen doppelten Insert verhindern müssen');
        } catch (UniqueConstraintViolationException $e) {
            $this->addToAssertionCount(1);
        }
    }

    public function testCancelledBookingAllowsNewConfirmedBookingForSameDates(): void
    {
        $room = $this->createTestRoom();

        $this->persistBooking($room, '2030-09-01', '2030-09-05', 'hash-a', 'cancelled');
        $this->entityManager->flush();

        // Gleiche Daten, aber ein anderer Status: muss erlaubt sein,
        // sonst könnte ein stornierter Zeitraum nie neu gebucht werden.
        $this->persistBooking($room, '2030-09-01', '2030-09-05', 'hash-b', 'confirmed');
        $this->entityManager->flush();

        $this->addToAssertionCount(1);
    }

    public function testDifferentDateRangesDoNotCollide(): void
    {
        $room = $this->createTestRoom();

        $this->persistBooking($room, '2030-09-01', '2030-09-05', 'hash-a');
        // Überlappende, aber nicht identische Zeiträume werden vom
        // Unique-Constraint nicht abgedeckt (das übernimmt findConflicts)
        $this->persistBooking($room, '2030-09-03', '2030-09-07', 'hash-b');
        $this->entityManager->flush();

        $this->addToAssertionCount(1);
    }

    public function testOptimisticLockingPreventsLostUpdate(): void
    {
        $room = $this->createTestRoom();
        $this->persistBooking($room, '2030-09-01', '2030-09-05', 'hash-a');
        $this->entityManager->flush();

        $bookingId = $this->entityManager->getRepository(Booking::class)
            ->findOneBy(['bookingHash' => 'hash-a'])
            ->getId();

        $this->entityManager->clear();

        $booking = $this->entityManager->find(Booking::class, $bookingId);

        // Parallel laufende Änderung erhöht die Version in der Datenbank,
        // während unser Objekt noch die veraltete Version hält.
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE bookings SET guest_name = ?, version = version + 1 WHERE id = ?',
            ['Schriftsteller A', $bookingId->toBinary()]
        );

        $booking->setGuestName('Schriftsteller B');

        try {
            $this->entityManager->flush();
            $this->fail('Der Optimistic Lock hätte das verlorene Update verhindern müssen');
        } catch (OptimisticLockException $e) {
            $this->addToAssertionCount(1);
        }
    }

    protected function tearDown(): void
    {
        $this->entityManager->clear();
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    private function createTestRoom(): Room
    {
        $room = new Room();
        $room->setName('Schutzzimmer');
        $room->setSlug('schutzzimmer-' . uniqid());
        $room->setCapacity(2);
        $room->setBasePrice("100.00");
        $room->setIsActive(true);

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        return $room;
    }

    private function persistBooking(Room $room, string $start, string $end, string $hash, string $status = 'confirmed'): Booking
    {
        $booking = new Booking();
        $booking->setRoom($room);
        $booking->setStartDate(new \DateTime($start));
        $booking->setEndDate(new \DateTime($end));
        $booking->setGuestName('Gast');
        $booking->setGuestEmail('gast@example.com');
        $booking->setTotalPrice('400.00');
        $booking->setSource('website');
        $booking->setStatus($status);
        $booking->setBookingHash($hash);

        $this->entityManager->persist($booking);

        return $booking;
    }
}
