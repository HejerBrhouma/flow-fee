<?php

namespace App\Entity;

use App\Repository\DeviceTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DeviceTokenRepository::class)]
#[ORM\Table(name: 'device_tokens')]
#[ORM\HasLifecycleCallbacks]
class DeviceToken
{
    public const PLATFORM_IOS = 'ios';
    public const PLATFORM_ANDROID = 'android';
    public const PLATFORM_WEB = 'web';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'deviceTokens')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    // Unique so re-registering the same device (e.g. app reinstall under a different account)
    // just reassigns it via upsert rather than creating a duplicate row that would keep
    // pushing notifications to a stale user.
    #[ORM\Column(length: 255, unique: true)]
    private ?string $token = null;

    #[ORM\Column(length: 10)]
    private string $platform = self::PLATFORM_ANDROID;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getToken(): ?string { return $this->token; }
    public function setToken(string $token): static { $this->token = $token; return $this; }
    public function getPlatform(): string { return $this->platform; }
    public function setPlatform(string $platform): static { $this->platform = $platform; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
