<?php

namespace App\Entity;

use App\Repository\CategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategoryRepository::class)]
#[ORM\Table(name: 'categories')]
class Category
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['category:read', 'expense:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['category:read', 'expense:read'])]
    private ?string $name = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['category:read', 'expense:read'])]
    private ?string $icon = null;

    #[ORM\Column(length: 7, nullable: true)]
    #[Groups(['category:read', 'expense:read'])]
    private ?string $color = null;

    // null = global category, set = company-specific (shared with the whole team)
    #[ORM\ManyToOne]
    private ?Company $company = null;

    // Personal category, only visible to and editable by this one user (e.g. a particulier
    // account's own custom category). Mutually exclusive with $company in practice — a
    // category is either global, company-wide, or one person's own, never more than one.
    // Deliberately not in a serialization group — the API exposes ownership via a computed
    // "editable" flag (see CategoryController) instead of the raw user relation, to avoid
    // leaking other users' account data through a shared list endpoint.
    #[ORM\ManyToOne]
    private ?User $user = null;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: Expense::class)]
    private Collection $expenses;

    public function __construct()
    {
        $this->expenses = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getIcon(): ?string { return $this->icon; }
    public function setIcon(?string $icon): static { $this->icon = $icon; return $this; }
    public function getColor(): ?string { return $this->color; }
    public function setColor(?string $color): static { $this->color = $color; return $this; }
    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(?Company $company): static { $this->company = $company; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getExpenses(): Collection { return $this->expenses; }
}
