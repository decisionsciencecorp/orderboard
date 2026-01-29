<?php
/**
 * Ghost Kitchen Order Board API - List Orders
 * 
 * GET /api/list-orders.php
 * 
 * Query Parameters:
 *     api_key (required) - API key
 *     status (optional) - Filter by status (preparing, ready)
 *     platform (optional) - Filter by platform (doordash, ubereats, grubhub)
 *     limit (optional) - Limit results
 *     offset (optional) - Pagination offset
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle OPTIONS for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    exit;
}

// Only allow GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    errorResponse('Method not allowed', 405);
}

// Require API key
$apiKey = requireApiKey();
enforceRateLimit();

// Track API usage
trackApiUsage('list-orders');

// Build filters
$filters = [];

if (isset($_GET['status']) && !empty($_GET['status'])) {
    $filters['status'] = $_GET['status'];
}

if (isset($_GET['platform']) && !empty($_GET['platform'])) {
    $filters['platform'] = $_GET['platform'];
}

if (isset($_GET['limit'])) {
    $filters['limit'] = max(1, min(100, (int)$_GET['limit']));
}

if (isset($_GET['offset'])) {
    $filters['offset'] = max(0, (int)$_GET['offset']);
}

try {
    $orders = listOrders($filters);
    
    $formattedOrders = [];
    foreach ($orders as $order) {
        $formattedOrders[] = [
            'id' => $order['id'],
            'order_id' => $order['order_id'],
            'customer_name' => $order['customer_name'],
            'display_name' => formatCustomerName($order['customer_name']),
            'platform' => $order['platform'],
            'status' => $order['status'],
            'shelf_location' => $order['shelf_location'],
            'notes' => $order['notes'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at'],
            'ready_at' => $order['ready_at']
        ];
    }
    
    jsonResponse([
        'success' => true,
        'orders' => $formattedOrders,
        'count' => count($formattedOrders)
    ]);
    
} catch (Exception $e) {
    trackApiUsage('list-orders', true);
    errorResponse('Internal server error', 500);
}
