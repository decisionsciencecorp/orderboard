<?php
/**
 * Ghost Kitchen Order Board API - Get Single Order
 * 
 * GET /api/get-order.php
 * 
 * Query Parameters:
 *     api_key (required) - API key
 *     id (optional) - Order database ID
 *     order_id (optional) - Order ID string (one of id or order_id required)
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
trackApiUsage('get-order');

// Find order
$order = null;

if (isset($_GET['id'])) {
    $order = getOrderById((int)$_GET['id']);
} elseif (isset($_GET['order_id'])) {
    $order = getOrderByOrderId($_GET['order_id']);
} else {
    trackApiUsage('get-order', true);
    errorResponse('Missing required parameter: id or order_id');
}

if (!$order) {
    trackApiUsage('get-order', true);
    errorResponse('Order not found', 404);
}

jsonResponse([
    'success' => true,
    'order' => [
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
    ]
]);
