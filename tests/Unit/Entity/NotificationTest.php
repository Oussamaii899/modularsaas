<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Notification;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Notification entity.
 *
 * Covers:
 *  - Constructor defaults (type, isRead, createdAt)
 *  - All getters / setters
 *  - User association
 */
class NotificationTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor defaults
    // -------------------------------------------------------------------------

    public function testDefaultTypeIsInfo(): void
    {
        $notification = new Notification();
        $this->assertSame('info', $notification->getType());
    }

    public function testDefaultIsReadIsFalse(): void
    {
        $notification = new Notification();
        $this->assertFalse($notification->isRead());
    }

    public function testCreatedAtIsSetOnConstruct(): void
    {
        $before = new \DateTime();
        $notification = new Notification();
        $after  = new \DateTime();

        $this->assertNotNull($notification->getCreatedAt());
        $this->assertGreaterThanOrEqual($before, $notification->getCreatedAt());
        $this->assertLessThanOrEqual($after, $notification->getCreatedAt());
    }

    // -------------------------------------------------------------------------
    // Getters / setters
    // -------------------------------------------------------------------------

    public function testSetAndGetTitle(): void
    {
        $notification = new Notification();
        $notification->setTitle('Low Stock Alert');
        $this->assertSame('Low Stock Alert', $notification->getTitle());
    }

    public function testSetAndGetMessage(): void
    {
        $notification = new Notification();
        $notification->setMessage('Product X has only 3 units left.');
        $this->assertSame('Product X has only 3 units left.', $notification->getMessage());
    }

    public function testSetAndGetType(): void
    {
        $notification = new Notification();

        foreach (['info', 'warning', 'danger', 'success'] as $type) {
            $notification->setType($type);
            $this->assertSame($type, $notification->getType());
        }
    }

    public function testSetAndGetIsRead(): void
    {
        $notification = new Notification();
        $notification->setIsRead(true);
        $this->assertTrue($notification->isRead());
    }

    public function testSetAndGetLinkUrl(): void
    {
        $notification = new Notification();
        $notification->setLinkUrl('/products/aspirin');
        $this->assertSame('/products/aspirin', $notification->getLinkUrl());
    }

    public function testSetLinkUrlToNull(): void
    {
        $notification = new Notification();
        $notification->setLinkUrl(null);
        $this->assertNull($notification->getLinkUrl());
    }

    // -------------------------------------------------------------------------
    // User association
    // -------------------------------------------------------------------------

    public function testSetAndGetUser(): void
    {
        $notification = new Notification();
        $user = new User();
        $user->setUsername('admin');

        $notification->setUser($user);

        $this->assertSame($user, $notification->getUser());
    }

    public function testSetUserToNull(): void
    {
        $notification = new Notification();
        $notification->setUser(null);
        $this->assertNull($notification->getUser());
    }

    // -------------------------------------------------------------------------
    // createdAt explicit setter
    // -------------------------------------------------------------------------

    public function testSetCreatedAt(): void
    {
        $notification = new Notification();
        $dt = new \DateTime('2024-01-01 08:00:00');
        $notification->setCreatedAt($dt);

        $this->assertSame($dt, $notification->getCreatedAt());
    }
}
