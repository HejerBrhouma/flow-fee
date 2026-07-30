<?php

namespace App\Entity;

use App\Repository\UserCompanyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: UserCompanyRepository::class)]
#[ORM\Table(name: 'user_companies')]
#[ORM\UniqueConstraint(columns: ['user_id', 'company_id'])]
#[ORM\HasLifecycleCallbacks]
class UserCompany
{
    public const ROLE_ADMIN = 'ROLE_COMPANY_ADMIN';
    public const ROLE_MANAGER = 'ROLE_MANAGER';
    public const ROLE_EMPLOYEE = 'ROLE_EMPLOYEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'userCompanies')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['team:read', 'company:read'])]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'userCompanies')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['user:read'])]
    private ?Company $company = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[Groups(['team:read', 'user:read'])]
    private ?Department $department = null;

    #[ORM\Column(length: 50)]
    #[Groups(['team:read', 'user:read', 'company:read'])]
    private string $role = self::ROLE_EMPLOYEE;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['team:read'])]
    private ?\DateTimeInterface $joinedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->joinedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getCompany(): ?Company { return $this->company; }
    public function setCompany(?Company $company): static { $this->company = $company; return $this; }
    public function getDepartment(): ?Department { return $this->department; }
    public function setDepartment(?Department $department): static { $this->department = $department; return $this; }
    public function getRole(): string { return $this->role; }
    public function setRole(string $role): static { $this->role = $role; return $this; }
    public function getJoinedAt(): ?\DateTimeInterface { return $this->joinedAt; }
}
