<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Permission;
use App\Entity\PermissionUser;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the User entity.
 *
 * Covers:
 *  - Role management (getRoles, deduplication)
 *  - Full name formatting
 *  - Permission helpers (hasPermission, hasAnyPermission)
 *  - getUserIdentifier
 */
class UserTest extends TestCase
{
    // -------------------------------------------------------------------------
    // getRoles()
    // -------------------------------------------------------------------------

    public function testGetRolesAlwaysIncludesRoleUser(): void
    {
        $user = new User();
        $user->setRoles([]);

        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testGetRolesIncludesAssignedRole(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER', $roles);
    }

    public function testGetRolesDeduplicatesRoleUser(): void
    {
        $user = new User();
        // Explicitly store ROLE_USER again — result must be unique
        $user->setRoles(['ROLE_USER', 'ROLE_USER']);

        $roles = $user->getRoles();

        $this->assertCount(1, array_filter($roles, fn($r) => $r === 'ROLE_USER'));
    }

    // -------------------------------------------------------------------------
    // getFullName()
    // -------------------------------------------------------------------------

    public function testGetFullNameReturnsTrimmedString(): void
    {
        $user = new User();
        $user->setFirstname('Alice');
        $user->setLastname('Smith');

        $this->assertSame('Alice Smith', $user->getFullName());
    }

    public function testGetFullNameWithNullFirstname(): void
    {
        $user = (new User())->setLastname('Smith');

        // Should not throw; trims the leading space
        $this->assertSame('Smith', $user->getFullName());
    }

    public function testGetFullNameWithNullLastname(): void
    {
        $user = (new User())->setFirstname('Alice');

        $this->assertSame('Alice', $user->getFullName());
    }

    // -------------------------------------------------------------------------
    // getUserIdentifier()
    // -------------------------------------------------------------------------

    public function testGetUserIdentifierReturnsUsername(): void
    {
        $user = new User();
        $user->setUsername('john_doe');

        $this->assertSame('john_doe', $user->getUserIdentifier());
    }

    // -------------------------------------------------------------------------
    // hasPermission()
    // -------------------------------------------------------------------------

    public function testHasPermissionReturnsTrueWhenSlugMatches(): void
    {
        $user = new User();

        // Build a real Permission + PermissionUser and wire them together
        $permission = new Permission();
        $permission->setName('manage_sales');

        $permUser = new PermissionUser();
        $permUser->setPermission($permission);
        $permUser->setUser($user);

        // Inject via reflection so we don't need a DB
        $this->injectPermissionUser($user, $permUser);

        $this->assertTrue($user->hasPermission('manage_sales'));
    }

    public function testHasPermissionReturnsFalseForUnknownSlug(): void
    {
        $user = new User();

        $permission = new Permission();
        $permission->setName('manage_sales');

        $permUser = new PermissionUser();
        $permUser->setPermission($permission);
        $permUser->setUser($user);

        $this->injectPermissionUser($user, $permUser);

        $this->assertFalse($user->hasPermission('delete_everything'));
    }

    public function testHasPermissionReturnsFalseWhenNoPermissions(): void
    {
        $user = new User();
        $this->assertFalse($user->hasPermission('manage_sales'));
    }

    // -------------------------------------------------------------------------
    // hasAnyPermission()
    // -------------------------------------------------------------------------

    public function testHasAnyPermissionReturnsFalseWhenEmpty(): void
    {
        $user = new User();
        $this->assertFalse($user->hasAnyPermission());
    }

    public function testHasAnyPermissionReturnsTrueWhenAtLeastOneExists(): void
    {
        $user = new User();

        $permission = new Permission();
        $permission->setName('view_reports');

        $permUser = new PermissionUser();
        $permUser->setPermission($permission);
        $permUser->setUser($user);

        $this->injectPermissionUser($user, $permUser);

        $this->assertTrue($user->hasAnyPermission());
    }

    // -------------------------------------------------------------------------
    // eraseCredentials()
    // -------------------------------------------------------------------------

    public function testEraseCredentialsDoesNotThrow(): void
    {
        $user = new User();
        $user->setPassword('hashed_password');

        // Should be a no-op; password should remain unchanged
        $user->eraseCredentials();
        $this->assertSame('hashed_password', $user->getPassword());
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Inject a PermissionUser into the User's private collection via reflection,
     * bypassing Doctrine's collection hydration (no DB needed).
     */
    private function injectPermissionUser(User $user, PermissionUser $permUser): void
    {
        $ref = new \ReflectionProperty(User::class, 'permissionUsers');
        $ref->setAccessible(true);
        $collection = $ref->getValue($user);
        $collection->add($permUser);
    }
}
