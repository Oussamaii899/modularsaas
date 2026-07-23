<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Contact;
use App\Entity\Product;
use App\Entity\ProductItem;
use App\Entity\Purchase;
use App\Entity\PurchaseItem;
use PHPUnit\Framework\TestCase;

class PurchaseAndSaleItemTest extends TestCase
{
    public function testPurchaseItemGettersSettersAndCollection(): void
    {
        $item = new PurchaseItem();
        $purchase = new Purchase();
        $contact = new Contact();
        $product = new Product();
        $productItem = new ProductItem();

        $item->setPurchase($purchase)
            ->setContact($contact)
            ->setProduct($product)
            ->setQuantity(5)
            ->setPrice('19.99')
            ->setContactName('Supplier Corp')
            ->setContactLogo('/images/supplier.png')
            ->setContactPhone('123456789')
            ->setContactEmail('supplier@corp.com')
            ->setPName('Widget')
            ->setPLogo('/images/widget.png')
            ->setPSku('SKU-123')
            ->setStatus('Active');

        $this->assertNull($item->getId());
        $this->assertSame($purchase, $item->getPurchase());
        $this->assertSame($contact, $item->getContact());
        $this->assertSame($product, $item->getProduct());
        $this->assertEquals(5, $item->getQuantity());
        $this->assertEquals('19.99', $item->getPrice());
        $this->assertEquals('Supplier Corp', $item->getContactName());
        $this->assertEquals('/images/supplier.png', $item->getContactLogo());
        $this->assertEquals('123456789', $item->getContactPhone());
        $this->assertEquals('supplier@corp.com', $item->getContactEmail());
        $this->assertEquals('Widget', $item->getPName());
        $this->assertEquals('/images/widget.png', $item->getPLogo());
        $this->assertEquals('SKU-123', $item->getPSku());
        $this->assertEquals('Active', $item->getStatus());

        $this->assertCount(0, $item->getProductItems());
        $item->addProductItem($productItem);
        $this->assertCount(1, $item->getProductItems());
        $this->assertSame($item, $productItem->getPurchaseItem());

        $item->removeProductItem($productItem);
        $this->assertCount(0, $item->getProductItems());
        $this->assertNull($productItem->getPurchaseItem());
    }
}
