<?php

namespace App\Tests\Unit\Service;

use App\Service\OpenRouterService;
use PHPUnit\Framework\TestCase;

class OpenRouterServiceTest extends TestCase
{
    private ?string $originalApiKey;

    protected function setUp(): void
    {
        $this->originalApiKey = $_ENV['OPENROUTER_API_KEY'] ?? $_SERVER['OPENROUTER_API_KEY'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalApiKey !== null) {
            $_ENV['OPENROUTER_API_KEY'] = $this->originalApiKey;
        } else {
            unset($_ENV['OPENROUTER_API_KEY']);
        }
    }

    public function testMissingApiKeyReturnsError(): void
    {
        unset($_ENV['OPENROUTER_API_KEY']);
        unset($_SERVER['OPENROUTER_API_KEY']);

        $service = new OpenRouterService();
        $result = $service->generateReport([]);

        $this->assertStringContainsString('Error: OpenRouter API key is missing', $result);
    }
}
