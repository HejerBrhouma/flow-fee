<?php

namespace App\Entity;

use App\Repository\DepartmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DepartmentRepository::class)]
#[ORM\Table(name: 'departments')]
#[ORM\HasLifecycleCallbacks]
class Department
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['department:read', 'expense:read', 'company:read', 'budget:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['department:read', 'expense:read', 'company:read', 'budget:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['department:read'])]
    private ?string $description = null;

    #[ORM\ManyToOne(inversedBy: 'departments')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['department:read'])]
    private ?Company $company = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['department:read'])]
    private ?string $monthlyBudget = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['department:read'])]
    private ?string $yearlyBudget = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'department', targetEntity: UserCompany::class)]
    private Collection $members;

    #[ORM\OneToMany(mappedBy: 'department', targetEntity: Expense::class)]
    private Collection $expenses;

    #[ORM\OneToMany(mappedBy: 'department', targetEntity: Budget::class, orphanRemoval: true)]
    private Collection $budgets;

    public function __construct()
    {
        $this->members = new ArrayCollection();
        $this->expenses = new ArrayCollection();
        $this->budgets = new ArrayCollection();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(?Company $company): static { $this->company = $company; return $this; }
    public function getMonthlyBudget(): ?string { return $this->monthlyBudget; }
    public function setMonthlyBudget(?string $budget): static { $this->monthlyBudget = $budget; return $this; }
    public function getYearlyBudget(): ?string { return $this->yearlyBudget; }
    public function setYearlyBudget(?string $budget): static { $this->yearlyBudget = $budget; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getMembers(): Collection { return $this->members; }
    public function getExpenses(): Collection { return $this->expenses; }
    public function getBudgets(): Collection { return $this->budgets; }
}
