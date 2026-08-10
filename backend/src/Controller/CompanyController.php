<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Department;
use App\Entity\User;
use App\Entity\UserCompany;
use App\Repository\CompanyRepository;
use App\Repository\UserCompanyRepository;
use App\Repository\UserRepository;
use App\Security\Voter\CompanyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/companies', name: 'api_company_')]
class CompanyController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CompanyRepository $companyRepository,
        private readonly UserCompanyRepository $userCompanyRepository,
        private readonly UserRepository $userRepository,
        private readonly SerializerInterface $serializer,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Route('/me', name: 'my_membership', methods: ['GET'])]
    public function myMembership(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $membership = $this->userCompanyRepository->findOneBy(['user' => $user]);

        if (!$membership) {
            return $this->json(null);
        }

        return $this->json(
            json_decode($this->serializer->serialize($membership, 'json', ['groups' => ['team:read']]))
        );
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        $company = new Company();
        $company->setName($data['name'] ?? '');
        $company->setSiret($data['siret'] ?? null);
        $company->setAddress($data['address'] ?? null);
        $company->setCity($data['city'] ?? null);
        $company->setZipCode($data['zipCode'] ?? null);
        $company->setCountry($data['country'] ?? 'France');

        $errors = $this->validator->validate($company);
        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json(['errors' => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->em->persist($company);

        // Creator becomes ADMIN
        $userCompany = new UserCompany();
        $userCompany->setUser($user);
        $userCompany->setCompany($company);
        $userCompany->setRole(UserCompany::ROLE_ADMIN);

        $user->setType(User::TYPE_PROFESSIONAL);
        $user->setRoles(['ROLE_COMPANY_ADMIN']);

        $this->em->persist($userCompany);
        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($company, 'json', ['groups' => ['company:read']])),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Company $company): JsonResponse
    {
        $this->denyAccessUnlessGranted(CompanyVoter::MEMBER, $company);

        return $this->json(
            json_decode($this->serializer->serialize($company, 'json', ['groups' => ['company:read']]))
        );
    }

    #[Route('/{id}/team', name: 'team', methods: ['GET'])]
    public function team(Company $company): JsonResponse
    {
        $this->denyAccessUnlessGranted(CompanyVoter::MEMBER, $company);

        return $this->json(
            json_decode($this->serializer->serialize($company->getUserCompanies(), 'json', ['groups' => ['team:read']]))
        );
    }

    #[Route('/{id}/invite', name: 'invite', methods: ['POST'])]
    public function invite(Company $company, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(CompanyVoter::ADMIN, $company);

        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $role = $data['role'] ?? UserCompany::ROLE_EMPLOYEE;

        if (!$email) {
            return $this->json(['message' => 'Email requis.'], Response::HTTP_BAD_REQUEST);
        }

        $invitedUser = $this->userRepository->findOneBy(['email' => $email]);
        if (!$invitedUser) {
            return $this->json(['message' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $userCompany = new UserCompany();
        $userCompany->setUser($invitedUser);
        $userCompany->setCompany($company);
        $userCompany->setRole($role);

        if (!empty($data['departmentId'])) {
            $department = $this->em->find(Department::class, $data['departmentId']);
            $userCompany->setDepartment($department);
        }

        $invitedUser->setType(User::TYPE_PROFESSIONAL);

        $mappedRole = match ($role) {
            UserCompany::ROLE_ADMIN => 'ROLE_COMPANY_ADMIN',
            UserCompany::ROLE_MANAGER => 'ROLE_MANAGER',
            default => null,
        };
        if ($mappedRole !== null) {
            $globalRoles = array_diff($invitedUser->getRoles(), ['ROLE_USER']);
            if (!in_array($mappedRole, $globalRoles, true)) {
                $globalRoles[] = $mappedRole;
            }
            $invitedUser->setRoles(array_values($globalRoles));
        }

        $this->em->persist($userCompany);
        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($userCompany, 'json', ['groups' => ['team:read']])),
            Response::HTTP_CREATED
        );
    }

    #[Route('/{id}/team/{memberId}', name: 'team_remove', methods: ['DELETE'])]
    public function removeMember(Company $company, int $memberId): JsonResponse
    {
        $this->denyAccessUnlessGranted(CompanyVoter::ADMIN, $company);

        /** @var User $currentUser */
        $currentUser = $this->getUser();

        $membership = $this->userCompanyRepository->findOneBy([
            'company' => $company,
            'id' => $memberId,
        ]);

        if (!$membership) {
            return $this->json(['message' => 'Membre introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if ($membership->getUser()->getId() === $currentUser->getId()) {
            return $this->json(['message' => 'Vous ne pouvez pas vous retirer vous-même.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($membership);
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/departments', name: 'departments', methods: ['GET'])]
    public function departments(Company $company): JsonResponse
    {
        $this->denyAccessUnlessGranted(CompanyVoter::MEMBER, $company);

        return $this->json(
            json_decode($this->serializer->serialize($company->getDepartments(), 'json', ['groups' => ['department:read']]))
        );
    }

    #[Route('/{id}/departments', name: 'department_create', methods: ['POST'])]
    public function createDepartment(Company $company, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted(CompanyVoter::ADMIN, $company);

        $data = json_decode($request->getContent(), true);

        $department = new Department();
        $department->setName($data['name'] ?? '');
        $department->setDescription($data['description'] ?? null);
        $department->setCompany($company);
        $department->setMonthlyBudget($data['monthlyBudget'] ?? null);
        $department->setYearlyBudget($data['yearlyBudget'] ?? null);

        $this->em->persist($department);
        $this->em->flush();

        return $this->json(
            json_decode($this->serializer->serialize($department, 'json', ['groups' => ['department:read']])),
            Response::HTTP_CREATED
        );
    }
}
