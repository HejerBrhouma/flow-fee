<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\ExpenseRepository;
use App\Repository\UserCompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/categories', name: 'api_category_')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CategoryRepository $categoryRepository,
        private readonly ExpenseRepository $expenseRepository,
        private readonly UserCompanyRepository $userCompanyRepository,
    ) {}

    /**
     * Categories available to the current user: the fixed global set (company = null,
     * user = null), their own company's categories if any, and their own personal ones.
     * Each entry carries a computed "editable" flag instead of exposing the raw
     * company/user relations — global categories are read-only for everyone.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $qb = $this->categoryRepository->createQueryBuilder('c')
            ->where('c.company IS NULL AND c.user IS NULL')
            ->orWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.name', 'ASC');

        $membership = $this->userCompanyRepository->findOneBy(['user' => $user]);
        if ($membership) {
            $qb->orWhere('c.company = :company')
                ->setParameter('company', $membership->getCompany());
        }

        $categories = array_map(
            fn (Category $c) => $this->serializeCategory($c, $user),
            $qb->getQuery()->getResult()
        );

        return $this->json($categories);
    }

    /**
     * New categories are scoped to the user's company (shared with the whole team) if
     * they're part of one, otherwise to the user alone — mirroring how a particulier
     * account has no team to share with.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        $name = trim($data['name'] ?? '');
        if ($name === '') {
            return $this->json(['message' => 'Le nom de la catégorie est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category = new Category();
        $category->setName($name);
        $category->setIcon($data['icon'] ?? null);
        $category->setColor($data['color'] ?? null);

        $membership = $this->userCompanyRepository->findOneBy(['user' => $user]);
        if ($membership) {
            $category->setCompany($membership->getCompany());
        } else {
            $category->setUser($user);
        }

        $this->em->persist($category);
        $this->em->flush();

        return $this->json($this->serializeCategory($category, $user), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'update', methods: ['PATCH'])]
    public function update(Category $category, Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->canEdit($category, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas modifier cette catégorie.');
        }

        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if ($name === '') {
                return $this->json(['message' => 'Le nom de la catégorie est obligatoire.'], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $category->setName($name);
        }
        if (array_key_exists('icon', $data)) {
            $category->setIcon($data['icon']);
        }
        if (array_key_exists('color', $data)) {
            $category->setColor($data['color']);
        }

        $this->em->flush();

        return $this->json($this->serializeCategory($category, $user));
    }

    /**
     * Expenses already using this category keep existing (just uncategorized) rather than
     * being blocked or cascaded — the category column is nullable specifically for this.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Category $category): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$this->canEdit($category, $user)) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas supprimer cette catégorie.');
        }

        foreach ($this->expenseRepository->findBy(['category' => $category]) as $expense) {
            $expense->setCategory(null);
        }

        $this->em->remove($category);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    private function canEdit(Category $category, User $user): bool
    {
        if ($category->getUser() !== null) {
            return $category->getUser()->getId() === $user->getId();
        }

        if ($category->getCompany() !== null) {
            return $this->userCompanyRepository->findOneBy([
                'user' => $user,
                'company' => $category->getCompany(),
            ]) !== null;
        }

        return false;
    }

    private function serializeCategory(Category $category, User $user): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'icon' => $category->getIcon(),
            'color' => $category->getColor(),
            'editable' => $this->canEdit($category, $user),
        ];
    }
}
