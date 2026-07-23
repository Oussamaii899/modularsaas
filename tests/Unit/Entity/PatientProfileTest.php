<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Contact;
use App\Entity\PatientProfile;
use PHPUnit\Framework\TestCase;

class PatientProfileTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $profile = new PatientProfile();
        $contact = new Contact();

        $profile->setContact($contact)
            ->setDiseaseCategory('Cardiology')
            ->setChronicDiseases('Hypertension')
            ->setGeneralMedicalNotes('Regular checkups required')
            ->setEmergencyContactName('Jane Doe')
            ->setEmergencyContactPhone('+123456789')
            ->setEmergencyContactRelation('Spouse');

        $this->assertNull($profile->getId());
        $this->assertSame($contact, $profile->getContact());
        $this->assertEquals('Cardiology', $profile->getDiseaseCategory());
        $this->assertEquals('Hypertension', $profile->getChronicDiseases());
        $this->assertEquals('Regular checkups required', $profile->getGeneralMedicalNotes());
        $this->assertEquals('Jane Doe', $profile->getEmergencyContactName());
        $this->assertEquals('+123456789', $profile->getEmergencyContactPhone());
        $this->assertEquals('Spouse', $profile->getEmergencyContactRelation());
    }
}
