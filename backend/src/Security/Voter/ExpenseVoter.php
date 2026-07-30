<?php

namespace App\Security\Voter;

use App\Entity\Expense;
use App\Entity\User;
use App\Entity\UserCompany;
use App\Repository\UserCompanyRepository;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ExpenseVoter extends Voter
{
    public const VIEW = 'view';
    public const EDIT = 'edit';
    public const REVIEW = 'review';

    public function __construct(
        private readonly UserCompanyRepository $userCompanyRepository,
    ) {}

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Expense && in_array($attribute, [self::VIEW, self::EDIT, self::REVIEW], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        /** @var Expense $expense */
        $expense = $subject;
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // The owner can always view/edit their own expense, but cannot review it.
        if ($expense->getUser()?->getId() === $user->getId()) {
            return $attribute !== self::REVIEW;
        }

        if ($attribute === self::EDIT) {
            return false;
        }

        // Non-owners only get access through company membership (personal expenses have no department).
        $department = $expense->getDepartment();
        if (!$department) {
            return false;
        }

        $membership = $this->userCompanyRepository->findOneBy([
            'user' => $user,
            'company' => $department->getCompany(),
        ]);

        if (!$membership) {
            return false;
        }

        return in_array($membership->getRole(), [UserCompany::ROLE_ADMIN, UserCompany::ROLE_MANAGER], true);
    }
}
