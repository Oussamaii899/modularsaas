<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\ProductRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;

#[ORM\Entity(repositoryClass: ProductRepository::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $price = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $purchasePrice = null;

    #[ORM\Column]
    private int $stockQuantity = 0;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    private ?string $sku = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(options: ["default" => false])]
    private bool $isSerialized = false;

    /**
     * @var Collection<int, ProductScreen>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductScreen::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $screens;

    /**
     * @var Collection<int, ProductItem>
     */
    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $productItems;

    public function __construct()
    {
        $this->screens = new ArrayCollection();
        $this->productItems = new ArrayCollection();
        $this->stockQuantity = 0;
    }

    public function getId(): ?int { return $this->id; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): static 
    { 
        $this->name = $name; 
        if (!$this->slug) {
            $this->slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
        }
        return $this; 
    }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getPrice(): ?string { return $this->price; }
    public function setPrice(string $price): static { $this->price = $price; return $this; }

    public function getPurchasePrice(): ?string { return $this->purchasePrice; }
    public function setPurchasePrice(?string $purchasePrice): static { $this->purchasePrice = $purchasePrice; return $this; }

    public function isSerialized(): bool { return $this->isSerialized; }
    public function setIsSerialized(bool $isSerialized): static { $this->isSerialized = $isSerialized; return $this; }

    public function getStockQuantity(): int 
    { 
        if ($this->isSerialized) {
            $availableCount = 0;
            foreach ($this->productItems as $item) {
                if (in_array($item->getStatus(), [ProductItem::STATUS_AVAILABLE, ProductItem::STATUS_REFUNDED_OK], true)) {
                    $availableCount++;
                }
            }
            return $availableCount;
        }
        return $this->stockQuantity ?? 0; 
    }
    public function setStockQuantity(?int $stockQuantity): static { $this->stockQuantity = $stockQuantity ?? 0; return $this; }

    public function getSku(): ?string { return $this->sku; }
    public function setSku(?string $sku): static { $this->sku = $sku; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getImage(): ?string { return $this->image; }
    public function setImage(?string $image): static { $this->image = $image; return $this; }

    /**
     * @return Collection<int, ProductScreen>
     */
    public function getScreens(): Collection
    {
        return $this->screens;
    }

    public function addScreen(ProductScreen $screen): static
    {
        if (!$this->screens->contains($screen)) {
            $this->screens->add($screen);
            $screen->setProduct($this);
        }
        return $this;
    }

    public function removeScreen(ProductScreen $screen): static
    {
        if ($this->screens->removeElement($screen)) {
            // set the owning side to null (unless already changed)
            if ($screen->getProduct() === $this) {
                $screen->setProduct(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ProductItem>
     */
    public function getProductItems(): Collection
    {
        return $this->productItems;
    }

    public function addProductItem(ProductItem $productItem): static
    {
        if (!$this->productItems->contains($productItem)) {
            $this->productItems->add($productItem);
            $productItem->setProduct($this);
        }
        return $this;
    }

    public function removeProductItem(ProductItem $productItem): static
    {
        if ($this->productItems->removeElement($productItem)) {
            if ($productItem->getProduct() === $this) {
                $productItem->setProduct(null);
            }
        }
        return $this;
    }
}
