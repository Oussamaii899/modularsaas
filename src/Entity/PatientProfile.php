<?php

namespace App\Entity;

use App\Repository\PatientProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PatientProfileRepository::class)]
class PatientProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $diseaseCategory = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $chronicDiseases = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $generalMedicalNotes = null;

    #[ORM\OneToOne(targetEntity: Contact::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: "contact_id", referencedColumnName: "id", nullable: false, onDelete: "CASCADE")]
    private ?Contact $contact = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDiseaseCategory(): ?string
    {
        return $this->diseaseCategory;
    }

    public function setDiseaseCategory(?string $diseaseCategory): static
    {
        $this->diseaseCategory = $diseaseCategory;
        return $this;
    }

    public function getChronicDiseases(): ?string
    {
        return $this->chronicDiseases;
    }

    public function setChronicDiseases(?string $chronicDiseases): static
    {
        $this->chronicDiseases = $chronicDiseases;
        return $this;
    }

    public function getGeneralMedicalNotes(): ?string
    {
        return $this->generalMedicalNotes;
    }

    public function setGeneralMedicalNotes(?string $generalMedicalNotes): static
    {
        $this->generalMedicalNotes = $generalMedicalNotes;
        return $this;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): static
    {
        $this->contact = $contact;
        return $this;
    }

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $emergencyContactName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emergencyContactPhone = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $emergencyContactRelation = null;

    public function getEmergencyContactName(): ?string
    {
        return $this->emergencyContactName;
    }

    public function setEmergencyContactName(?string $emergencyContactName): static
    {
        $this->emergencyContactName = $emergencyContactName;
        return $this;
    }

    public function getEmergencyContactPhone(): ?string
    {
        return $this->emergencyContactPhone;
    }

    public function setEmergencyContactPhone(?string $emergencyContactPhone): static
    {
        $this->emergencyContactPhone = $emergencyContactPhone;
        return $this;
    }

    public function getEmergencyContactRelation(): ?string
    {
        return $this->emergencyContactRelation;
    }

    public function setEmergencyContactRelation(?string $emergencyContactRelation): static
    {
        $this->emergencyContactRelation = $emergencyContactRelation;
        return $this;
    }
}
