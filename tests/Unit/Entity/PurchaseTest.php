<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Purchase entity.
 *
 * Mirrors SaleTest because Purchase has the same business logic.
 *
 * Covers:
 *  - Slug generation in constructor
 *  - getPaidAmount() – sum of payments
 *  - getBalance() – total minus paid
 *  - updatePaymentStatus() – all status transitions
 *  - PurchaseItem collection management
 */
class PurchaseTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor / Slug
    // -------------------------------------------------------------------------

    public function testSlugIsGeneratedOnConstruct(): void
    {
        $purchase = new Purchase();
        $this->assertNotNull($purchase->getSlug());
        $this->assertStringStartsWith('pur-', $purchase->getSlug());
    }

    public function testTwoPurchasesHaveDifferentSlugs(): void
    {
        $p1 = new Purchase();
        $p2 = new Purchase();
        $this->assertNotSame($p1->getSlug(), $p2->getSlug());
    }

    // -------------------------------------------------------------------------
    // getPaidAmount()
    // -------------------------------------------------------------------------

    public function testGetPaidAmountReturnsZeroWhenNoPayments(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('500.00');
        $this->assertSame(0.0, $purchase->getPaidAmount());
    }

    public function testGetPaidAmountSumsAllPayments(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('500.00');

        $p1 = (new Payment())->setAmount('200.00')->setMethod('transfer');
        $p2 = (new Payment())->setAmount('150.00')->setMethod('cash');

        $purchase->addPayment($p1);
        $purchase->addPayment($p2);

        $this->assertEqualsWithDelta(350.0, $purchase->getPaidAmount(), 0.001);
    }

    // -------------------------------------------------------------------------
    // getBalance()
    // -------------------------------------------------------------------------

    public function testGetBalanceWhenUnpaid(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('300.00');

        $this->assertEqualsWithDelta(300.0, $purchase->getBalance(), 0.001);
    }

    public function testGetBalanceWhenPartiallyPaid(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('300.00');
        $purchase->addPayment((new Payment())->setAmount('100.00')->setMethod('cash'));

        $this->assertEqualsWithDelta(200.0, $purchase->getBalance(), 0.001);
    }

    public function testGetBalanceIsNegativeWhenOverpaid(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('300.00');
        $purchase->addPayment((new Payment())->setAmount('400.00')->setMethod('card'));

        $this->assertEqualsWithDelta(-100.0, $purchase->getBalance(), 0.001);
    }

    // -------------------------------------------------------------------------
    // updatePaymentStatus()
    // -------------------------------------------------------------------------

    public function testUpdatePaymentStatusSetsUnpaidWhenNoPayments(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('100.00');
        $purchase->updatePaymentStatus();

        $this->assertSame('Unpaid', $purchase->getPaymentStatus());
    }

    public function testUpdatePaymentStatusSetsRefundedWhenZeroAmountPaymentExists(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('100.00');
        $purchase->addPayment((new Payment())->setAmount('0.00')->setMethod('refund'));
        $purchase->updatePaymentStatus();

        $this->assertSame('Refunded', $purchase->getPaymentStatus());
    }

    public function testUpdatePaymentStatusSetsPartialWhenPaidLessThanTotal(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('100.00');
        $purchase->addPayment((new Payment())->setAmount('50.00')->setMethod('cash'));
        $purchase->updatePaymentStatus();

        $this->assertSame('Partial', $purchase->getPaymentStatus());
    }

    public function testUpdatePaymentStatusSetsPaidWhenExactMatch(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('100.00');
        $purchase->addPayment((new Payment())->setAmount('100.00')->setMethod('card'));
        $purchase->updatePaymentStatus();

        $this->assertSame('Paid', $purchase->getPaymentStatus());
    }

    public function testUpdatePaymentStatusSetsOverpaidWhenExceedsTotal(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('100.00');
        $purchase->addPayment((new Payment())->setAmount('200.00')->setMethod('card'));
        $purchase->updatePaymentStatus();

        $this->assertSame('Overpaid', $purchase->getPaymentStatus());
    }

    public function testUpdatePaymentStatusDoesNothingWhenCancelled(): void
    {
        $purchase = new Purchase();
        $purchase->setTotal('100.00');
        $purchase->setPaymentStatus('Cancelled');
        $purchase->addPayment((new Payment())->setAmount('100.00')->setMethod('card'));

        $purchase->updatePaymentStatus();

        $this->assertSame('Cancelled', $purchase->getPaymentStatus());
    }

    // -------------------------------------------------------------------------
    // PurchaseItem collection management
    // -------------------------------------------------------------------------

    public function testAddPurchaseItemAddsToCollection(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();

        $purchase->addPurchaseItem($item);

        $this->assertTrue($purchase->getPurchaseItems()->contains($item));
        $this->assertSame($purchase, $item->getPurchase());
    }

    public function testAddPurchaseItemDoesNotDuplicate(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();

        $purchase->addPurchaseItem($item);
        $purchase->addPurchaseItem($item); // no-op

        $this->assertCount(1, $purchase->getPurchaseItems());
    }

    public function testRemovePurchaseItemRemovesFromCollection(): void
    {
        $purchase = new Purchase();
        $item = new PurchaseItem();

        $purchase->addPurchaseItem($item);
        $purchase->removePurchaseItem($item);

        $this->assertFalse($purchase->getPurchaseItems()->contains($item));
    }
}
