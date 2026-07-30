<?php

namespace App\Controller;

use App\Entity\Budget;
use App\Entity\Department;
use App\Entity\Expense;
use App\Entity\ExpenseReceipt;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserCompany;
use App\Repository\BudgetRepository;
use App\Repository\CategoryRepository;
use App\Repository\DepartmentRepository;
use App\Repository\ExpenseReceiptRepository;
use App\Repository\ExpenseRepository;
use App\Repository\UserCompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Vich\UploaderBundle\Storage\StorageInterface;

#[Route('/api/expenses', name: 'api_expense_')]
class ExpenseController extends AbstractController
{
    private const MAX_RECEIPT_SIZE = 10 * 1024 * 1024; // 10 MB
    private const ALLOWED_RECEIPT_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExpenseRepository $expenseRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly DepartmentRepository $departmentRepository,
        private readonly BudgetRepository $budgetRepository,
        private readonly UserCompanyRepository $userCompanyRepository,
        private readonly ExpenseReceiptRepository $expenseReceiptRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly PaginatorInterface $paginator,
        private readonly StorageInterface $storage,
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * Resolves each receipt's Vich-managed filename into an absolute download URL
     * on a transient property, since entities can't depend on the storage service themselves.
     */
    private function withReceiptUrls(Expense $expense): Expense
    {
        $request = $this->requestStack->getCurrentRequest();

        foreach ($expense->getReceipts() as $receipt) {
            $uri = $this->storage->resolveUri($receipt, 'file');
            $receipt->setDownloadUrl($uri !== null && $request ? $request->getSchemeAndHttpHost() . $uri : $uri);
        }

        return $expense;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $query = $this->expenseRepository->findByUserQuery($user, [
            'status' => $request->query->get('status'),
            'category' => $request->query->get('category'),
            'dateFrom' => $request->query->get('dateFrom'),
            'dateTo' => $request->query->get('dateTo'),
        ]);

        $pagination = $this->paginator->paginate(
            $query,
            $request->query->getInt('page', 1),
            $request->query->getInt('limit', 20)
        );

        foreach ($pagination as $item) {
            $this->withReceiptUrls($item);
        }

        return $this->json([
            'items' => json_decode($this->serializer->serialize(iterator_to_array($pagination), 'json', ['groups' => ['expense:read']])),
            'total' => $pagination->getTotalItemCount(),
            'page' => $pagination->getCurrentPageNumber(),
            'pages' => ceil($pagination->getTotalItemCount() / $pagination->getItemNumberPerPage()),
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $expense = new Expense();
        $expense->setUser($user);
        $expense->setTitle($data['title'] ?? '');
        $expense->setAmount($data['amount'] ?? '0');
        $expense->setCurrency($data['currency'] ?? 'EUR');
        $expense->setDescription($data['description'] ?? null);

        if (!empty($data['expenseDate'])) {
            $expense->setExpenseDate(new \DateTime($data['expenseDate']));
        }

        if (!empty($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            $expense->setCategory($category);
        }

        if (!empty($data['departmentId'])) {
            $department = $this->departmentRepository->find($data['departmentId']);
            $expense->setDepartment($department);
        }

        $errors = $this->validator->validate($expense);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($expense);
        $this->em->flush();

        if (!$expense->getDepartment()) {
            $this->notifyPersonalBudgetAlertIfCrossed($expense);
        }

        return $this->json(
            json_decode($this->serializer->serialize($this->withReceiptUrls($expense), 'json', ['groups' => ['expense:read']])),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Expense $expense): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $expense);

        return $this->json(
            json_decode($this->serializer->serialize($this->withReceiptUrls($expense), 'json', ['groups' => ['expense:read']]))
        );
    }

    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $expense);

        if ($expense->getStatus() !== Expense::STATUS_DRAFT) {
            return $this->json(['message' => 'Seules les dépenses en brouillon peuvent être modifiées.'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) $expense->setTitle($data['title']);
        if (isset($data['amount'])) $expense->setAmount($data['amount']);
        if (isset($data['currency'])) $expense->setCurrency($data['currency']);
        if (isset($data['description'])) $expense->setDescription($data['description']);
        if (isset($data['expenseDate'])) $expense->setExpenseDate(new \DateTime($data['expenseDate']));

        if (isset($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            $expense->setCategory($category);
        }

        $this->em->flush();

        if (isset($data['amount']) && !$expense->getDepartment()) {
            $this->notifyPersonalBudgetAlertIfCrossed($expense);
        }

        return $this->json(
            json_decode($this->serializer->serialize($this->withReceiptUrls($expense), 'json', ['groups' => ['expense:read']]))
        );
    }

    #[Route('/{id}/submit', name: 'submit', methods: ['POST'])]
    public function submit(Expense $expense): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $expense);

        if ($expense->getStatus() !== Expense::STATUS_DRAFT) {
            return $this->json(['message' => 'Cette dépense ne peut pas être soumise.'], Response::HTTP_BAD_REQUEST);
        }

        $expense->setStatus(Expense::STATUS_SUBMITTED);
        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($this->withReceiptUrls($expense), 'json', ['groups' => ['expense:read']]))
        );
    }

    #[Route('/{id}/review', name: 'review', methods: ['POST'])]
    public function review(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('review', $expense);

        if ($expense->getStatus() !== Expense::STATUS_SUBMITTED) {
            return $this->json(['message' => 'Cette dépense n\'est pas en attente de validation.'], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? null;

        if (!in_array($action, ['approve', 'reject'])) {
            return $this->json(['message' => 'Action invalide. Utilisez "approve" ou "reject".'], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $reviewer */
        $reviewer = $this->getUser();
        $expense->setReviewedBy($reviewer);
        $expense->setReviewedAt(new \DateTime());
        $expense->setReviewComment($data['comment'] ?? null);
        $expense->setStatus($action === 'approve' ? Expense::STATUS_APPROVED : Expense::STATUS_REJECTED);

        // Notify the expense owner
        $notification = new Notification();
        $notification->setUser($expense->getUser());
        $notification->setType($action === 'approve' ? Notification::TYPE_EXPENSE_APPROVED : Notification::TYPE_EXPENSE_REJECTED);
        $notification->setMessage(sprintf(
            'Votre dépense "%s" a été %s.',
            $expense->getTitle(),
            $action === 'approve' ? 'approuvée' : 'rejetée'
        ));
        $notification->setData(['expenseId' => $expense->getId()]);

        $this->em->persist($notification);
        $this->em->flush();

        if ($action === 'approve') {
            $this->notifyBudgetAlertIfCrossed($expense);
        }

        return $this->json(
            json_decode($this->serializer->serialize($this->withReceiptUrls($expense), 'json', ['groups' => ['expense:read']]))
        );
    }

    /**
     * Notifies the company admins when this approval pushes the department's budget
     * consumption across the 90% or 100% mark (fires once per crossing, not on every
     * approval past the threshold).
     */
    private function notifyBudgetAlertIfCrossed(Expense $expense): void
    {
        $department = $expense->getDepartment();
        if (!$department) {
            return;
        }

        $year = (int) $expense->getExpenseDate()->format('Y');
        $month = (int) $expense->getExpenseDate()->format('n');

        $budget = $this->budgetRepository->findOneBy([
            'department' => $department,
            'period' => Budget::PERIOD_MONTHLY,
            'year' => $year,
            'month' => $month,
        ]) ?? $this->budgetRepository->findOneBy([
            'department' => $department,
            'period' => Budget::PERIOD_YEARLY,
            'year' => $year,
        ]);

        $amount = $budget ? (float) $budget->getAmount() : 0;
        if (!$budget || $amount <= 0) {
            return;
        }

        $spentAfter = $this->expenseRepository->getTotalByDepartmentAndPeriod(
            $department,
            $budget->getYear(),
            $budget->getPeriod() === Budget::PERIOD_MONTHLY ? $budget->getMonth() : null
        );
        $spentBefore = $spentAfter - (float) $expense->getAmount();

        $percentBefore = ($spentBefore / $amount) * 100;
        $percentAfter = ($spentAfter / $amount) * 100;

        $crossedThreshold = null;
        foreach ([100, 90] as $threshold) {
            if ($percentBefore < $threshold && $percentAfter >= $threshold) {
                $crossedThreshold = $threshold;
                break;
            }
        }

        if ($crossedThreshold === null) {
            return;
        }

        $admins = $this->userCompanyRepository->findBy([
            'company' => $department->getCompany(),
            'role' => UserCompany::ROLE_ADMIN,
        ]);

        foreach ($admins as $membership) {
            $notification = new Notification();
            $notification->setUser($membership->getUser());
            $notification->setType(Notification::TYPE_BUDGET_ALERT);
            $notification->setMessage(sprintf(
                'Le budget du département "%s" a atteint %d%% (%.2f / %.2f %s).',
                $department->getName(),
                $crossedThreshold,
                $spentAfter,
                $amount,
                $budget->getCurrency()
            ));
            $notification->setData(['departmentId' => $department->getId(), 'budgetId' => $budget->getId()]);
            $this->em->persist($notification);
        }

        $this->em->flush();
    }

    /**
     * Personal expenses have no company/manager to approve them, so the alert fires
     * as soon as the expense is created/edited instead of waiting for a review step.
     */
    private function notifyPersonalBudgetAlertIfCrossed(Expense $expense): void
    {
        $user = $expense->getUser();
        $year = (int) $expense->getExpenseDate()->format('Y');
        $month = (int) $expense->getExpenseDate()->format('n');

        $budget = $this->budgetRepository->findOneBy([
            'user' => $user,
            'period' => Budget::PERIOD_MONTHLY,
            'year' => $year,
            'month' => $month,
        ]) ?? $this->budgetRepository->findOneBy([
            'user' => $user,
            'period' => Budget::PERIOD_YEARLY,
            'year' => $year,
        ]);

        $amount = $budget ? (float) $budget->getAmount() : 0;
        if (!$budget || $amount <= 0) {
            return;
        }

        $spentAfter = $budget->getPeriod() === Budget::PERIOD_MONTHLY
            ? $this->expenseRepository->getTotalByUserAndPeriod($user, $budget->getYear(), $budget->getMonth())
            : $this->expenseRepository->getTotalByUserAndYear($user, $budget->getYear());
        $spentBefore = $spentAfter - (float) $expense->getAmount();

        $percentBefore = ($spentBefore / $amount) * 100;
        $percentAfter = ($spentAfter / $amount) * 100;

        $crossedThreshold = null;
        foreach ([100, 90] as $threshold) {
            if ($percentBefore < $threshold && $percentAfter >= $threshold) {
                $crossedThreshold = $threshold;
                break;
            }
        }

        if ($crossedThreshold === null) {
            return;
        }

        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType(Notification::TYPE_BUDGET_ALERT);
        $notification->setMessage(sprintf(
            'Votre budget %s a atteint %d%% (%.2f / %.2f %s).',
            $budget->getPeriod() === Budget::PERIOD_MONTHLY ? 'mensuel' : 'annuel',
            $crossedThreshold,
            $spentAfter,
            $amount,
            $budget->getCurrency()
        ));
        $notification->setData(['budgetId' => $budget->getId()]);
        $this->em->persist($notification);
        $this->em->flush();
    }

    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Expense $expense): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $expense);

        if ($expense->getStatus() !== Expense::STATUS_DRAFT) {
            return $this->json(['message' => 'Seules les dépenses en brouillon peuvent être supprimées.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($expense);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/receipts', name: 'receipt_upload', methods: ['POST'])]
    public function uploadReceipt(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $expense);

        if ($expense->getStatus() !== Expense::STATUS_DRAFT) {
            return $this->json(['message' => 'Les justificatifs ne peuvent être ajoutés que sur une dépense en brouillon.'], Response::HTTP_FORBIDDEN);
        }

        $uploadedFile = $request->files->get('receipt');
        if (!$uploadedFile instanceof UploadedFile || !$uploadedFile->isValid()) {
            return $this->json(['message' => 'Fichier invalide.'], Response::HTTP_BAD_REQUEST);
        }

        if (!in_array($uploadedFile->getMimeType(), self::ALLOWED_RECEIPT_MIME_TYPES, true)) {
            return $this->json(['message' => 'Format non supporté (JPEG, PNG, WEBP ou PDF uniquement).'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($uploadedFile->getSize() > self::MAX_RECEIPT_SIZE) {
            return $this->json(['message' => 'Le fichier dépasse la taille maximale de 10 Mo.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $receipt = new ExpenseReceipt();
        $receipt->setExpense($expense);
        $receipt->setFile($uploadedFile);

        $this->em->persist($receipt);
        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($this->withReceiptUrls($expense), 'json', ['groups' => ['expense:read']])),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}/receipts/{receiptId}', name: 'receipt_delete', methods: ['DELETE'])]
    public function deleteReceipt(Expense $expense, int $receiptId): JsonResponse
    {
        $this->denyAccessUnlessGranted('edit', $expense);

        if ($expense->getStatus() !== Expense::STATUS_DRAFT) {
            return $this->json(['message' => 'Les justificatifs ne peuvent être supprimés que sur une dépense en brouillon.'], Response::HTTP_FORBIDDEN);
        }

        $receipt = $this->expenseReceiptRepository->findOneBy(['id' => $receiptId, 'expense' => $expense]);
        if (!$receipt) {
            return $this->json(['message' => 'Justificatif introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($receipt);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
