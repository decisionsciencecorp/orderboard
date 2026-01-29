<?php
/**
 * Ghost Kitchen Order Board - CSRF Protection
 */

require_once __DIR__ . '/auth.php';

/**
 * Generate CSRF token
 */
function generateCsrfToken(): string {
    startSecureSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get CSRF hidden field for forms
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken()) . '">';
}

/**
 * Validate CSRF token
 */
function validateCsrfToken(): bool {
    startSecureSession();
    
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    
    if (!$token || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Require valid CSRF token
 */
function requireCsrfToken(): void {
    if (!validateCsrfToken()) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}
