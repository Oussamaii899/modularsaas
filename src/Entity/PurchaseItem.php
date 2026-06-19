<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\PurchaseItemRepository;

#[ORM\Entity(repositoryClass: PurchaseItemRepository::class)]
class PurchaseItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Purchase::class, inversedBy: 'purchaseItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Purchase $purchase = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $contact = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactLogo = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pLogo = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pSku = null;

    #[ORM\Column(length: 20, options: ["default" => "Active"])]
    private string $status = 'Active';

    public function getId(): ?int { return $this->id; }

    public function getPurchase(): ?Purchase { return $this->purchase; }
    public function setPurchase(?Purchase $purchase): static { $this->purchase = $purchase; return $this; }

    public function getContact(): ?Contact { return $this->contact; }
    public function setContact(?Contact $contact): static { $this->contact = $contact; return $this; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): static { $this->product = $product; return $this; }

    public function getQuantity(): ?int { return $this->quantity; }
    public function setQuantity(int $quantity): static { $this->quantity = $quantity; return $this; }

    public function getPrice(): ?string { return $this->price; }
    public function setPrice(string $price): static { $this->price = $price; return $this; }

    public function getContactName(): ?string { return $this->contactName; }
    public function setContactName(?string $contactName): static { $this->contactName = $contactName; return $this; }

    public function getContactLogo(): ?string { return $this->contactLogo; }
    public function setContactLogo(?string $contactLogo): static { $this->contactLogo = $contactLogo; return $this; }

    public function getContactPhone(): ?string { return $this->contactPhone; }
    public function setContactPhone(?string $contactPhone): static { $this->contactPhone = $contactPhone; return $this; }

    public function getContactEmail(): ?string { return $this->contactEmail; }
    public function setContactEmail(?string $contactEmail): static { $this->contactEmail = $contactEmail; return $this; }

    public function getPName(): ?string { return $this->pName; }
    public function setPName(?string $pName): static { $this->pName = $pName; return $this; }

    public function getPLogo(): ?string { return $this->pLogo; }
    public function setPLogo(?string $pLogo): static { $this->pLogo = $pLogo; return $this; }

    public function getPSku(): ?string { return $this->pSku; }
    public function setPSku(?string $pSku): static { $this->pSku = $pSku; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
}
