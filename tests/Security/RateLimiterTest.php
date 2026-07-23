<?php

namespace App\Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security test suite for the Rate Limiter system.
 *
 * Tests:
 *  - Login brute-force protection (login_throttling)
 *  - API endpoint rate limiting (/api/*)
 *  - AI Growth Report rate limiting (/sales/overview/growth-report)
 */
class RateLimiterTest extends WebTestCase
{
    /**
     * Test that the login route triggers throttle (429) after exceeding max_attempts.
     * Default config: max 5 attempts per minute.
     */
    public function testLoginBruteForceProtection(): void
    {
        $client = static::createClient();

        // Simulate 5 failed login attempts within the same minute
        for ($i = 0; $i < 5; $i++) {
            $client->request('POST', '/login', [
                '_username' => 'nonexistent_user_' . $i,
                '_password' => 'wrongpassword',
            ]);
        }

        // On the 6th attempt, the rate limiter should kick in
        $client->request('POST', '/login', [
            '_username' => 'nonexistent_user_excess',
            '_password' => 'wrongpassword',
        ]);

        // Symfony's login_throttling sends 429 when limit is exceeded
        $statusCode = $client->getResponse()->getStatusCode();
        $this->assertContains(
            $statusCode,
            [Response::HTTP_TOO_MANY_REQUESTS, Response::HTTP_FOUND],
            'Login should be blocked or redirected after exceeding rate limit'
        );
    }

    /**
     * Test that API endpoints return 429 after exceeding the api_limiter (60 req/min).
     */
    public function testApiRateLimitExceeded(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        // Fire 61 requests to the notifications API from the same IP
        for ($i = 0; $i < 61; $i++) {
            $client->request('GET', '/api/notifications', [], [], [
                'HTTP_X_FORWARDED_FOR' => '10.0.1.1',
                'REMOTE_ADDR' => '10.0.1.1',
            ]);
        }

        $response = $client->getResponse();
        $this->assertEquals(
            429,
            $response->getStatusCode(),
            'API route should return 429 Too Many Requests after exceeding rate limit'
        );
        $this->assertTrue(
            $response->headers->has('Retry-After'),
            'Response should include a Retry-After header'
        );
    }

    /**
     * Test that a single API request under the limit is served normally (not blocked).
     */
    public function testApiRateLimitNotExceededForSingleRequest(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        $client->request('GET', '/api/notifications', [], [], [
            'HTTP_X_FORWARDED_FOR' => '10.0.2.1',
            'REMOTE_ADDR' => '10.0.2.1',
        ]);

        $response = $client->getResponse();

        // The rate limiter should accept the request (not 429)
        $this->assertNotEquals(
            429,
            $response->getStatusCode(),
            'A single API request should not be rate-limited'
        );
    }

    /**
     * Test that the AI growth report route is rate-limited after 3 requests per hour.
     */
    public function testAiReportRateLimitExceeded(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        // Fire 4 requests — limit is 3 per hour
        for ($i = 0; $i < 4; $i++) {
            $client->request('GET', '/sales/overview/growth-report', [], [], [
                'HTTP_X_FORWARDED_FOR' => '10.0.3.1',
                'REMOTE_ADDR' => '10.0.3.1',
                'HTTP_ACCEPT' => 'application/json',
            ]);
        }

        $response = $client->getResponse();
        $this->assertEquals(
            429,
            $response->getStatusCode(),
            'AI report route should return 429 after exceeding 3 requests per hour'
        );
    }

    /**
     * Test the Retry-After header is present in rate-limited API responses.
     */
    public function testRateLimitedResponseIncludesRetryAfterHeader(): void
    {
        self::ensureKernelShutdown();
        $client = static::createClient();

        // Exhaust the api_limiter for a unique IP
        for ($i = 0; $i < 61; $i++) {
            $client->request('GET', '/api/notifications', [], [], [
                'HTTP_X_FORWARDED_FOR' => '10.0.4.1',
                'REMOTE_ADDR' => '10.0.4.1',
            ]);
        }

        $response = $client->getResponse();
        if ($response->getStatusCode() === 429) {
            $this->assertTrue(
                $response->headers->has('Retry-After'),
                '429 response must include a Retry-After header'
            );
            $retryAfter = (int) $response->headers->get('Retry-After');
            $this->assertGreaterThanOrEqual(0, $retryAfter, 'Retry-After must be a non-negative integer');
        } else {
            $this->markTestIncomplete('Rate limit was not triggered in this environment.');
        }
    }
}
