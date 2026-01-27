<?php

namespace App\Repository;

use App\Entity\Booking;
use App\Enum\BookingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Find bookings that conflict with given date range
     * @return Booking[]
     */
    public function findConflicts(
        string $roomId,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): array {
        return $this->createQueryBuilder('b')
            ->where('b.room = :roomId')
            ->andWhere('b.status != :cancelled')
            ->andWhere('b.startDate < :end')
            ->andWhere('b.endDate > :start')
            ->setParameter('roomId', $roomId)
            ->setParameter('cancelled', BookingStatus::CANCELLED->value)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find booking by hash
     * @return Booking|null
     */
    public function findByHash(string $hash): ?Booking
    {
        return $this->findOneBy(['hash' => $hash]);
    }

    /**
     * Save booking
     */
    public function save(Booking $booking, bool $flush = false): void
    {
        $this->getEntityManager()->persist($booking);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}