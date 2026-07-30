<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Entity\Department;
use App\Entity\User;
use App\Repository\BudgetRepository;
use App\Repository\ExpenseRepository;
use App\Security\Voter\CompanyVoter;
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

        $spent = $budget->getUser() !== null
            ? ($budget->getPeriod() === Budget::PERIOD_MONTHLY
                ? $this->expenseRepository->getTotalByUserAndPeriod($budget->getUser(), $budget->getYear(), $budget->getMonth())
                : $this->expenseRepository->getTotalByUserAndYear($budget->getUser(), $budget->getYear()))
            : $this->expenseRepository->getTotalByDepartmentAndPeriod(
                $budget->getDepartment(),
                $budget->getYear(),
                $budget->getPeriod() === Budget::PERIOD_MONTHLY ? $budget->getMonth() : null
            );

        $amount = (float) $budget->getAmount();

        return $this->json([
            'budget' => json_decode($this->serializer->serialize($budget, 'json', ['groups' => ['budget:read']])),
            'spent' => $spent,
            'remaining' => $amount - $spent,
            'percentage' => $amount > 0 ? round(($spent / $amount) * 100, 1) : 0.0,
        ]);
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
