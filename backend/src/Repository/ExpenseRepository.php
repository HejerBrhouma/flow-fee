<?php

namespace App\Repository;

use App\Entity\Department;
use App\Entity\Expense;
use App\Entity\User;
use App\Service\CurrencyConverter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;

class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly CurrencyConverter $currencyConverter,
    ) {
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

    public function getTotalByUserAndPeriod(User $user, int $year, int $month, string $targetCurrency = 'EUR'): float
    {
        [$start, $end] = $this->monthRange($year, $month);

        return $this->sumConverted(
            $this->amountsQueryBuilder($start, $end)
                ->andWhere('e.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getArrayResult(),
            $targetCurrency
        );
    }

    public function getTotalByUserAndYear(User $user, int $year, string $targetCurrency = 'EUR'): float
    {
        [$start, $end] = $this->yearRange($year);

        return $this->sumConverted(
            $this->amountsQueryBuilder($start, $end)
                ->andWhere('e.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->getArrayResult(),
            $targetCurrency
        );
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

    public function getTotalByCategoryAndPeriod(User $user, int $year, int $month, string $targetCurrency = 'EUR'): array
    {
        [$start, $end] = $this->monthRange($year, $month);

        $rows = $this->createQueryBuilder('e')
            ->select('c.name, c.color, c.icon, e.amount, e.currency')
            ->leftJoin('e.category', 'c')
            ->where('e.user = :user')
            ->andWhere('e.expenseDate >= :start')
            ->andWhere('e.expenseDate < :end')
            ->andWhere('e.status != :status')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', Expense::STATUS_REJECTED)
            ->getQuery()
            ->getArrayResult();

        $totals = [];
        foreach ($rows as $row) {
            $key = $row['name'] ?? '__uncategorized__';
            if (!isset($totals[$key])) {
                $totals[$key] = ['name' => $row['name'], 'color' => $row['color'], 'icon' => $row['icon'], 'total' => 0.0];
            }
            $totals[$key]['total'] += $this->currencyConverter->convert((float) $row['amount'], $row['currency'], $targetCurrency);
        }

        return array_values($totals);
    }

    public function getTotalByDepartmentAndPeriod(Department $department, int $year, ?int $month = null, string $targetCurrency = 'EUR'): float
    {
        [$start, $end] = $month !== null ? $this->monthRange($year, $month) : $this->yearRange($year);

        return $this->sumConverted(
            $this->amountsQueryBuilder($start, $end)
                ->andWhere('e.department = :department')
                ->setParameter('department', $department)
                ->getQuery()
                ->getArrayResult(),
            $targetCurrency
        );
    }

    /**
     * Doctrine DQL has no native YEAR()/MONTH() function, so this aggregation
     * runs as native SQL against the underlying table instead.
     */
    public function getMonthlyTrend(User $user, int $year, string $targetCurrency = 'EUR'): array
    {
        [$start, $end] = $this->yearRange($year);

        $sql = <<<'SQL'
            SELECT MONTH(expense_date) AS month, amount, currency
            FROM expenses
            WHERE user_id = :user
              AND expense_date >= :start
              AND expense_date < :end
              AND status != :status
            SQL;

        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, [
            'user' => $user->getId(),
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d'),
            'status' => Expense::STATUS_REJECTED,
        ]);

        $totals = [];
        foreach ($rows as $row) {
            $month = (int) $row['month'];
            $totals[$month] ??= 0.0;
            $totals[$month] += $this->currencyConverter->convert((float) $row['amount'], $row['currency'], $targetCurrency);
        }

        ksort($totals);

        return array_map(
            fn (int $month, float $total) => ['month' => $month, 'total' => $total],
            array_keys($totals),
            array_values($totals)
        );
    }

    private function amountsQueryBuilder(\DateTimeInterface $start, \DateTimeInterface $end)
    {
        return $this->createQueryBuilder('e')
            ->select('e.amount, e.currency')
            ->where('e.expenseDate >= :start')
            ->andWhere('e.expenseDate < :end')
            ->andWhere('e.status != :status')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->setParameter('status', Expense::STATUS_REJECTED);
    }

    private function sumConverted(array $rows, string $targetCurrency): float
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += $this->currencyConverter->convert((float) $row['amount'], $row['currency'], $targetCurrency);
        }

        return $total;
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
