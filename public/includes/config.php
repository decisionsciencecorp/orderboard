<?php
/**
 * Ghost Kitchen Order Board - Configuration
 * 
 * Database configuration and initialization for the order board system.
 * Following the Technonomicon pattern for LEMP stack applications.
 *
 * On production, set ORDERBOARD_BASE to the absolute path of the repo root
 * (the directory that contains db/ and public/). If only public/ is deployed,
 * set ORDERBOARD_BASE to a writable directory that will hold db/ and logs/.
 */

// Application base path: repo root or override via env (e.g. /var/www/orderboard)
$basePath = getenv('ORDERBOARD_BASE');
if ($basePath === false || $basePath === '') {
    $basePath = realpath(__DIR__ . '/../..') ?: (__DIR__ . '/../..');
}
$basePath = rtrim(str_replace('\\', '/', $basePath), '/');

// Error reporting (disable display in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$logsDir = $basePath . '/logs';
if (is_dir($logsDir) || @mkdir($logsDir, 0755, true)) {
    ini_set('error_log', $logsDir . '/error.log');
}

// Database configuration
define('DB_PATH', $basePath . '/db/orderboard.db');
define('DB_TIMEOUT', 30);

// Application settings
define('SITE_NAME', 'Ghost Kitchen Order Board');
define('SITE_URL', 'http://localhost:8000');
define('API_VERSION', '1.0.0');

// Security settings
define('SESSION_NAME', 'ghost_kitchen_orderboard');
define('SESSION_LIFETIME', 3600); // 1 hour
define('PASSWORD_COST', 12); // bcrypt cost

// Rate limiting (disabled by default for internal use; set to true if exposing API publicly)
define('RATE_LIMIT_ENABLED', false);
define('RATE_LIMIT_REQUESTS', 60);
define('RATE_LIMIT_WINDOW', 60); // seconds

// Order statuses
define('STATUS_PREPARING', 'preparing');
define('STATUS_READY', 'ready');

// Platforms
define('PLATFORMS', ['doordash', 'ubereats', 'grubhub']);

// Shelf locations
define('SHELF_LOCATIONS', ['A', 'B', 'C', 'D', 'E', 'F']);

// Display settings
define('DISPLAY_REFRESH_INTERVAL', 5000); // milliseconds
define('MAX_DISPLAY_ORDERS', 12); // max orders shown on display

/**
 * Show configuration/database error and exit (avoids 500 with no info)
 */
function showConfigError(string $message): void {
    if (php_sapi_name() === 'cli') {
        fwrite(STDERR, "Config error: $message\n");
        exit(1);
    }
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html><head><title>Service Unavailable</title></head><body>';
    echo '<h1>Service Unavailable</h1><p>' . htmlspecialchars($message) . '</p>';
    echo '<p>Check server logs and ensure <code>db/</code> and <code>logs/</code> exist and are writable. ';
    echo 'You can set <code>ORDERBOARD_BASE</code> to the app root path if needed.</p></body></html>';
    exit;
}

/**
 * Get database connection
 */
function getDB(): SQLite3 {
    static $db = null;
    
    if ($db === null) {
        $dbDir = dirname(DB_PATH);
        if (!is_dir($dbDir)) {
            if (!@mkdir($dbDir, 0755, true)) {
                showConfigError('Cannot create database directory: ' . $dbDir);
            }
        }
        if (!is_writable($dbDir)) {
            showConfigError('Database directory is not writable: ' . $dbDir);
        }
        
        try {
            $db = new SQLite3(DB_PATH);
        } catch (Throwable $e) {
            $log = ini_get('error_log');
            if ($log) {
                error_log('OrderBoard getDB: ' . $e->getMessage() . ' path=' . DB_PATH);
            }
            showConfigError('Database unavailable. Check logs and path: ' . DB_PATH);
        }
        if ($db === false) {
            showConfigError('Database unavailable: ' . DB_PATH);
        }
        $db->busyTimeout(DB_TIMEOUT * 1000);
        $db->exec('PRAGMA journal_mode = WAL');
        $db->exec('PRAGMA foreign_keys = ON');
        
        initializeDatabase($db);
    }
    
    return $db;
}

/**
 * Initialize database schema
 */
function initializeDatabase(SQLite3 $db): void {
    // Orders table
    $db->exec("
        CREATE TABLE IF NOT EXISTS orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT UNIQUE NOT NULL,
            customer_name TEXT NOT NULL,
            platform TEXT NOT NULL CHECK(platform IN ('doordash', 'ubereats', 'grubhub')),
            status TEXT NOT NULL DEFAULT 'preparing' CHECK(status IN ('preparing', 'ready')),
            shelf_location TEXT CHECK(shelf_location IN ('A', 'B', 'C', 'D', 'E', 'F') OR shelf_location IS NULL),
            notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            ready_at DATETIME,
            picked_up_at DATETIME
        )
    ");
    
    // Admin users table
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // API keys table
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_keys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            key_name TEXT NOT NULL,
            api_key TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_used DATETIME
        )
    ");
    
    // Rate limiting table
    $db->exec("
        CREATE TABLE IF NOT EXISTS api_rate_limits (
            rate_key TEXT PRIMARY KEY,
            window_start INTEGER NOT NULL,
            count INTEGER NOT NULL
        )
    ");
    
    // Statistics tables
    $db->exec("
        CREATE TABLE IF NOT EXISTS stats_api_usage (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            endpoint TEXT NOT NULL,
            requests INTEGER NOT NULL DEFAULT 0,
            errors INTEGER NOT NULL DEFAULT 0,
            date DATE NOT NULL,
            UNIQUE(endpoint, date)
        )
    ");
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS stats_order_history (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id TEXT NOT NULL,
            customer_name TEXT NOT NULL,
            platform TEXT NOT NULL,
            created_at DATETIME NOT NULL,
            ready_at DATETIME,
            picked_up_at DATETIME,
            wait_time_seconds INTEGER,
            archived_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    // Create indexes
    $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_platform ON orders(platform)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_orders_created ON orders(created_at)");
    
    // Create default admin user if none exists
    $result = $db->querySingle("SELECT COUNT(*) FROM admin_users");
    if ($result == 0) {
        $hash = password_hash('go0dp4ssw0rd', PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);
        $stmt = $db->prepare("INSERT INTO admin_users (username, password_hash) VALUES (:username, :hash)");
        $stmt->bindValue(':username', 'admin', SQLITE3_TEXT);
        $stmt->bindValue(':hash', $hash, SQLITE3_TEXT);
        $stmt->execute();
    }
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Error response helper
 */
function errorResponse(string $message, int $statusCode = 400): void {
    jsonResponse(['success' => false, 'error' => $message], $statusCode);
}

/**
 * Get request body as JSON
 */
function getJsonBody(): array {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

// Ensure logs directory exists (already set $logsDir above for error_log)
if (!empty($logsDir) && !is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}
