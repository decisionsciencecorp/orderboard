<?php
/**
 * Ghost Kitchen Order Board - Authentication
 * 
 * Session-based auth for admin UI + API key auth for programmatic access.
 */

require_once __DIR__ . '/config.php';

/**
 * Start secure session
 */
function startSecureSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool {
    startSecureSession();
    return isset($_SESSION['admin_id']) && isset($_SESSION['admin_username']);
}

/**
 * Require login for admin pages
 */
function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Attempt admin login
 */
function attemptLogin(string $username, string $password): bool {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = :username");
    $stmt->bindValue(':username', $username, SQLITE3_TEXT);
    $result = $stmt->execute();
    $user = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($user && password_verify($password, $user['password_hash'])) {
        startSecureSession();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        $_SESSION['login_time'] = time();
        session_write_close();
        return true;
    }
    
    return false;
}

/**
 * Logout admin user
 */
function logout(): void {
    startSecureSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

/**
 * Validate API key
 */
function validateApiKey(): ?array {
    $apiKey = null;
    
    // Check header first
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'];
    }
    // Then POST body
    elseif (isset($_POST['api_key'])) {
        $apiKey = $_POST['api_key'];
    }
    // Then GET parameter
    elseif (isset($_GET['api_key'])) {
        $apiKey = $_GET['api_key'];
    }
    // Check JSON body
    else {
        $body = getJsonBody();
        if (isset($body['api_key'])) {
            $apiKey = $body['api_key'];
        }
    }
    
    if (!$apiKey) {
        return null;
    }
    
    $db = getDB();
    $stmt = $db->prepare("SELECT id, key_name, api_key FROM api_keys WHERE api_key = :key");
    $stmt->bindValue(':key', $apiKey, SQLITE3_TEXT);
    $result = $stmt->execute();
    $key = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($key) {
        // Update last used timestamp
        $updateStmt = $db->prepare("UPDATE api_keys SET last_used = CURRENT_TIMESTAMP WHERE id = :id");
        $updateStmt->bindValue(':id', $key['id'], SQLITE3_INTEGER);
        $updateStmt->execute();
        
        return $key;
    }
    
    return null;
}

/**
 * Require valid API key for API endpoints
 */
function requireApiKey(): array {
    $key = validateApiKey();
    if (!$key) {
        errorResponse('Invalid or missing API key', 401);
    }
    return $key;
}

/**
 * Check rate limit (no-op when RATE_LIMIT_ENABLED is false)
 */
function checkRateLimit(string $identifier): bool {
    if (!RATE_LIMIT_ENABLED) {
        return true;
    }
    $db = getDB();
    $now = time();
    $windowStart = $now - RATE_LIMIT_WINDOW;
    
    // Clean old entries
    $db->exec("DELETE FROM api_rate_limits WHERE window_start < " . $windowStart);
    
    $stmt = $db->prepare("SELECT count FROM api_rate_limits WHERE rate_key = :key AND window_start >= :start");
    $stmt->bindValue(':key', $identifier, SQLITE3_TEXT);
    $stmt->bindValue(':start', $windowStart, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $row = $result->fetchArray(SQLITE3_ASSOC);
    
    if ($row && $row['count'] >= RATE_LIMIT_REQUESTS) {
        return false;
    }
    
    // Update or insert rate limit record
    $db->exec("INSERT OR REPLACE INTO api_rate_limits (rate_key, window_start, count) 
               VALUES ('$identifier', $now, COALESCE((SELECT count + 1 FROM api_rate_limits WHERE rate_key = '$identifier'), 1))");
    
    return true;
}

/**
 * Enforce rate limit (no-op when RATE_LIMIT_ENABLED is false)
 */
function enforceRateLimit(): void {
    if (!RATE_LIMIT_ENABLED) {
        return;
    }
    $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Also use API key if available
    $apiKey = validateApiKey();
    if ($apiKey) {
        $identifier = 'apikey_' . $apiKey['id'];
    }
    
    if (!checkRateLimit($identifier)) {
        errorResponse('Rate limit exceeded', 429);
    }
}

/**
 * Generate secure API key
 */
function generateApiKey(): string {
    return 'gkob_' . bin2hex(random_bytes(24));
}

/**
 * Create new API key
 */
function createApiKey(string $name): string {
    $db = getDB();
    $key = generateApiKey();
    
    $stmt = $db->prepare("INSERT INTO api_keys (key_name, api_key) VALUES (:name, :key)");
    $stmt->bindValue(':name', $name, SQLITE3_TEXT);
    $stmt->bindValue(':key', $key, SQLITE3_TEXT);
    $stmt->execute();
    
    return $key;
}

/**
 * Change admin password
 */
function changePassword(int $userId, string $newPassword): bool {
    $db = getDB();
    $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
    
    $stmt = $db->prepare("UPDATE admin_users SET password_hash = :hash WHERE id = :id");
    $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
    $stmt->bindValue(':id', $userId, SQLITE3_INTEGER);
    
    return $stmt->execute() !== false;
}
