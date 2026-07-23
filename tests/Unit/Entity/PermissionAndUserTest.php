<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Permission;
use App\Entity\PermissionUser;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PermissionAndUserTest extends TestCase
{
    public function testPermissionGettersAndSetters(): void
    {
        $permission = new Permission();
        $permission->setName('ROLE_MANAGE_SALES');

        $this->assertNull($permission->getId());
        $this->assertEquals('ROLE_MANAGE_SALES', $permission->getName());
    }

    public function testPermissionUserGettersAndSetters(): void
    {
        $permissionUser = new PermissionUser();
        $user = new User();
        $permission = new Permission();

        $permissionUser->setUser($user)
            ->setPermission($permission);

        $this->assertNull($permissionUser->getId());
        $this->assertSame($user, $permissionUser->getUser());
        $this->assertSame($permission, $permissionUser->getPermission());
    }
}
