<?php

namespace App\Repository;

use App\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return Product[] Returns an array of Product objects filtered by category
     */
    public function findByCategory(string $categoryname): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.category', 'c')
            ->andWhere('c.categoryname = :categoryname')
            ->setParameter('categoryname', $categoryname)
            ->orderBy('p.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Product[]
     */
    public function findActiveProducts(?string $category = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.isActive = true');

        if ($category && $category !== 'All') {
            $qb->join('p.category', 'c')
               ->andWhere('c.categoryname = :category')
               ->setParameter('category', $category);
        }

        if ($search) {
            $qb->andWhere('p.name LIKE :search OR p.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        return $qb->orderBy('p.id', 'ASC')
                  ->getQuery()
                  ->getResult();
    }
}
