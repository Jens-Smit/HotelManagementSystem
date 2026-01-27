<?php

namespace App\Repository;

use App\Entity\AvailabilitySnapshot;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AvailabilitySnapshot>
 */
class AvailabilitySnapshotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvailabilitySnapshot::class);
    }
}