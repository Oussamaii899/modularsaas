<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\LogRepository;

#[ORM\Entity(repositoryClass: LogRepository::class)]
class Log
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $action = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $entityClass = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $beforeData = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $afterData = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getAction(): ?string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getDetails(): ?string { return $this->details; }
    public function setDetails(?string $details): static { $this->details = $details; return $this; }

    public function getEntityClass(): ?string { return $this->entityClass; }
    public function setEntityClass(?string $entityClass): static { $this->entityClass = $entityClass; return $this; }

    public function getEntityId(): ?string { return $this->entityId; }
    public function setEntityId(?string $entityId): static { $this->entityId = $entityId; return $this; }

    public function getBeforeData(): ?array { return $this->beforeData; }
    public function setBeforeData(?array $beforeData): static { $this->beforeData = $beforeData; return $this; }

    public function getAfterData(): ?array { return $this->afterData; }
    public function setAfterData(?array $afterData): static { $this->afterData = $afterData; return $this; }

    public function getCreatedAt(): ?\DateTimeInterface { return $this->createdAt; }
    public function setCreatedAt(\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt; return $this; }
}
