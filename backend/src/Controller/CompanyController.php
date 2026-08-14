<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\Department;
use App\Entity\Notification;
use App\Entity\User;
use App\Entity\UserCompany;
use App\Repository\CompanyRepository;
use App\Repository\UserCompanyRepository;
use App\Repository\UserRepository;
use App\Security\Voter\CompanyVoter;
use App\Service\AddressVerificationService;
use App\Service\NotificationService;
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
        private readonly AddressVerificationService $addressVerification,
        private readonly NotificationService $notificationService,
    ) {}

    #[Route('/verify-address', name: 'verify_address', methods: ['GET'])]
    public function verifyAddress(Request $request): JsonResponse
    {
        $country = $request->query->get('country', '');
        $city = $request->query->get('city', '');
        $zipCode = $request->query->get('zipCode', '');

        $valid = $this->addressVerification->exists($country, $city, $zipCode);

        return $this->json(['valid' => $valid]);
    }

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

    // Which identification number each country requires, and its expected format.
    // Countries not listed here fall back to accepting either field, unvalidated — we can't
    // realistically hardcode every country's business registry format.
    private const TAX_ID_RULES = [
        'France' => ['field' => 'siret', 'pattern' => '/^\d{14}$/', 'message' => 'Le SIRET doit contenir exactement 14 chiffres.'],
        'Tunisie' => ['field' => 'taxId', 'pattern' => '/^\d{7}[A-Z]{3}\d{3}$/', 'message' => 'Le matricule fiscal doit être au format 1234567AAM000 (7 chiffres, 3 lettres, 3 chiffres).'],
    ];

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // One company per user keeps a company's admin membership unambiguous — an existing
        // member creating a second company would otherwise silently become an admin of two.
        if ($this->userCompanyRepository->findOneBy(['user' => $user])) {
            return $this->json(['errors' => ['name' => 'Vous appartenez déjà à une entreprise.']], Response::HTTP_CONFLICT);
        }

        $data = json_decode($request->getContent(), true);

        $company = new Company();
        $company->setName($data['name'] ?? '');
        $company->setSiret($this->normalizeIdentifier($data['siret'] ?? null));
        $company->setTaxId($this->normalizeIdentifier($data['taxId'] ?? null));
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

        if ($identificationError = $this->validateIdentification($company)) {
            return $this->json(['errors' => $identificationError], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($duplicateError = $this->findDuplicateIdentification($company)) {
            return $this->json(['errors' => $duplicateError], Response::HTTP_CONFLICT);
        }

        if ($company->getCity() && $company->getZipCode()) {
            $addressValid = $this->addressVerification->exists($company->getCountry(), $company->getCity(), $company->getZipCode());
            // null means the lookup service was unreachable — don't block company creation on it.
            if ($addressValid === false) {
                return $this->json([
                    'errors' => ['zipCode' => "Ce code postal ne correspond pas à la ville indiquée pour ce pays."],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
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

        $this->notificationService->notify(
            $invitedUser,
            Notification::TYPE_TEAM_INVITE,
            sprintf('Vous avez rejoint l\'entreprise "%s".', $company->getName()),
            ['companyId' => $company->getId()],
        );

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

    private function normalizeIdentifier(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $normalized = strtoupper(str_replace(['/', ' ', '-'], '', trim($value)));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * @return array<string, string>|null a property-path-keyed error, or null if valid
     */
    private function validateIdentification(Company $company): ?array
    {
        $rule = self::TAX_ID_RULES[$company->getCountry()] ?? null;
        if (!$rule) {
            return null;
        }

        $getter = 'get' . ucfirst($rule['field']);
        $value = $company->$getter();

        if (!$value) {
            return [$rule['field'] => sprintf(
                '%s est requis pour une entreprise enregistrée en %s.',
                $rule['field'] === 'siret' ? 'Le SIRET' : 'Le matricule fiscal',
                $company->getCountry()
            )];
        }

        if (!preg_match($rule['pattern'], $value)) {
            return [$rule['field'] => $rule['message']];
        }

        return null;
    }

    /**
     * @return array<string, string>|null a property-path-keyed error, or null if no conflict
     */
    private function findDuplicateIdentification(Company $company): ?array
    {
        if ($company->getSiret() && $this->companyRepository->findOneBy(['siret' => $company->getSiret()])) {
            return ['siret' => 'Ce SIRET est déjà enregistré par une autre entreprise.'];
        }

        if ($company->getTaxId() && $this->companyRepository->findOneBy(['taxId' => $company->getTaxId()])) {
            return ['taxId' => 'Ce matricule fiscal est déjà enregistré par une autre entreprise.'];
        }

        return null;
    }
}
