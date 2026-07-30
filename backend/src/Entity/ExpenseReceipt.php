<?php

namespace App\Entity;

use App\Repository\ExpenseReceiptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Serializer\Annotation\SerializedName;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: ExpenseReceiptRepository::class)]
#[ORM\Table(name: 'expense_receipts')]
#[ORM\HasLifecycleCallbacks]
#[Vich\Uploadable]
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

    // Vich manages this internally (bare generated filename); never exposed directly.
    #[Vich\UploadableField(mapping: 'expense_receipts', fileNameProperty: 'filePath', size: 'fileSize', mimeType: 'mimeType', originalName: 'originalName')]
    private ?File $file = null;

    #[ORM\Column(length: 255)]
    private ?string $filePath = null;

    // Populated by ExpenseController before serialization (resolved absolute download URL).
    #[Groups(['expense:read'])]
    #[SerializedName('filePath')]
    private ?string $downloadUrl = null;

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
    public function getFile(): ?File { return $this->file; }
    public function setFile(?File $file): static { $this->file = $file; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $filePath): static { $this->filePath = $filePath; return $this; }
    public function getDownloadUrl(): ?string { return $this->downloadUrl; }
    public function setDownloadUrl(?string $url): static { $this->downloadUrl = $url; return $this; }
    public function getOriginalName(): ?string { return $this->originalName; }
    public function setOriginalName(?string $name): static { $this->originalName = $name; return $this; }
    public function getMimeType(): ?string { return $this->mimeType; }
    public function setMimeType(?string $mimeType): static { $this->mimeType = $mimeType; return $this; }
    public function getFileSize(): ?int { return $this->fileSize; }
    public function setFileSize(?int $size): static { $this->fileSize = $size; return $this; }
    public function getUploadedAt(): ?\DateTimeInterface { return $this->uploadedAt; }
}
