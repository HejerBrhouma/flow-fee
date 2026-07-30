<?php

namespace App\Controller;

use App\Entity\Expense;
use App\Entity\Notification;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\DepartmentRepository;
use App\Repository\ExpenseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/expenses', name: 'api_expense_')]
class ExpenseController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ExpenseRepository $expenseRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly DepartmentRepository $departmentRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
        private readonly PaginatorInterface $paginator,
    ) {}

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

        return $this->json(
            json_decode($this->serializer->serialize($expense, 'json', ['groups' => ['expense:read']])),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Expense $expense): JsonResponse
    {
        $this->denyAccessUnlessGranted('view', $expense);

        return $this->json(
            json_decode($this->serializer->serialize($expense, 'json', ['groups' => ['expense:read']]))
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

        return $this->json(
            json_decode($this->serializer->serialize($expense, 'json', ['groups' => ['expense:read']]))
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
            json_decode($this->serializer->serialize($expense, 'json', ['groups' => ['expense:read']]))
        );
    }

    #[Route('/{id}/review', name: 'review', methods: ['POST'])]
    public function review(Expense $expense, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_MANAGER');

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

        return $this->json(
            json_decode($this->serializer->serialize($expense, 'json', ['groups' => ['expense:read']]))
        );
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
}
