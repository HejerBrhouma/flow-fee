<?php

namespace App\Entity;

use App\Repository\ExpenseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ExpenseRepository::class)]
#[ORM\Table(name: 'expenses')]
#[ORM\HasLifecycleCallbacks]
class Expense
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['expense:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['expense:read'])]
    private ?string $title = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['expense:read'])]
    private ?string $amount = null;

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    #[Groups(['expense:read'])]
    private string $currency = 'EUR';

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank]
    #[Groups(['expense:read'])]
    private ?\DateTimeInterface $expenseDate = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['expense:read'])]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Groups(['expense:read'])]
    private string $status = self::STATUS_DRAFT;

    #[ORM\ManyToOne(inversedBy: 'expenses')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['expense:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'expenses')]
    #[Groups(['expense:read'])]
    private ?Category $category = null;

    // For professional expenses
    #[ORM\ManyToOne(inversedBy: 'expenses')]
    #[Groups(['expense:read'])]
    private ?Department $department = null;

    #[ORM\ManyToOne]
    #[Groups(['expense:read'])]
    private ?User $reviewedBy = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['expense:read'])]
    private ?string $reviewComment = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['expense:read'])]
    private ?\DateTimeInterface $reviewedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['expense:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\OneToMany(mappedBy: 'expense', targetEntity: ExpenseReceipt::class, orphanRemoval: true, cascade: ['persist'])]
    #[Groups(['expense:read'])]
    private Collection $receipts;

    public function __construct()
    {
        $this->receipts = new ArrayCollection();
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
    public function getTitle(): ?string { return $this->title; }
    public function setTitle(string $title): static { $this->title = $title; return $this; }
    public function getAmount(): ?string { return $this->amount; }
    public function setAmount(string $amount): static { $this->amount = $amount; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }
    public function getExpenseDate(): ?\DateTimeInterface { return $this->expenseDate; }
    public function setExpenseDate(\DateTimeInterface $date): static { $this->expenseDate = $date; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): static { $this->category = $category; return $this; }
    public function getDepartment(): ?Department { return $this->department; }
    public function setDepartment(?Department $department): static { $this->department = $department; return $this; }
    public function getReviewedBy(): ?User { return $this->reviewedBy; }
    public function setReviewedBy(?User $user): static { $this->reviewedBy = $user; return $this; }
    public function getReviewComment(): ?string { return $this->reviewComment; }
    public function setReviewComment(?string $comment): static { $this->reviewComment = $comment; return $this; }
    public function getReviewedAt(): ?\DateTimeInterface { return $this->reviewedAt; }
    public function setReviewedAt(?\DateTimeInterface $date): static { $this->reviewedAt = $date; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
    public function getReceipts(): Collection { return $this->receipts; }
}
