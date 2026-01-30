<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for admin redirect behavior (requireLogin → /admin/login.php).
 * Requires server at BASE_URL (e.g. php -S localhost:8000 -t public).
 */
class AdminRedirectTest extends TestCase
{
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = rtrim(getenv('BASE_URL') ?: 'http://localhost:8000', '/');
    }

    private function getHeaders(string $url): array
    {
        $opts = ['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 2]];
        $ctx = stream_context_create($opts);
        @file_get_contents($url, false, $ctx);
        return $http_response_header ?? [];
    }

    public function testDashboardRedirectsToLoginWhenNotAuthenticated(): void
    {
        $url = self::$baseUrl . '/admin/index.php';
        $headers = $this->getHeaders($url);
        if (empty($headers)) {
            $this->markTestSkipped('Server not reachable at ' . self::$baseUrl);
        }
        $firstLine = $headers[0] ?? '';
        $location = null;
        foreach ($headers as $h) {
            if (stripos($h, 'Location:') === 0) {
                $location = trim(substr($h, 9));
                break;
            }
        }
        $this->assertStringContainsString('302', $firstLine, 'Expected redirect');
        $this->assertNotNull($location, 'Expected Location header');
        $this->assertStringContainsString('/admin/login.php', $location);
    }

    public function testLoginPageReturns200(): void
    {
        $url = self::$baseUrl . '/admin/login.php';
        $headers = $this->getHeaders($url);
        if (empty($headers)) {
            $this->markTestSkipped('Server not reachable at ' . self::$baseUrl);
        }
        $this->assertStringContainsString('200', $headers[0] ?? '');
    }
}
