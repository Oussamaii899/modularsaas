<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PermissionVoter extends Voter
{
    private const PERMISSIONS = [
        'see.dashboard',
        'see.purchases',
        'see.sales',
        'see.products',
        'see.logs',
        
        'see.purchase.overview',
        'see.purchase.suppliers',
        'see.purchase.list',
        'see.sale.overview',
        'see.sale.clients',
        'see.sale.list',
        
        'add.suppliers',
        'edit.suppliers',
        'delete.suppliers',
        
        'add.purchases',
        'edit.purchases',
        'delete.purchases',
        
        'add.clients',
        'edit.clients',
        'delete.clients',
        
        'add.sales',
        'edit.sales',
        'delete.sales',
        
        'add.products',
        'edit.products',
        'delete.products',
        
        'perm.general',
        'perm.appearance',
        'perm.branding',
        'perm.maintenance',
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, self::PERMISSIONS, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // ROLE_ADMIN bypasses all permission checks and has full access
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        // Implicit permissions checks
        if (in_array($attribute, ['see.purchase.overview', 'see.purchase.suppliers', 'see.purchase.list'], true)) {
            return $user->hasPermission('see.purchases') || $user->hasPermission($attribute);
        }

        if (in_array($attribute, ['see.sale.overview', 'see.sale.clients', 'see.sale.list'], true)) {
            return $user->hasPermission('see.sales') || $user->hasPermission($attribute);
        }

        return $user->hasPermission($attribute);
    }
}
