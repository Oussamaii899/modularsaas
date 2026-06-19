<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\SettingRepository;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $keyName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $value = null;

    public function getId(): ?int { return $this->id; }

    public function getKeyName(): ?string { return $this->keyName; }
    public function setKeyName(string $keyName): static { $this->keyName = $keyName; return $this; }

    public function getValue(): ?string { return $this->value; }
    public function setValue(?string $value): static { $this->value = $value; return $this; }
}
