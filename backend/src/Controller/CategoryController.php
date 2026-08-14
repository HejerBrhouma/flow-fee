<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\UserCompanyRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/categories', name: 'api_category_')]
class CategoryController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly UserCompanyRepository $userCompanyRepository,
        private readonly SerializerInterface $serializer,
    ) {}

    /**
     * Categories available to the current user: the fixed global set (company = null) plus
     * their own company's categories, if any. Personal-account users just get the global set.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $qb = $this->categoryRepository->createQueryBuilder('c')
            ->where('c.company IS NULL')
            ->orderBy('c.name', 'ASC');

        $membership = $this->userCompanyRepository->findOneBy(['user' => $user]);
        if ($membership) {
            $qb->orWhere('c.company = :company')
                ->setParameter('company', $membership->getCompany());
        }

        return $this->json(
            json_decode($this->serializer->serialize($qb->getQuery()->getResult(), 'json', ['groups' => ['category:read']]), true)
        );
    }
}
