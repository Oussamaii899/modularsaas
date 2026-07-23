<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use App\Entity\PrescriptionItem;
use App\Entity\Sale;
use PHPUnit\Framework\TestCase;

class PrescriptionItemTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $item = new PrescriptionItem();
        $sale = new Sale();
        $product = new Product();

        $item->setSale($sale)
            ->setProduct($product)
            ->setMedicationName('Amoxicillin')
            ->setDosage('500mg')
            ->setFrequency('Three times a day')
            ->setDuration('7 days')
            ->setInstructions('Take after meals')
            ->setQuantity(21);

        $this->assertNull($item->getId());
        $this->assertSame($sale, $item->getSale());
        $this->assertSame($product, $item->getProduct());
        $this->assertEquals('Amoxicillin', $item->getMedicationName());
        $this->assertEquals('500mg', $item->getDosage());
        $this->assertEquals('Three times a day', $item->getFrequency());
        $this->assertEquals('7 days', $item->getDuration());
        $this->assertEquals('Take after meals', $item->getInstructions());
        $this->assertEquals(21, $item->getQuantity());
    }
}
