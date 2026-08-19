<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Entity\Department;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserCompany;
use App\Repository\BudgetRepository;
use App\Repository\ExpenseRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserCompanyRepository;
use App\Security\Voter\CompanyVoter;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BudgetController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BudgetRepository $budgetRepository,
        private readonly ExpenseRepository $expenseRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly NotificationRepository $notificationRepository,
        private readonly NotificationService $notificationService,
        private readonly UserCompanyRepository $userCompanyRepository,
    ) {}

    // --- Company (department) budgets ---

    #[Route('/api/departments/{id}/budgets', name: 'api_department_budgets_list', methods: ['GET'])]
    public function list(Department $department): JsonResponse
    {
        $this->denyAccessUnlessGranted(CompanyVoter::MEMBER, $department->getCompany());

        return $this->json(
            json_decode($this->serializer->serialize($department->getBudgets(), 'json', ['groups' => ['budget:read']]))
        );
    }

    #[Route('/api/departments/{id}/budgets', name: 'api_department_budgets_create', methods: ['POST'])]
    public function create(Department $department, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(CompanyVoter::ADMIN, $department->getCompany());

        $budget = new Budget();
        $budget->setDepartment($department);

        return $this->saveBudgetFromRequest($budget, $request);
    }

    // --- Personal budgets ---

    #[Route('/api/me/budgets', name: 'api_my_budgets_list', methods: ['GET'])]
    public function myBudgets(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $budgets = $this->budgetRepository->findBy(['user' => $user], ['year' => 'DESC', 'month' => 'DESC']);

        return $this->json(
            json_decode($this->serializer->serialize($budgets, 'json', ['groups' => ['budget:read']]))
        );
    }

    #[Route('/api/me/budgets', name: 'api_my_budgets_create', methods: ['POST'])]
    public function createMyBudget(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $budget = new Budget();
        $budget->setUser($user);

        return $this->saveBudgetFromRequest($budget, $request);
    }

    // --- Shared by both kinds of budget ---

    #[Route('/api/budgets/{id}', name: 'api_budget_show', methods: ['GET'])]
    public function show(Budget $budget): JsonResponse
    {
        $this->assertBudgetAccess($budget);

        return $this->json(
            json_decode($this->serializer->serialize($budget, 'json', ['groups' => ['budget:read']]))
        );
    }

    #[Route('/api/budgets/{id}', name: 'api_budget_update', methods: ['PUT', 'PATCH'])]
    public function update(Budget $budget, Request $request): JsonResponse
    {
        $this->assertBudgetAccess($budget, requireAdmin: true);

        $data = json_decode($request->getContent(), true);

        if (isset($data['amount'])) {
            $budget->setAmount((string) $data['amount']);
        }
        if (isset($data['currency'])) {
            $budget->setCurrency($data['currency']);
        }

        $errors = $this->validator->validate($budget);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($budget, 'json', ['groups' => ['budget:read']]))
        );
    }

    #[Route('/api/budgets/{id}', name: 'api_budget_delete', methods: ['DELETE'])]
    public function delete(Budget $budget): JsonResponse
    {
        $this->assertBudgetAccess($budget, requireAdmin: true);

        $this->em->remove($budget);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/budgets/{id}/consumption', name: 'api_budget_consumption', methods: ['GET'])]
    public function consumption(Budget $budget): JsonResponse
    {
        $this->assertBudgetAccess($budget);

        // Expenses can each carry their own currency, so "spent" is converted (approximate
        // live rate — see CurrencyConverter) into the budget's own currency before comparing
        // it against the budget amount, instead of mixing units.
        $spent = $budget->getUser() !== null
            ? ($budget->getPeriod() === Budget::PERIOD_MONTHLY
                ? $this->expenseRepository->getTotalByUserAndPeriod($budget->getUser(), $budget->getYear(), $budget->getMonth(), $budget->getCurrency())
                : $this->expenseRepository->getTotalByUserAndYear($budget->getUser(), $budget->getYear(), $budget->getCurrency()))
            : $this->expenseRepository->getTotalByDepartmentAndPeriod(
                $budget->getDepartment(),
                $budget->getYear(),
                $budget->getPeriod() === Budget::PERIOD_MONTHLY ? $budget->getMonth() : null,
                $budget->getCurrency()
            );

        $amount = (float) $budget->getAmount();
        $percentage = $amount > 0 ? round(($spent / $amount) * 100, 1) : 0.0;

        $this->notifyBudgetPaceIfAhead($budget, $percentage);

        return $this->json([
            'budget' => json_decode($this->serializer->serialize($budget, 'json', ['groups' => ['budget:read']])),
            'spent' => $spent,
            'remaining' => $amount - $spent,
            'percentage' => $percentage,
        ]);
    }

    /**
     * Early-warning alert distinct from the 90%/100% "just crossed" alerts fired on expense
     * create/approve (see ExpenseController) — this one catches overspending *pace* even
     * without a new expense triggering it, e.g. having already burned 60% of the budget by
     * the middle of the month. Checked opportunistically whenever consumption is viewed
     * (this app has no cron/scheduler), so it fires the first time the budget page is opened
     * on/after the day the pace condition becomes true, not necessarily on the exact day.
     * Guarded against re-firing every page load by checking for a prior pace notification
     * this month.
     */
    private function notifyBudgetPaceIfAhead(Budget $budget, float $percentage): void
    {
        if ($budget->getPeriod() !== Budget::PERIOD_MONTHLY) {
            return;
        }

        $now = new \DateTime();
        if ($budget->getYear() !== (int) $now->format('Y') || $budget->getMonth() !== (int) $now->format('n')) {
            return;
        }

        $dayOfMonth = (int) $now->format('j');
        $daysInMonth = (int) $now->format('t');
        $timeElapsedPercentage = ($dayOfMonth / $daysInMonth) * 100;

        // "Ahead of pace": already further into the budget than into the month, and past a
        // floor so it doesn't fire on trivially small amounts on day 1.
        if ($percentage < 60 || $percentage < $timeElapsedPercentage + 10) {
            return;
        }

        $recipients = $budget->getUser()
            ? [$budget->getUser()]
            : array_map(
                fn (UserCompany $membership) => $membership->getUser(),
                $this->userCompanyRepository->findBy(['company' => $budget->getDepartment()->getCompany(), 'role' => UserCompany::ROLE_ADMIN])
            );

        foreach ($recipients as $recipient) {
            $alreadyNotified = false;
            foreach ($this->notificationRepository->findBy(['user' => $recipient, 'type' => Notification::TYPE_BUDGET_ALERT]) as $existing) {
                $data = $existing->getData() ?? [];
                if (($data['budgetId'] ?? null) === $budget->getId() && ($data['pace'] ?? false) === true
                    && $existing->getCreatedAt() >= new \DateTime('first day of this month 00:00:00')) {
                    $alreadyNotified = true;
                    break;
                }
            }
            if ($alreadyNotified) {
                continue;
            }

            $label = $budget->getUser() ? 'Votre budget mensuel' : sprintf('Le budget du département "%s"', $budget->getDepartment()->getName());
            $this->notificationService->notify(
                $recipient,
                Notification::TYPE_BUDGET_ALERT,
                sprintf(
                    '%s est déjà consommé à %.0f%% alors que le mois n\'est qu\'à %.0f%% — au rythme actuel, vous risquez de le dépasser avant la fin du mois.',
                    $label,
                    $percentage,
                    $timeElapsedPercentage
                ),
                ['budgetId' => $budget->getId(), 'pace' => true],
            );
        }

        $this->em->flush();
    }

    private function saveBudgetFromRequest(Budget $budget, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $budget->setAmount((string) ($data['amount'] ?? '0'));
        $budget->setCurrency($data['currency'] ?? 'EUR');
        $budget->setPeriod($data['period'] ?? Budget::PERIOD_MONTHLY);
        $budget->setYear($data['year'] ?? (int) date('Y'));
        $budget->setMonth(
            $budget->getPeriod() === Budget::PERIOD_MONTHLY
                ? (int) ($data['month'] ?? (int) date('n'))
                : null
        );

        $errors = $this->validator->validate($budget);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($budget);
        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($budget, 'json', ['groups' => ['budget:read']])),
            Response::HTTP_CREATED
        );
    }

    private function assertBudgetAccess(Budget $budget, bool $requireAdmin = false): void
    {
        if ($budget->getUser() !== null) {
            /** @var User $user */
            $user = $this->getUser();
            if ($budget->getUser()->getId() !== $user->getId()) {
                throw $this->createAccessDeniedException();
            }
            return;
        }

        $this->denyAccessUnlessGranted(
            $requireAdmin ? CompanyVoter::ADMIN : CompanyVoter::MEMBER,
            $budget->getDepartment()->getCompany()
        );
    }
}
