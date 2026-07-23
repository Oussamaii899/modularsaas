<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Contact;
use App\Entity\PatientDocument;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PatientDocumentTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $doc = new PatientDocument();
        $contact = new Contact();
        $user = new User();
        $now = new \DateTimeImmutable();

        $doc->setContact($contact)
            ->setLabel('X-Ray Results')
            ->setFilename('xray_123.pdf')
            ->setDescription('Chest X-ray report')
            ->setUploadedBy($user)
            ->setCreatedAt($now);

        $this->assertNull($doc->getId());
        $this->assertSame($contact, $doc->getContact());
        $this->assertEquals('X-Ray Results', $doc->getLabel());
        $this->assertEquals('xray_123.pdf', $doc->getFilename());
        $this->assertEquals('Chest X-ray report', $doc->getDescription());
        $this->assertSame($user, $doc->getUploadedBy());
        $this->assertSame($now, $doc->getCreatedAt());
    }

    public function testConstructorInitializesCreatedAt(): void
    {
        $doc = new PatientDocument();
        $this->assertInstanceOf(\DateTimeImmutable::class, $doc->getCreatedAt());
    }
}
