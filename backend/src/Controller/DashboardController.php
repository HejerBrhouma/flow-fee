<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Entity\Expense;
use App\Entity\SavingsGoal;
use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\ExpenseRepository;
use App\Repository\SavingsGoalRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dashboard', name: 'api_dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ExpenseRepository $expenseRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly SavingsGoalRepository $savingsGoalRepository,
    ) {}

    #[Route('', name: 'stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('n'));

        // Expenses can each carry their own currency, so every total here is converted
        // (at an approximate fixed rate — see CurrencyConverter) into the user's preferred
        // currency before summing, instead of silently mixing units.
        $currency = $user->getPreferredCurrency() ?? 'EUR';

        $monthlyTotal = $this->expenseRepository->getTotalByUserAndPeriod($user, $year, $month, $currency);
        $yearlyTotal = $this->expenseRepository->getTotalByUserAndYear($user, $year, $currency);
        $pendingCount = $this->expenseRepository->countByUserAndStatus($user, Expense::STATUS_SUBMITTED);
        $monthlyByCategory = $this->expenseRepository->getTotalByCategoryAndPeriod($user, $year, $month, $currency);
        $monthlyTrend = $this->expenseRepository->getMonthlyTrend($user, $year, $currency);

        return $this->json([
            'currency' => $currency,
            'monthlyTotal' => $monthlyTotal,
            'yearlyTotal' => $yearlyTotal,
            'pendingCount' => $pendingCount,
            'monthlyByCategory' => $monthlyByCategory,
            'monthlyTrend' => $monthlyTrend,
            'monthlyBudget' => $this->buildMonthlyBudgetPace($user, $year, $month),
            'savingsGoals' => $this->buildSavingsGoalsSummary($user),
        ]);
    }

    /**
     * Spend-vs-time-elapsed snapshot for the current personal monthly budget, powering the
     * dashboard's pace widget. Same "ahead of pace" concept as
     * BudgetController::notifyBudgetPaceIfAhead, surfaced visually here instead of (or
     * alongside) the notification. Only meaningful for the month actually in progress —
     * viewing a past/future month's stats returns no time-elapsed reading.
     */
    private function buildMonthlyBudgetPace(User $user, int $year, int $month): ?array
    {
        $budget = $this->budgetRepository->findOneBy([
            'user' => $user,
            'period' => Budget::PERIOD_MONTHLY,
            'year' => $year,
            'month' => $month,
        ]);

        if (!$budget) {
            return null;
        }

        $amount = (float) $budget->getAmount();
        $spent = $this->expenseRepository->getTotalByUserAndPeriod($user, $year, $month, $budget->getCurrency());
        $percentage = $amount > 0 ? round(($spent / $amount) * 100, 1) : 0.0;

        $now = new \DateTime();
        $isCurrentMonth = $year === (int) $now->format('Y') && $month === (int) $now->format('n');
        $timeElapsedPercentage = null;
        if ($isCurrentMonth) {
            $timeElapsedPercentage = round(((int) $now->format('j') / (int) $now->format('t')) * 100, 1);
        }

        return [
            'amount' => $amount,
            'spent' => $spent,
            'currency' => $budget->getCurrency(),
            'percentage' => $percentage,
            'timeElapsedPercentage' => $timeElapsedPercentage,
        ];
    }

    /**
     * Short list of active (not yet reached) savings goals for the dashboard summary —
     * short-term goals first (they move faster and benefit more from staying visible), then
     * whichever has the nearest deadline.
     */
    private function buildSavingsGoalsSummary(User $user): array
    {
        $goals = array_values(array_filter(
            $this->savingsGoalRepository->findBy(['user' => $user]),
            fn (SavingsGoal $g) => (float) $g->getCurrentAmount() < (float) $g->getTargetAmount()
        ));

        usort($goals, function (SavingsGoal $a, SavingsGoal $b) {
            if ($a->getTerm() !== $b->getTerm()) {
                return $a->getTerm() === SavingsGoal::TERM_SHORT ? -1 : 1;
            }
            $aDate = $a->getTargetDate()?->getTimestamp() ?? PHP_INT_MAX;
            $bDate = $b->getTargetDate()?->getTimestamp() ?? PHP_INT_MAX;
            return $aDate <=> $bDate;
        });

        return array_map(fn (SavingsGoal $g) => [
            'id' => $g->getId(),
            'name' => $g->getName(),
            'targetAmount' => (float) $g->getTargetAmount(),
            'currentAmount' => (float) $g->getCurrentAmount(),
            'currency' => $g->getCurrency(),
            'term' => $g->getTerm(),
            'targetDate' => $g->getTargetDate()?->format('Y-m-d'),
            'percentage' => (float) $g->getTargetAmount() > 0
                ? round(((float) $g->getCurrentAmount() / (float) $g->getTargetAmount()) * 100, 1)
                : 0.0,
        ], array_slice($goals, 0, 4));
    }
}
