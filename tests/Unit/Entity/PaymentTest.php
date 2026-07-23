<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Payment;
use App\Entity\Purchase;
use App\Entity\Sale;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Payment entity.
 *
 * Covers:
 *  - Constructor defaults (createdAt, type)
 *  - All getters / setters
 *  - Association with Sale and Purchase
 */
class PaymentTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor defaults
    // -------------------------------------------------------------------------

    public function testCreatedAtIsSetOnConstruct(): void
    {
        $before = new \DateTimeImmutable();
        $payment = new Payment();
        $after  = new \DateTimeImmutable();

        $this->assertNotNull($payment->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $payment->getCreatedAt());
        $this->assertLessThanOrEqual($after, $payment->getCreatedAt());
    }

    public function testDefaultTypeIsPayment(): void
    {
        $payment = new Payment();
        $this->assertSame('Payment', $payment->getType());
    }

    // -------------------------------------------------------------------------
    // Getters / setters
    // -------------------------------------------------------------------------

    public function testSetAndGetAmount(): void
    {
        $payment = new Payment();
        $payment->setAmount('250.75');

        $this->assertSame('250.75', $payment->getAmount());
    }

    public function testSetAndGetMethod(): void
    {
        $payment = new Payment();
        $payment->setMethod('bank_transfer');

        $this->assertSame('bank_transfer', $payment->getMethod());
    }

    public function testSetAndGetReference(): void
    {
        $payment = new Payment();
        $payment->setReference('REF-20240101-001');

        $this->assertSame('REF-20240101-001', $payment->getReference());
    }

    public function testSetAndGetNullableReference(): void
    {
        $payment = new Payment();
        $payment->setReference(null);

        $this->assertNull($payment->getReference());
    }

    public function testSetAndGetType(): void
    {
        $payment = new Payment();
        $payment->setType('Refund');

        $this->assertSame('Refund', $payment->getType());
    }

    // -------------------------------------------------------------------------
    // Sale association
    // -------------------------------------------------------------------------

    public function testSetAndGetSale(): void
    {
        $payment = new Payment();
        $sale = new Sale();

        $payment->setSale($sale);

        $this->assertSame($sale, $payment->getSale());
    }

    public function testSetSaleToNull(): void
    {
        $payment = new Payment();
        $sale = new Sale();

        $payment->setSale($sale);
        $payment->setSale(null);

        $this->assertNull($payment->getSale());
    }

    // -------------------------------------------------------------------------
    // Purchase association
    // -------------------------------------------------------------------------

    public function testSetAndGetPurchase(): void
    {
        $payment = new Payment();
        $purchase = new Purchase();

        $payment->setPurchase($purchase);

        $this->assertSame($purchase, $payment->getPurchase());
    }

    public function testSetPurchaseToNull(): void
    {
        $payment = new Payment();
        $purchase = new Purchase();

        $payment->setPurchase($purchase);
        $payment->setPurchase(null);

        $this->assertNull($payment->getPurchase());
    }

    // -------------------------------------------------------------------------
    // createdAt explicit setter
    // -------------------------------------------------------------------------

    public function testSetCreatedAt(): void
    {
        $payment = new Payment();
        $dt = new \DateTimeImmutable('2024-01-15 10:00:00');
        $payment->setCreatedAt($dt);

        $this->assertSame($dt, $payment->getCreatedAt());
    }
}
