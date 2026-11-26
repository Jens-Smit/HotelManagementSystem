<?php

namespace App\Repository;

use App\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    //    /**
    //     * @return Room[] Returns an array of Room objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('r.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Room
    //    {
    //        return $this->createQueryBuilder('r')
    //            ->andWhere('r.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * Find available rooms by capacity
     * @return Room[]
     */
    public function findAvailableRoomsByCapacity(
        int $capacity,
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        int $limit = 10
    ): array {
        // Find rooms not in conflicting bookings
        $qb = $this->createQueryBuilder('r')
            ->where('r.isActive = :active')
            ->andWhere('r.capacity >= :capacity')
            ->andWhere('r.id NOT IN (
                SELECT IDENTITY(b.room)
                FROM App\Entity\Booking b
                WHERE b.status != :cancelled
                AND b.startDate < :end
                AND b.endDate > :start
            )')
            ->setParameter('active', true)
            ->setParameter('capacity', $capacity)
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return Room[]
     */
    public function findByFilters(bool $isActive, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.isActive = :active')
            ->setParameter('active', $isActive)
            ->orderBy('r.name', 'ASC')
            ->setFirstResult(max(0, ($page - 1) * $limit))
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function countByFilters(bool $isActive): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.isActive = :active')
            ->setParameter('active', $isActive)
            ->getQuery()
            ->getSingleScalarResult();
    }
}