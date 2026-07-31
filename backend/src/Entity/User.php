<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
#[Vich\Uploadable]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const TYPE_PERSONAL = 'personal';
    public const TYPE_PROFESSIONAL = 'professional';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['user:read', 'expense:read', 'team:read', 'budget:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Groups(['user:read', 'expense:read', 'budget:read', 'team:read'])]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['user:read', 'expense:read', 'team:read', 'budget:read'])]
    private ?string $firstName = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['user:read', 'expense:read', 'team:read', 'budget:read'])]
    private ?string $lastName = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 20)]
    #[Groups(['user:read'])]
    private string $type = self::TYPE_PERSONAL;

    // External avatar URL from an OAuth provider (Google/Facebook). Superseded by a locally
    // uploaded avatarFile when both are set — see AuthController::withAvatarUrl().
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[Vich\UploadableField(mapping: 'user_avatars', fileNameProperty: 'avatarPath', size: 'avatarFileSize')]
    private ?File $avatarFile = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatarPath = null;

    #[ORM\Column(nullable: true)]
    private ?int $avatarFileSize = null;

    // Populated by AuthController before serialization (resolved absolute URL, whichever
    // avatar source applies).
    #[Groups(['user:read'])]
    private ?string $avatarUrl = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $phone = null;

    #[ORM\Column(length: 3, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $preferredCurrency = null;

    // OAuth
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facebookId = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emailVerificationToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $passwordResetTokenExpiresAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

    // Relations
    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Expense::class, orphanRemoval: true)]
    private Collection $expenses;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: UserCompany::class, orphanRemoval: true)]
    private Collection $userCompanies;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    public function __construct()
    {
        $this->expenses = new ArrayCollection();
        $this->userCompanies = new ArrayCollection();
        $this->notifications = new ArrayCollection();
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

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getUserIdentifier(): string { return (string) $this->email; }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }
    public function setRoles(array $roles): static { $this->roles = $roles; return $this; }

    public function getPassword(): ?string { return $this->password; }
    public function setPassword(?string $password): static { $this->password = $password; return $this; }

    public function eraseCredentials(): void {}

    public function getFirstName(): ?string { return $this->firstName; }
    public function setFirstName(string $firstName): static { $this->firstName = $firstName; return $this; }

    public function getLastName(): ?string { return $this->lastName; }
    public function setLastName(string $lastName): static { $this->lastName = $lastName; return $this; }

    #[Groups(['user:read'])]
    public function getFullName(): string { return $this->firstName . ' ' . $this->lastName; }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getAvatar(): ?string { return $this->avatar; }
    public function setAvatar(?string $avatar): static { $this->avatar = $avatar; return $this; }

    public function getAvatarFile(): ?File { return $this->avatarFile; }
    public function setAvatarFile(?File $file): static { $this->avatarFile = $file; return $this; }

    public function getAvatarPath(): ?string { return $this->avatarPath; }
    public function setAvatarPath(?string $path): static { $this->avatarPath = $path; return $this; }

    public function getAvatarUrl(): ?string { return $this->avatarUrl; }
    public function setAvatarUrl(?string $url): static { $this->avatarUrl = $url; return $this; }

    public function getPhone(): ?string { return $this->phone; }
    public function setPhone(?string $phone): static { $this->phone = $phone; return $this; }

    public function getPreferredCurrency(): ?string { return $this->preferredCurrency; }
    public function setPreferredCurrency(?string $currency): static { $this->preferredCurrency = $currency; return $this; }

    public function getGoogleId(): ?string { return $this->googleId; }
    public function setGoogleId(?string $googleId): static { $this->googleId = $googleId; return $this; }

    public function getFacebookId(): ?string { return $this->facebookId; }
    public function setFacebookId(?string $facebookId): static { $this->facebookId = $facebookId; return $this; }

    public function isVerified(): bool { return $this->isVerified; }
    public function setIsVerified(bool $isVerified): static { $this->isVerified = $isVerified; return $this; }

    public function getEmailVerificationToken(): ?string { return $this->emailVerificationToken; }
    public function setEmailVerificationToken(?string $token): static { $this->emailVerificationToken = $token; return $this; }

    public function getPasswordResetToken(): ?string { return $this->passwordResetToken; }
    public function setPasswordResetToken(?string $token): static { $this->passwordResetToken = $token; return $this; }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeInterface { return $this->passwordResetTokenExpiresAt; }
    public function setPasswordResetTokenExpiresAt(?\DateTimeInterface $date): static { $this->passwordResetTokenExpiresAt = $date; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }

    public function getExpenses(): Collection { return $this->expenses; }
    public function getUserCompanies(): Collection { return $this->userCompanies; }
    public function getNotifications(): Collection { return $this->notifications; }
}
