<?php
/**
 * Ghost Kitchen Order Board API - Display Data
 * 
 * GET /api/display.php
 * 
 * Public endpoint for the driver-facing display board.
 * No API key required - this is the public display feed.
 * 
 * Returns orders formatted for the display board:
 * - NAME (formatted: FIRST NAME + LAST INITIAL, ALL CAPS)
 * - PLATFORM
 * - STATUS (preparing or ready)
 * - SHELF (location letter when ready)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle OPTIONS for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

try {
    $orders = getDisplayOrders();
    
    jsonResponse([
        'success' => true,
        'timestamp' => date('c'),
        'refresh_interval' => DISPLAY_REFRESH_INTERVAL,
        'orders' => $orders,
        'count' => count($orders)
    ]);
    
} catch (Exception $e) {
    errorResponse('Internal server error', 500);
}
