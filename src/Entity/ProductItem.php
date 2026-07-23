<?php

namespace App\Entity;

use App\Repository\ProductItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductItemRepository::class)]
#[ORM\HasLifecycleCallbacks]
class ProductItem
{
    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_SOLD = 'SOLD';
    public const STATUS_REFUNDED_OK = 'REFUNDED_OK';
    public const STATUS_REFUNDED_DEFECTIVE = 'REFUNDED_DEFECTIVE';
    public const STATUS_DAMAGED = 'DAMAGED';
    public const STATUS_RESERVED = 'RESERVED';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'productItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $serialNumber = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_AVAILABLE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: PurchaseItem::class, inversedBy: 'productItems')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PurchaseItem $purchaseItem = null;

    #[ORM\ManyToOne(targetEntity: SaleItem::class, inversedBy: 'productItems')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?SaleItem $saleItem = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;
        return $this;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): static
    {
        $this->serialNumber = $serialNumber;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getPurchaseItem(): ?PurchaseItem
    {
        return $this->purchaseItem;
    }

    public function setPurchaseItem(?PurchaseItem $purchaseItem): static
    {
        $this->purchaseItem = $purchaseItem;
        return $this;
    }

    public function getSaleItem(): ?SaleItem
    {
        return $this->saleItem;
    }

    public function setSaleItem(?SaleItem $saleItem): static
    {
        $this->saleItem = $saleItem;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
