<?php

namespace App\Repository;

use App\Entity\Appointment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Appointment>
 */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    public function countByStatus(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('a.status, COUNT(a.id) as cnt')
            ->groupBy('a.status')
            ->getQuery()
            ->getArrayResult();

        $counts = ['all' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];
        foreach ($rows as $r) {
            $st = strtolower($r['status']);
            if (isset($counts[$st])) {
                $counts[$st] = (int)$r['cnt'];
            }
            $counts['all'] += (int)$r['cnt'];
        }
        return $counts;
    }
}
