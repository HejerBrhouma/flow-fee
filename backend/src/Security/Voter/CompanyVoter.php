<?php

namespace App\Security\Voter;

use App\Entity\Company;
use App\Entity\User;
use App\Entity\UserCompany;
use App\Repository\UserCompanyRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class CompanyVoter extends Voter
{
    public const MEMBER = 'MEMBER';
    public const ADMIN = 'ADMIN';

    public function __construct(
        private readonly UserCompanyRepository $userCompanyRepository,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Company && in_array($attribute, [self::MEMBER, self::ADMIN], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Company $company */
        $company = $subject;
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        $membership = $this->userCompanyRepository->findOneBy(['user' => $user, 'company' => $company]);
        if (!$membership) {
            return false;
        }

        return match ($attribute) {
            self::MEMBER => true,
            self::ADMIN => $membership->getRole() === UserCompany::ROLE_ADMIN,
            default => false,
        };
    }
}
