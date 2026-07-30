<?php

namespace App\Entity;

use App\Repository\SavingsGoalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SavingsGoalRepository::class)]
#[ORM\Table(name: 'savings_goals')]
#[ORM\HasLifecycleCallbacks]
class SavingsGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['savings_goal:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['savings_goal:read'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    #[Groups(['savings_goal:read'])]
    private ?string $targetAmount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Groups(['savings_goal:read'])]
    private string $currentAmount = '0.00';

    #[ORM\Column(length: 3, options: ['default' => 'EUR'])]
    #[Groups(['savings_goal:read'])]
    private string $currency = 'EUR';

    // Optional target date for the purchase (house, car, ...).
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['savings_goal:read'])]
    private ?\DateTimeInterface $targetDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['savings_goal:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $updatedAt = null;

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
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static { $this->name = $name; return $this; }
    public function getTargetAmount(): ?string { return $this->targetAmount; }
    public function setTargetAmount(string $amount): static { $this->targetAmount = $amount; return $this; }
    public function getCurrentAmount(): string { return $this->currentAmount; }
    public function setCurrentAmount(string $amount): static { $this->currentAmount = $amount; return $this; }
    public function getCurrency(): string { return $this->currency; }
    public function setCurrency(string $currency): static { $this->currency = $currency; return $this; }
    public function getTargetDate(): ?\DateTimeInterface { return $this->targetDate; }
    public function setTargetDate(?\DateTimeInterface $date): static { $this->targetDate = $date; return $this; }
    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
