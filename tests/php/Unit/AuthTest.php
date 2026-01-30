<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for auth: validateApiKey, requireApiKey, createApiKey, attemptLogin, isLoggedIn.
 */
class AuthTest extends TestCase
{
    private static ?string $apiKey = null;

    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_SERVER['HTTP_X_API_KEY']);
        require_once dirname(__DIR__, 2) . '/../public/includes/auth.php';
        require_once dirname(__DIR__, 2) . '/../public/includes/functions.php';
    }

    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 2) . '/bootstrap.php';
        require_once dirname(__DIR__, 2) . '/../public/includes/auth.php';
        self::$apiKey = createApiKey('test-suite-key');
    }

    public function testCreateApiKeyFormat(): void
    {
        $key = createApiKey('format-test');
        $this->assertStringStartsWith('gkob_', $key);
        $this->assertGreaterThanOrEqual(50, strlen($key));
    }

    public function testValidateApiKeyViaHeader(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = self::$apiKey;
        $key = validateApiKey();
        $this->assertIsArray($key);
        $this->assertArrayHasKey('id', $key);
        $this->assertArrayHasKey('api_key', $key);
        $this->assertSame(self::$apiKey, $key['api_key']);
    }

    public function testValidateApiKeyViaGet(): void
    {
        $_GET['api_key'] = self::$apiKey;
        $key = validateApiKey();
        $this->assertNotNull($key);
        $this->assertSame(self::$apiKey, $key['api_key']);
    }

    public function testValidateApiKeyReturnsNullForInvalid(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'gkob_invalidkey123';
        $this->assertNull(validateApiKey());
    }

    public function testValidateApiKeyReturnsNullWhenMissing(): void
    {
        unset($_SERVER['HTTP_X_API_KEY']);
        $_GET = [];
        $_POST = [];
        $this->assertNull(validateApiKey());
    }

    public function testAttemptLoginWithDefaultAdmin(): void
    {
        $ok = attemptLogin('admin', 'go0dp4ssw0rd');
        $this->assertTrue($ok);
        $this->assertArrayHasKey('admin_id', $_SESSION);
        $this->assertArrayHasKey('admin_username', $_SESSION);
        $this->assertSame('admin', $_SESSION['admin_username']);
    }

    public function testAttemptLoginFailsWithWrongPassword(): void
    {
        $ok = attemptLogin('admin', 'wrongpassword');
        $this->assertFalse($ok);
    }

    public function testAttemptLoginFailsWithWrongUser(): void
    {
        $ok = attemptLogin('nonexistent', 'go0dp4ssw0rd');
        $this->assertFalse($ok);
    }

    public function testIsLoggedInAfterLogin(): void
    {
        attemptLogin('admin', 'go0dp4ssw0rd');
        $this->assertTrue(isLoggedIn());
    }

    public function testIsLoggedInWhenNotLoggedIn(): void
    {
        $this->assertFalse(isLoggedIn());
    }

    public function testLogout(): void
    {
        attemptLogin('admin', 'go0dp4ssw0rd');
        $this->assertTrue(isLoggedIn());
        logout();
        $this->assertFalse(isLoggedIn());
    }

    public function testChangePassword(): void
    {
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM admin_users WHERE username = :u');
        $stmt->bindValue(':u', 'admin', SQLITE3_TEXT);
        $result = $stmt->execute();
        $row = $result->fetchArray(SQLITE3_ASSOC);
        $this->assertNotNull($row);
        $ok = changePassword((int) $row['id'], 'newpass123');
        $this->assertTrue($ok);
        $this->assertTrue(attemptLogin('admin', 'newpass123'));
        changePassword((int) $row['id'], 'go0dp4ssw0rd');
    }

    public function testCheckRateLimitAllowsUnderLimit(): void
    {
        $this->assertTrue(checkRateLimit('test_ip_' . bin2hex(random_bytes(4))));
    }

    public function testGenerateApiKeyPrefix(): void
    {
        $key = generateApiKey();
        $this->assertStringStartsWith('gkob_', $key);
    }
}
