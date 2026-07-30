<?php

namespace App\Repository;

use App\Entity\Department;
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
        [$start, $end] = $this->monthRange($year, $month);

        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.amount)')
            ->where('e.user = :user')
            ->andWhere('e.expenseDate >= :start')
            ->andWhere('e.expenseDate < :end')
            ->andWhere('e.status != :status')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', Expense::STATUS_REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    public function getTotalByUserAndYear(User $user, int $year): float
    {
        [$start, $end] = $this->yearRange($year);

        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.amount)')
            ->where('e.user = :user')
            ->andWhere('e.expenseDate >= :start')
            ->andWhere('e.expenseDate < :end')
            ->andWhere('e.status != :status')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
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
        [$start, $end] = $this->monthRange($year, $month);

        return $this->createQueryBuilder('e')
            ->select('c.name, c.color, c.icon, SUM(e.amount) as total')
            ->leftJoin('e.category', 'c')
            ->where('e.user = :user')
            ->andWhere('e.expenseDate >= :start')
            ->andWhere('e.expenseDate < :end')
            ->andWhere('e.status != :status')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', Expense::STATUS_REJECTED)
            ->groupBy('c.id')
            ->getQuery()
            ->getArrayResult();
    }

    public function getTotalByDepartmentAndPeriod(Department $department, int $year, ?int $month = null): float
    {
        [$start, $end] = $month !== null ? $this->monthRange($year, $month) : $this->yearRange($year);

        $result = $this->createQueryBuilder('e')
            ->select('SUM(e.amount)')
            ->where('e.department = :department')
            ->andWhere('e.expenseDate >= :start')
            ->andWhere('e.expenseDate < :end')
            ->andWhere('e.status != :status')
            ->setParameter('department', $department)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', Expense::STATUS_REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Doctrine DQL has no native YEAR()/MONTH() function, so this aggregation
     * runs as native SQL against the underlying table instead.
     */
    public function getMonthlyTrend(User $user, int $year): array
    {
        [$start, $end] = $this->yearRange($year);

        $sql = <<<'SQL'
            SELECT MONTH(expense_date) AS month, SUM(amount) AS total
            FROM expenses
            WHERE user_id = :user
              AND expense_date >= :start
              AND expense_date < :end
              AND status != :status
            GROUP BY month
            ORDER BY month ASC
            SQL;

        return $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'user' => $user->getId(),
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'status' => Expense::STATUS_REJECTED,
        ]);
    }

    private function monthRange(int $year, int $month): array
    {
        $start = new \DateTime(sprintf('%04d-%02d-01', $year, $month));
        $end = (clone $start)->modify('first day of next month');

        return [$start, $end];
    }

    private function yearRange(int $year): array
    {
        $start = new \DateTime(sprintf('%04d-01-01', $year));
        $end = (clone $start)->modify('+1 year');

        return [$start, $end];
    }
}
