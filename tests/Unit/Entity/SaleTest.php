<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Payment;
use App\Entity\Sale;
use App\Entity\SaleItem;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Sale entity.
 *
 * Covers:
 *  - Slug generation in constructor
 *  - getPaidAmount() – sum of payments
 *  - getBalance() – total minus paid
 *  - updatePaymentStatus() – all status transitions
 *  - SaleItem collection management
 */
class SaleTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor / Slug
    // -------------------------------------------------------------------------

    public function testSlugIsGeneratedOnConstruct(): void
    {
        $sale = new Sale();
        $this->assertNotNull($sale->getSlug());
        $this->assertStringStartsWith('inv-', $sale->getSlug());
    }

    public function testTwoSalesHaveDifferentSlugs(): void
    {
        $sale1 = new Sale();
        $sale2 = new Sale();
        $this->assertNotSame($sale1->getSlug(), $sale2->getSlug());
    }

    // -------------------------------------------------------------------------
    // getPaidAmount()
    // -------------------------------------------------------------------------

    public function testGetPaidAmountReturnsZeroWhenNoPayments(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');

        $this->assertSame(0.0, $sale->getPaidAmount());
    }

    public function testGetPaidAmountSumsAllPayments(): void
    {
        $sale = new Sale();
        $sale->setTotal('200.00');

        $p1 = (new Payment())->setAmount('80.00')->setMethod('cash');
        $p2 = (new Payment())->setAmount('70.00')->setMethod('card');

        $sale->addPayment($p1);
        $sale->addPayment($p2);

        $this->assertEqualsWithDelta(150.0, $sale->getPaidAmount(), 0.001);
    }

    // -------------------------------------------------------------------------
    // getBalance()
    // -------------------------------------------------------------------------

    public function testGetBalanceIsZeroWhenFullyPaid(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');
        $sale->addPayment((new Payment())->setAmount('100.00')->setMethod('cash'));

        $this->assertEqualsWithDelta(0.0, $sale->getBalance(), 0.001);
    }

    public function testGetBalanceIsPositiveWhenPartiallyPaid(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');
        $sale->addPayment((new Payment())->setAmount('60.00')->setMethod('cash'));

        $this->assertEqualsWithDelta(40.0, $sale->getBalance(), 0.001);
    }

    public function testGetBalanceIsNegativeWhenOverpaid(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');
        $sale->addPayment((new Payment())->setAmount('120.00')->setMethod('card'));

        $this->assertEqualsWithDelta(-20.0, $sale->getBalance(), 0.001);
    }

    // -------------------------------------------------------------------------
    // updatePaymentStatus()
    // -------------------------------------------------------------------------

    public function testUpdatePaymentStatusSetsUnpaidWhenNoPayments(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');
        $sale->updatePaymentStatus();

        $this->assertSame('Unpaid', $sale->getPaymentStatus());
    }

    public function testUpdatePaymentStatusSetsRefundedWhenPaymentsExistButZeroAmount(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');
        // Add a refund-style payment with 0 amount
        $sale->addPayment((new Payment())->setAmount('0.00')->setMethod('refund'));
        $sale->updatePaymentStatus();

        $this->assertSame('Refunded', $sale->getPaymentStatus());
    }

    public function testUpdatePaymentStatusSetsPartialWhenPaidLessThanTotal(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');
        $sale->addPayment((new Payment())->setAmount('40.00')->setMethod('cash'));
        $sale->updatePaymentStatus();

        $this->assertSame('Partial', $sale->getPaymentStatus());
    }

    public function testUpdatePaymentStatusSetsPaidWhenExactMatch(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');
        $sale->addPayment((new Payment())->setAmount('100.00')->setMethod('card'));
        $sale->updatePaymentStatus();

        $this->assertSame('Paid', $sale->getPaymentStatus());
    }

    public function testUpdatePaymentStatusSetsOverpaidWhenExceedsTotal(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');
        $sale->addPayment((new Payment())->setAmount('150.00')->setMethod('card'));
        $sale->updatePaymentStatus();

        $this->assertSame('Overpaid', $sale->getPaymentStatus());
    }

    public function testUpdatePaymentStatusDoesNothingWhenCancelled(): void
    {
        $sale = new Sale();
        $sale->setTotal('100.00');
        $sale->setPaymentStatus('Cancelled');
        $sale->addPayment((new Payment())->setAmount('100.00')->setMethod('card'));

        // Should remain Cancelled
        $sale->updatePaymentStatus();

        $this->assertSame('Cancelled', $sale->getPaymentStatus());
    }

    // -------------------------------------------------------------------------
    // SaleItem collection management
    // -------------------------------------------------------------------------

    public function testAddSaleItemAddsToCollection(): void
    {
        $sale = new Sale();
        $item = new SaleItem();

        $sale->addSaleItem($item);

        $this->assertTrue($sale->getSaleItems()->contains($item));
        $this->assertSame($sale, $item->getSale());
    }

    public function testAddSaleItemDoesNotDuplicate(): void
    {
        $sale = new Sale();
        $item = new SaleItem();

        $sale->addSaleItem($item);
        $sale->addSaleItem($item); // second call is a no-op

        $this->assertCount(1, $sale->getSaleItems());
    }

    public function testRemoveSaleItemRemovesFromCollection(): void
    {
        $sale = new Sale();
        $item = new SaleItem();

        $sale->addSaleItem($item);
        $sale->removeSaleItem($item);

        $this->assertFalse($sale->getSaleItems()->contains($item));
    }

    // -------------------------------------------------------------------------
    // Medical / Prescription fields
    // -------------------------------------------------------------------------

    public function testMedicalDetailsGetterSetter(): void
    {
        $sale = new Sale();
        $sale->setMedicalDetails('Patient has hypertension');

        $this->assertSame('Patient has hypertension', $sale->getMedicalDetails());
    }

    public function testPrescriptionNotesGetterSetter(): void
    {
        $sale = new Sale();
        $sale->setPrescriptionNotes('Take twice daily');

        $this->assertSame('Take twice daily', $sale->getPrescriptionNotes());
    }
}
