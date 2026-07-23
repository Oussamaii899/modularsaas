<?php

namespace App\Tests\Security;

use App\Entity\Permission;
use App\Entity\PermissionUser;
use App\Entity\User;
use App\Security\Voter\PermissionVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PermissionVoterTest extends TestCase
{
    private PermissionVoter $voter;

    protected function setUp(): void
    {
        $this->voter = new PermissionVoter();
    }

    public function testAbstainOnUnsupportedAttribute(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $result = $this->voter->vote($token, null, ['unsupported.attribute']);

        $this->assertEquals(Voter::ACCESS_ABSTAIN, $result);
    }

    public function testDeniedOnAnonymousUser(): void
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        $result = $this->voter->vote($token, null, ['see.dashboard']);

        $this->assertEquals(Voter::ACCESS_DENIED, $result);
    }

    public function testGrantedForAdminUser(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, null, ['see.dashboard']);

        $this->assertEquals(Voter::ACCESS_GRANTED, $result);
    }

    public function testGrantedWithSpecificPermission(): void
    {
        $user = new User();
        $permission = new Permission();
        $permission->setName('see.dashboard');

        $pu = new PermissionUser();
        $pu->setUser($user)->setPermission($permission);
        $user->getPermissionUsers()->add($pu);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        $result = $this->voter->vote($token, null, ['see.dashboard']);

        $this->assertEquals(Voter::ACCESS_GRANTED, $result);
    }

    public function testImplicitPermissionCheckForPurchasesAndSales(): void
    {
        $user = new User();
        $permission = new Permission();
        $permission->setName('see.purchases');

        $pu = new PermissionUser();
        $pu->setUser($user)->setPermission($permission);
        $user->getPermissionUsers()->add($pu);

        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        // 'see.purchases' implicitly grants 'see.purchase.overview'
        $result = $this->voter->vote($token, null, ['see.purchase.overview']);
        $this->assertEquals(Voter::ACCESS_GRANTED, $result);

        // Without 'see.sales', 'see.sale.overview' is denied
        $resultDenied = $this->voter->vote($token, null, ['see.sale.overview']);
        $this->assertEquals(Voter::ACCESS_DENIED, $resultDenied);
    }
}
