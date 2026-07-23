<?php

namespace App\Entity;


use App\Repository\SaleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: SaleRepository::class)]
class Sale
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToMany(mappedBy: 'sale', targetEntity: SaleItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $saleItems;

    #[ORM\OneToMany(mappedBy: 'sale', targetEntity: Payment::class, cascade: ['persist', 'remove'])]
    private Collection $payments;

    #[ORM\Column(length: 20, options: ["default" => "Unpaid"])]
    private string $paymentStatus = 'Unpaid';

    #[ORM\Column(length: 255, unique: true)]
    private ?string $slug = null;

    #[ORM\OneToMany(mappedBy: 'sale', targetEntity: PrescriptionItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $prescriptionItems;

    public function __construct()
    {
        $this->saleItems = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->prescriptionItems = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
        $this->slug = 'inv-' . bin2hex(random_bytes(4));
    }

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $contact = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $total = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    public function getId(): ?int { return $this->id; }

    public function getSlug(): ?string { return $this->slug; }
    public function setSlug(string $slug): static { $this->slug = $slug; return $this; }

    public function getContact(): ?Contact { return $this->contact; }
    public function setContact(?Contact $contact): static { $this->contact = $contact; return $this; }

    public function getTotal(): ?string { return $this->total; }
    public function setTotal(string $total): static { $this->total = $total; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    /**
     * @return Collection<int, SaleItem>
     */
    public function getSaleItems(): Collection
    {
        return $this->saleItems;
    }

    public function addSaleItem(SaleItem $saleItem): static
    {
        if (!$this->saleItems->contains($saleItem)) {
            $this->saleItems->add($saleItem);
            $saleItem->setSale($this);
        }

        return $this;
    }

    public function removeSaleItem(SaleItem $saleItem): static
    {
        if ($this->saleItems->removeElement($saleItem)) {
            // set the owning side to null (unless already changed)
            if ($saleItem->getSale() === $this) {
                $saleItem->setSale(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): static
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setSale($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): static
    {
        if ($this->payments->removeElement($payment)) {
            // set the owning side to null (unless already changed)
            if ($payment->getSale() === $this) {
                $payment->setSale(null);
            }
        }

        return $this;
    }

    public function getPaymentStatus(): string
    {
        return $this->paymentStatus;
    }

    public function setPaymentStatus(string $paymentStatus): static
    {
        $this->paymentStatus = $paymentStatus;
        return $this;
    }

    /**
     * Calculate total paid amount from all payments
     */
    public function getPaidAmount(): float
    {
        $totalPaid = 0;
        foreach ($this->payments as $payment) {
            $totalPaid += (float) $payment->getAmount();
        }
        return $totalPaid;
    }

    /**
     * Calculate remaining balance
     */
    public function getBalance(): float
    {
        return (float) $this->total - $this->getPaidAmount();
    }

    /**
     * Sync payment status based on total and payments
     */
    public function updatePaymentStatus(): void
    {
        if ($this->paymentStatus === 'Cancelled') {
            return;
        }

        $paid = $this->getPaidAmount();
        $total = (float) $this->total;

        if ($paid <= 0) {
            if ($this->payments->isEmpty()) {
                $this->paymentStatus = 'Unpaid';
            } else {
                $this->paymentStatus = 'Refunded';
            }
        } elseif ($paid < $total) {
            $this->paymentStatus = 'Partial';
        } elseif ($paid > $total) {
            $this->paymentStatus = 'Overpaid';
        } else {
            $this->paymentStatus = 'Paid';
        }
    }

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $medicalDetails = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $prescriptionNotes = null;

    public function getMedicalDetails(): ?string
    {
        return $this->medicalDetails;
    }

    public function setMedicalDetails(?string $medicalDetails): static
    {
        $this->medicalDetails = $medicalDetails;
        return $this;
    }

    public function getPrescriptionNotes(): ?string
    {
        return $this->prescriptionNotes;
    }

    public function setPrescriptionNotes(?string $prescriptionNotes): static
    {
        $this->prescriptionNotes = $prescriptionNotes;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $doctor = null;

    public function getDoctor(): ?User
    {
        return $this->doctor;
    }

    public function setDoctor(?User $doctor): static
    {
        $this->doctor = $doctor;
        return $this;
    }

    /**
     * @return Collection<int, PrescriptionItem>
     */
    public function getPrescriptionItems(): Collection
    {
        return $this->prescriptionItems;
    }

    public function addPrescriptionItem(PrescriptionItem $prescriptionItem): static
    {
        if (!$this->prescriptionItems->contains($prescriptionItem)) {
            $this->prescriptionItems->add($prescriptionItem);
            $prescriptionItem->setSale($this);
        }

        return $this;
    }

    public function removePrescriptionItem(PrescriptionItem $prescriptionItem): static
    {
        if ($this->prescriptionItems->removeElement($prescriptionItem)) {
            // set the owning side to null (unless already changed)
            if ($prescriptionItem->getSale() === $this) {
                $prescriptionItem->setSale(null);
            }
        }

        return $this;
    }
}
