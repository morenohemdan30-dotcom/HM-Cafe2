<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    public function getAverageRating(): float
    {
        $avg = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->getQuery()
            ->getSingleScalarResult();

        return $avg ? round((float)$avg, 1) : 0.0;
    }
}
