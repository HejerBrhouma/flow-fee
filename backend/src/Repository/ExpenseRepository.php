<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    public function findByUserQuery(User $user, array $filters = []): Query
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.category', 'c')
            ->leftJoin('e.department', 'd')
            ->addSelect('c', 'd')
            ->where('e.user = :user')
            ->setParameter('user', $user)
            ->orderBy('e.expenseDate', 'DESC');

        if (!empty($filters['status'])) {
            $qb->andWhere('e.status = :status')->setParameter('status', $filters['status']);
        }

        if (!empty($filters['category'])) {
            $qb->andWhere('c.id = :category')->setParameter('category', $filters['category']);
        }

        if (!empty($filters['dateFrom'])) {
            $qb->andWhere('e.expenseDate >= :dateFrom')->setParameter('dateFrom', new \DateTime($filters['dateFrom']));
        }

        if (!empty($filters['dateTo'])) {
            $qb->andWhere('e.expenseDate <= :dateTo')->setParameter('dateTo', new \DateTime($filters['dateTo']));
        }

        return $qb->getQuery();
    }

    public function getTotalByUserAndPeriod(User $user, int $year, int $month): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.amount)')
            ->where('e.user = :user')
            ->andWhere('YEAR(e.expenseDate) = :year')
            ->andWhere('MONTH(e.expenseDate) = :month')
            ->andWhere('e.status != :status')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->setParameter('status', Expense::STATUS_REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getTotalByUserAndYear(User $user, int $year): float
    {
        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.amount)')
            ->where('e.user = :user')
            ->andWhere('YEAR(e.expenseDate) = :year')
            ->andWhere('e.status != :status')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->setParameter('status', Expense::STATUS_REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function countByUserAndStatus(User $user, string $status): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.user = :user')
            ->andWhere('e.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTotalByCategoryAndPeriod(User $user, int $year, int $month): array
    {
        return $this->createQueryBuilder('e')
            ->select('c.name, c.color, c.icon, SUM(e.amount) as total')
            ->leftJoin('e.category', 'c')
            ->where('e.user = :user')
            ->andWhere('YEAR(e.expenseDate) = :year')
            ->andWhere('MONTH(e.expenseDate) = :month')
            ->andWhere('e.status != :status')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->setParameter('month', $month)
            ->setParameter('status', Expense::STATUS_REJECTED)
            ->groupBy('c.id')
            ->getQuery()
            ->getArrayResult();
    }

    public function getMonthlyTrend(User $user, int $year): array
    {
        return $this->createQueryBuilder('e')
            ->select('MONTH(e.expenseDate) as month, SUM(e.amount) as total')
            ->where('e.user = :user')
            ->andWhere('YEAR(e.expenseDate) = :year')
            ->andWhere('e.status != :status')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->setParameter('status', Expense::STATUS_REJECTED)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
