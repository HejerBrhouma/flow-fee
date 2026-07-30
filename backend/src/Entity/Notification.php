<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    public const TYPE_EXPENSE_SUBMITTED = 'expense_submitted';
    public const TYPE_EXPENSE_APPROVED = 'expense_approved';
    public const TYPE_EXPENSE_REJECTED = 'expense_rejected';
    public const TYPE_BUDGET_ALERT = 'budget_alert';
    public const TYPE_TEAM_INVITE = 'team_invite';
    public const TYPE_SAVINGS_GOAL_REACHED = 'savings_goal_reached';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['notification:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    #[Groups(['notification:read'])]
    private ?string $type = null;

    #[ORM\Column(length: 255)]
    #[Groups(['notification:read'])]
    private ?string $message = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['notification:read'])]
    private ?array $data = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['notification:read'])]
    private bool $isRead = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['notification:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }
    public function getData(): ?array { return $this->data; }
    public function setData(?array $data): static { $this->data = $data; return $this; }
    public function isRead(): bool { return $this->isRead; }
    public function setIsRead(bool $isRead): static { $this->isRead = $isRead; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
}
