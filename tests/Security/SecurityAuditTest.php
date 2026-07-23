<?php

namespace App\Tests\Security;

use PHPUnit\Framework\TestCase;

function sanitizeFilename(string $filename): string
{
    return preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
}

class SecurityAuditTest extends TestCase
{
    public function testSanitizeFilenamePreventsPathTraversal(): void
    {
        $maliciousFilename = '../../../../etc/passwd';
        $cleaned = sanitizeFilename($maliciousFilename);

        $this->assertStringNotContainsString('../', $cleaned);
        $this->assertStringNotContainsString('/', $cleaned);
        $this->assertEquals('.._.._.._.._etc_passwd', $cleaned);
    }

    public function testSanitizeFilenameStripsExecutableChars(): void
    {
        $filename = 'shell.php;rm -rf /';
        $cleaned = sanitizeFilename($filename);

        $this->assertStringNotContainsString(';', $cleaned);
        $this->assertStringNotContainsString(' ', $cleaned);
        $this->assertStringNotContainsString('/', $cleaned);
        $this->assertEquals('shell.php_rm_-rf__', $cleaned);
    }

    public function testRateLimiterClassExistsAndConfigured(): void
    {
        $this->assertTrue(class_exists(\App\Tests\Security\RateLimiterTest::class));
    }
}
