<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use App\Entity\ProductItem;
use App\Entity\PurchaseItem;
use App\Entity\SaleItem;
use PHPUnit\Framework\TestCase;

class ProductItemTest extends TestCase
{
    public function testGettersSettersAndDefaults(): void
    {
        $item = new ProductItem();
        $product = new Product();
        $purchaseItem = new PurchaseItem();
        $saleItem = new SaleItem();

        $this->assertEquals(ProductItem::STATUS_AVAILABLE, $item->getStatus());

        $item->setProduct($product)
            ->setSerialNumber('SN-9988776655')
            ->setStatus(ProductItem::STATUS_SOLD)
            ->setNotes('Sold in sale #102')
            ->setPurchaseItem($purchaseItem)
            ->setSaleItem($saleItem);

        $this->assertNull($item->getId());
        $this->assertSame($product, $item->getProduct());
        $this->assertEquals('SN-9988776655', $item->getSerialNumber());
        $this->assertEquals(ProductItem::STATUS_SOLD, $item->getStatus());
        $this->assertEquals('Sold in sale #102', $item->getNotes());
        $this->assertSame($purchaseItem, $item->getPurchaseItem());
        $this->assertSame($saleItem, $item->getSaleItem());
        $this->assertInstanceOf(\DateTimeImmutable::class, $item->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $item->getUpdatedAt());
    }

    public function testUpdateTimestampsLifecycleCallback(): void
    {
        $item = new ProductItem();
        $oldUpdatedAt = $item->getUpdatedAt();

        usleep(1000);
        $item->updateTimestamps();

        $this->assertGreaterThanOrEqual($oldUpdatedAt, $item->getUpdatedAt());
    }
}
