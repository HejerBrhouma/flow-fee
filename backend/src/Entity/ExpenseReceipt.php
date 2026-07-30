<?php

namespace App\Entity;

use App\Repository\ExpenseReceiptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ExpenseReceiptRepository::class)]
#[ORM\Table(name: 'expense_receipts')]
#[ORM\HasLifecycleCallbacks]
class ExpenseReceipt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['expense:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'receipts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Expense $expense = null;

    #[ORM\Column(length: 255)]
    #[Groups(['expense:read'])]
    private ?string $filePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['expense:read'])]
    private ?string $originalName = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['expense:read'])]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['expense:read'])]
    private ?int $fileSize = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['expense:read'])]
    private ?\DateTimeInterface $uploadedAt = null;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->uploadedAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }
    public function getExpense(): ?Expense { return $this->expense; }
    public function setExpense(?Expense $expense): static { $this->expense = $expense; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(string $filePath): static { $this->filePath = $filePath; return $this; }
    public function getOriginalName(): ?string { return $this->originalName; }
    public function setOriginalName(?string $name): static { $this->originalName = $name; return $this; }
    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): static { $this->mimeType = $mimeType; return $this; }
    public function getFileSize(): ?int { return $this->fileSize; }
    public function setFileSize(?int $size): static { $this->fileSize = $size; return $this; }
    public function getUploadedAt(): ?\DateTimeInterface { return $this->uploadedAt; }
}
