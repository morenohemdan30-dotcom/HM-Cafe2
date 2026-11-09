<?php

namespace App\Repository;

use App\Entity\Order;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('o')
            ->select('o.status, COUNT(o.id) as cnt')
            ->groupBy('o.status')
            ->getQuery()
            ->getArrayResult();

        $counts = ['all' => 0, 'pending' => 0, 'completed' => 0, 'cancelled' => 0];
        foreach ($rows as $r) {
            $st = strtolower($r['status']);
            if (isset($counts[$st])) {
                $counts[$st] = (int)$r['cnt'];
            }
            $counts['all'] += (int)$r['cnt'];
        }
        return $counts;
    }

    public function calculateGrossRevenue(): float
    {
        $res = $this->createQueryBuilder('o')
            ->select('SUM(o.quantity * p.price) as gross')
            ->join('o.product', 'p')
            ->where('o.status != :cancelled')
            ->setParameter('cancelled', 'Cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($res ?? 0.0);
    }
}
