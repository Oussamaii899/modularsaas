<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Log;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class LogTest extends TestCase
{
    public function testGettersAndSetters(): void
    {
        $log = new Log();
        $user = new User();
        $now = new \DateTime();

        $log->setAction('CREATE')
            ->setUser($user)
            ->setDetails('Created new item')
            ->setEntityClass('App\Entity\Product')
            ->setEntityId('123')
            ->setBeforeData(['stock' => 10])
            ->setAfterData(['stock' => 15])
            ->setCreatedAt($now);

        $this->assertNull($log->getId());
        $this->assertEquals('CREATE', $log->getAction());
        $this->assertSame($user, $log->getUser());
        $this->assertEquals('Created new item', $log->getDetails());
        $this->assertEquals('App\Entity\Product', $log->getEntityClass());
        $this->assertEquals('123', $log->getEntityId());
        $this->assertEquals(['stock' => 10], $log->getBeforeData());
        $this->assertEquals(['stock' => 15], $log->getAfterData());
        $this->assertSame($now, $log->getCreatedAt());
    }

    public function testConstructorInitializesCreatedAt(): void
    {
        $log = new Log();
        $this->assertInstanceOf(\DateTimeInterface::class, $log->getCreatedAt());
    }
}
