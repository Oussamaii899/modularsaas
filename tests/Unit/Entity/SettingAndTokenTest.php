<?php

namespace App\Tests\Unit\Entity;

use App\Entity\ResetPasswordRequest;
use App\Entity\Setting;
use App\Entity\Token;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class SettingAndTokenTest extends TestCase
{
    public function testSettingGettersAndSetters(): void
    {
        $setting = new Setting();
        $setting->setKeyName('app_currency')
            ->setValue('USD');

        $this->assertNull($setting->getId());
        $this->assertEquals('app_currency', $setting->getKeyName());
        $this->assertEquals('USD', $setting->getValue());
    }

    public function testTokenGettersAndSetters(): void
    {
        $token = new Token();
        $user = new User();
        $expires = new \DateTime('+1 hour');

        $token->setToken('random_hash_abc123')
            ->setUser($user)
            ->setExpiresAt($expires);

        $this->assertNull($token->getId());
        $this->assertEquals('random_hash_abc123', $token->getToken());
        $this->assertSame($user, $token->getUser());
        $this->assertSame($expires, $token->getExpiresAt());
    }

    public function testResetPasswordRequest(): void
    {
        $user = new User();
        $expiresAt = new \DateTime('+1 day');
        $resetReq = new ResetPasswordRequest($user, $expiresAt, 'selector_str', 'hashed_token_str');

        $this->assertNull($resetReq->getId());
        $this->assertSame($user, $resetReq->getUser());
        $this->assertSame($expiresAt, $resetReq->getExpiresAt());
    }
}
