<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use App\Repository\PermissionUserRepository;

#[ORM\Entity(repositoryClass: PermissionUserRepository::class)]
class PermissionUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'permissionUsers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Permission::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Permission $permission = null;

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getPermission(): ?Permission { return $this->permission; }
    public function setPermission(?Permission $permission): static { $this->permission = $permission; return $this; }
}
