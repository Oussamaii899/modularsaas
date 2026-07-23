<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Product;
use App\Entity\ProductScreen;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Product entity.
 *
 * Covers:
 *  - setName() auto-slug generation
 *  - setName() does not overwrite an existing slug
 *  - setSlug() explicit override
 *  - addScreen() / removeScreen() collection management
 *  - getters and setters
 */
class ProductTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Slug auto-generation via setName()
    // -------------------------------------------------------------------------

    public function testSetNameGeneratesSlug(): void
    {
        $product = new Product();
        $product->setName('My Awesome Product');

        $this->assertSame('my-awesome-product', $product->getSlug());
    }

    public function testSetNameConvertsSpecialCharsToHyphens(): void
    {
        $product = new Product();
        $product->setName('Vitamin C (1000mg) & Zinc!');

        // Non-alphanumeric runs become single hyphens; output is lower-cased
        $slug = $product->getSlug();
        $this->assertNotNull($slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
    }

    public function testSetNameDoesNotOverwriteExistingSlug(): void
    {
        $product = new Product();
        $product->setSlug('my-custom-slug');
        $product->setName('Something Completely Different');

        // Slug must NOT be replaced because it was already set
        $this->assertSame('my-custom-slug', $product->getSlug());
    }

    public function testSetSlugExplicitOverride(): void
    {
        $product = new Product();
        $product->setName('Original Name');
        $product->setSlug('overridden-slug');

        $this->assertSame('overridden-slug', $product->getSlug());
    }

    // -------------------------------------------------------------------------
    // getters / setters
    // -------------------------------------------------------------------------

    public function testGettersAndSetters(): void
    {
        $product = new Product();
        $product->setName('Aspirin');
        $product->setPrice('9.99');
        $product->setStockQuantity(100);
        $product->setSku('ASP-001');
        $product->setDescription('Pain reliever');
        $product->setImage('aspirin.jpg');

        $this->assertSame('Aspirin', $product->getName());
        $this->assertSame('9.99', $product->getPrice());
        $this->assertSame(100, $product->getStockQuantity());
        $this->assertSame('ASP-001', $product->getSku());
        $this->assertSame('Pain reliever', $product->getDescription());
        $this->assertSame('aspirin.jpg', $product->getImage());
    }

    public function testNullableSkuAcceptsNull(): void
    {
        $product = new Product();
        $product->setSku(null);
        $this->assertNull($product->getSku());
    }

    // -------------------------------------------------------------------------
    // ProductScreen collection management
    // -------------------------------------------------------------------------

    public function testAddScreenAddsToCollection(): void
    {
        $product = new Product();
        $screen = new ProductScreen();

        $product->addScreen($screen);

        $this->assertTrue($product->getScreens()->contains($screen));
        $this->assertSame($product, $screen->getProduct());
    }

    public function testAddScreenDoesNotDuplicate(): void
    {
        $product = new Product();
        $screen = new ProductScreen();

        $product->addScreen($screen);
        $product->addScreen($screen); // no-op

        $this->assertCount(1, $product->getScreens());
    }

    public function testRemoveScreenRemovesFromCollection(): void
    {
        $product = new Product();
        $screen = new ProductScreen();

        $product->addScreen($screen);
        $product->removeScreen($screen);

        $this->assertFalse($product->getScreens()->contains($screen));
    }
}
