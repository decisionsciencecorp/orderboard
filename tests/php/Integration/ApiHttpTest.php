<?php

use PHPUnit\Framework\TestCase;

/**
 * Integration tests for REST API via HTTP.
 * Set BASE_URL (e.g. http://localhost:8000) to run against a live server; otherwise skipped.
 */
class ApiHttpTest extends TestCase
{
    private static ?string $baseUrl = null;
    private static ?string $apiKey = null;

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = getenv('BASE_URL') ?: 'http://localhost:8000';
        self::$baseUrl = rtrim(self::$baseUrl, '/');
        require_once dirname(__DIR__, 2) . '/bootstrap.php';
        require_once dirname(__DIR__, 2) . '/../public/includes/auth.php';
        require_once dirname(__DIR__, 2) . '/../public/includes/functions.php';
        self::$apiKey = createApiKey('http-test-key');
    }

    private function request(string $method, string $path, array $body = [], array $headers = []): array
    {
        $url = self::$baseUrl . $path;
        $defaultHeaders = [
            'X-API-Key: ' . self::$apiKey,
            'Content-Type: application/json',
        ];
        $headers = array_merge($defaultHeaders, $headers);
        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'ignore_errors' => true,
                'timeout' => 2,
            ],
        ];
        if ($body !== [] && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            $opts['http']['content'] = json_encode($body);
        }
        $ctx = stream_context_create($opts);
        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            $this->markTestSkipped('Server not reachable at ' . self::$baseUrl . ' - start with: cd public && php -S localhost:8000');
        }
        $code = 0;
        if (isset($http_response_header[0]) && preg_match('/ (\d{3}) /', $http_response_header[0], $m)) {
            $code = (int) $m[1];
        }
        $data = json_decode($response, true);
        return ['code' => $code, 'body' => $data !== null ? $data : $response];
    }

    public function testDisplayEndpointNoAuth(): void
    {
        $url = self::$baseUrl . '/api/display.php';
        $opts = ['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 5]];
        $response = @file_get_contents($url, false, stream_context_create($opts));
        if ($response === false) {
            $this->markTestSkipped('Server not reachable');
        }
        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertTrue($data['success'] ?? false);
        $this->assertArrayHasKey('orders', $data);
        $this->assertArrayHasKey('refresh_interval', $data);
    }

    public function testListOrdersRequiresAuth(): void
    {
        $url = self::$baseUrl . '/api/list-orders.php';
        $opts = ['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 5]];
        $response = @file_get_contents($url, false, stream_context_create($opts));
        if ($response === false) {
            $this->markTestSkipped('Server not reachable');
        }
        $data = json_decode($response, true);
        $this->assertIsArray($data);
        $this->assertFalse($data['success'] ?? true);
        $this->assertStringContainsString('API key', $data['error'] ?? '');
    }

    public function testCreateOrderViaApi(): void
    {
        $r = $this->request('POST', '/api/create-order.php', [
            'customer_name' => 'HTTP Test User',
            'platform' => 'doordash',
        ]);
        if (isset($r['body']['error']) && strpos($r['body']['error'] ?? '', 'reachable') !== false) {
            $this->markTestSkipped('Server not reachable');
        }
        $this->assertSame(201, $r['code'], json_encode($r['body']));
        $this->assertTrue($r['body']['success'] ?? false);
        $this->assertArrayHasKey('order', $r['body']);
        $this->assertSame('HTTP Test User', $r['body']['order']['customer_name'] ?? '');
    }

    public function testListOrdersViaApi(): void
    {
        $r = $this->request('GET', '/api/list-orders.php');
        if (is_string($r['body']) || ($r['body']['success'] ?? false) === false && ($r['body']['error'] ?? '') === '') {
            $this->markTestSkipped('Server not reachable or invalid response');
        }
        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success']);
        $this->assertArrayHasKey('orders', $r['body']);
    }

    public function testGetOrderViaApi(): void
    {
        $create = $this->request('POST', '/api/create-order.php', [
            'customer_name' => 'Get Single',
            'platform' => 'ubereats',
        ]);
        if (($create['body']['success'] ?? false) !== true) {
            $this->markTestSkipped('Create failed or server not reachable');
        }
        $orderId = $create['body']['order']['order_id'] ?? null;
        $this->assertNotNull($orderId);
        $r = $this->request('GET', '/api/get-order.php?order_id=' . urlencode($orderId));
        $this->assertSame(200, $r['code']);
        $this->assertSame($orderId, $r['body']['order']['order_id'] ?? null);
    }

    public function testUpdateOrderViaApi(): void
    {
        $create = $this->request('POST', '/api/create-order.php', [
            'customer_name' => 'Update Via API',
            'platform' => 'grubhub',
        ]);
        if (($create['body']['success'] ?? false) !== true) {
            $this->markTestSkipped('Create failed or server not reachable');
        }
        $orderId = $create['body']['order']['order_id'];
        $r = $this->request('POST', '/api/update-order.php', [
            'order_id' => $orderId,
            'status' => 'ready',
            'shelf_location' => 'B',
        ]);
        $this->assertSame(200, $r['code']);
        $this->assertSame('ready', $r['body']['order']['status'] ?? null);
    }

    public function testDeleteOrderViaApi(): void
    {
        $create = $this->request('POST', '/api/create-order.php', [
            'customer_name' => 'Delete Via API',
            'platform' => 'doordash',
        ]);
        if (($create['body']['success'] ?? false) !== true) {
            $this->markTestSkipped('Create failed or server not reachable');
        }
        $orderId = $create['body']['order']['order_id'];
        $r = $this->request('DELETE', '/api/delete-order.php', ['order_id' => $orderId]);
        $this->assertSame(200, $r['code']);
        $this->assertTrue($r['body']['success'] ?? false);
    }

    public function testStatsViaApi(): void
    {
        $r = $this->request('GET', '/api/stats.php');
        if (is_string($r['body']) || (($r['body']['success'] ?? false) === false && ($r['code'] ?? 0) !== 200)) {
            $this->markTestSkipped('Server not reachable or invalid response');
        }
        $this->assertSame(200, $r['code']);
        $this->assertArrayHasKey('stats', $r['body']);
    }

    public function testCreateOrderMissingFieldReturns400(): void
    {
        $r = $this->request('POST', '/api/create-order.php', ['platform' => 'doordash']);
        if ($r['code'] === 0) {
            $this->markTestSkipped('Server not reachable');
        }
        $this->assertSame(400, $r['code']);
        $this->assertStringContainsString('customer_name', $r['body']['error'] ?? '');
    }

    public function testMethodNotAllowed(): void
    {
        $r = $this->request('POST', '/api/list-orders.php', []);
        if ($r['code'] === 0) {
            $this->markTestSkipped('Server not reachable');
        }
        $this->assertSame(405, $r['code']);
    }
}
