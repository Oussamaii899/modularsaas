<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Contact;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Contact entity.
 *
 * Covers:
 *  - setName() auto-slug generation
 *  - setName() does not overwrite an existing slug
 *  - All getters/setters
 */
class ContactTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Slug auto-generation via setName()
    // -------------------------------------------------------------------------

    public function testSetNameGeneratesSlugFromName(): void
    {
        $contact = new Contact();
        $contact->setName('Acme Corporation');

        $this->assertSame('acme-corporation', $contact->getSlug());
    }

    public function testSetNameConvertsSpecialCharactersToHyphens(): void
    {
        $contact = new Contact();
        $contact->setName('John & Jane LLC!');

        $slug = $contact->getSlug();
        $this->assertNotNull($slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $slug);
    }

    public function testSetNameDoesNotOverwriteExistingSlug(): void
    {
        $contact = new Contact();
        $contact->setSlug('existing-slug');
        $contact->setName('New Name That Should Not Overwrite');

        $this->assertSame('existing-slug', $contact->getSlug());
    }

    public function testSetSlugExplicitOverride(): void
    {
        $contact = new Contact();
        $contact->setName('Any Name');
        $contact->setSlug('forced-slug');

        $this->assertSame('forced-slug', $contact->getSlug());
    }

    // -------------------------------------------------------------------------
    // Getters / setters
    // -------------------------------------------------------------------------

    public function testGettersAndSetters(): void
    {
        $contact = new Contact();
        $contact->setName('Patient Zero');
        $contact->setType('patient');
        $contact->setPhone('+1-555-0100');
        $contact->setEmail('zero@clinic.com');
        $contact->setAddress('123 Hospital Rd');
        $contact->setWebsite('https://clinic.com');
        $contact->setLogo('logo.png');
        $contact->setAvatar('avatar.png');

        $this->assertSame('Patient Zero', $contact->getName());
        $this->assertSame('patient', $contact->getType());
        $this->assertSame('+1-555-0100', $contact->getPhone());
        $this->assertSame('zero@clinic.com', $contact->getEmail());
        $this->assertSame('123 Hospital Rd', $contact->getAddress());
        $this->assertSame('https://clinic.com', $contact->getWebsite());
        $this->assertSame('logo.png', $contact->getLogo());
        $this->assertSame('avatar.png', $contact->getAvatar());
    }

    public function testNullableFieldsAcceptNull(): void
    {
        $contact = new Contact();
        $contact->setPhone(null);
        $contact->setEmail(null);
        $contact->setAddress(null);
        $contact->setWebsite(null);
        $contact->setLogo(null);
        $contact->setAvatar(null);

        $this->assertNull($contact->getPhone());
        $this->assertNull($contact->getEmail());
        $this->assertNull($contact->getAddress());
        $this->assertNull($contact->getWebsite());
        $this->assertNull($contact->getLogo());
        $this->assertNull($contact->getAvatar());
    }
}
