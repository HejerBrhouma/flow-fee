<?php

namespace App\Entity;

use App\Repository\BudgetRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BudgetRepository::class)]
#[ORM\Table(name: 'budgets')]
#[ORM\UniqueConstraint(name: 'uniq_budget_department_period', columns: ['department_id', 'year', 'month'])]
#[ORM\UniqueConstraint(name: 'uniq_budget_user_period', columns: ['user_id', 'year', 'month'])]
class Budget
{
    public const PERIOD_MONTHLY = 'monthly';
    public const PERIOD_YEARLY = 'yearly';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['budget:read'])]
    private ?int $id = null;

    // Set for a company department budget; null for a personal budget.
    #[ORM\ManyToOne(inversedBy: 'budgets')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['budget:read'])]
    private ?Department $department = null;

    // Set for a personal budget; null for a company department budget.
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['budget:read'])]
    private ?User $user = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['budget:read'])]
    private ?string $amount = null;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    #[Groups(['budget:read'])]
    private string $currency = 'EUR';

    #[ORM\Column(length: 10)]
    #[Groups(['budget:read'])]
    private string $period = self::PERIOD_MONTHLY;

    #[ORM\Column]
    #[Assert\NotBlank]
    #[Groups(['budget:read'])]
    private ?int $year = null;

    // null for yearly budgets
    #[ORM\Column(nullable: true)]
    #[Groups(['budget:read'])]
    private ?int $month = null;

    public function getId(): ?int { return $this->id; }
    public function getDepartment(): ?Department { return $this->department; }
    public function setDepartment(?Department $department): static { $this->department = $department; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(string $amount): static { $this->amount = $amount; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }
    public function getPeriod(): string { return $this->period; }
    public function setPeriod(string $period): static { $this->period = $period; return $this; }
    public function getYear(): ?int { return $this->year; }
    public function setYear(int $year): static { $this->year = $year; return $this; }
    public function getMonth(): ?int { return $this->month; }
    public function setMonth(?int $month): static { $this->month = $month; return $this; }
}
