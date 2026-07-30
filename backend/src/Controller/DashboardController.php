<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\User;
use App\Repository\ExpenseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/dashboard', name: 'api_dashboard_')]
class DashboardController extends AbstractController
{
    public function __construct(
        private readonly ExpenseRepository $expenseRepository,
    ) {}

    #[Route('', name: 'stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $year = $request->query->getInt('year', (int) date('Y'));
        $month = $request->query->getInt('month', (int) date('n'));

        $monthlyTotal = $this->expenseRepository->getTotalByUserAndPeriod($user, $year, $month);
        $yearlyTotal = $this->expenseRepository->getTotalByUserAndYear($user, $year);
        $pendingCount = $this->expenseRepository->countByUserAndStatus($user, Expense::STATUS_SUBMITTED);
        $monthlyByCategory = $this->expenseRepository->getTotalByCategoryAndPeriod($user, $year, $month);
        $monthlyTrend = $this->expenseRepository->getMonthlyTrend($user, $year);

        return $this->json([
            'monthlyTotal' => $monthlyTotal,
            'yearlyTotal' => $yearlyTotal,
            'pendingCount' => $pendingCount,
            'monthlyByCategory' => $monthlyByCategory,
            'monthlyTrend' => $monthlyTrend,
        ]);
    }
}
