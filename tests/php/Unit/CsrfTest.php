<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CSRF token generation and validation.
 */
class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        $_SESSION = [];
        $_POST = [];
        require_once dirname(__DIR__, 2) . '/../public/includes/csrf.php';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
    }

    public function testGenerateCsrfToken(): void
    {
        $token = generateCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertSame($token, generateCsrfToken());
    }

    public function testCsrfFieldContainsHiddenInput(): void
    {
        $field = csrfField();
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString('value="', $field);
        $this->assertStringContainsString('type="hidden"', $field);
    }

    public function testValidateCsrfTokenWithValidToken(): void
    {
        $token = generateCsrfToken();
        $_POST['csrf_token'] = $token;
        $this->assertTrue(validateCsrfToken());
    }

    public function testValidateCsrfTokenWithInvalidToken(): void
    {
        generateCsrfToken();
        $_POST['csrf_token'] = 'invalid';
        $this->assertFalse(validateCsrfToken());
    }

    public function testValidateCsrfTokenWithMissingToken(): void
    {
        generateCsrfToken();
        unset($_POST['csrf_token']);
        $this->assertFalse(validateCsrfToken());
    }
}
