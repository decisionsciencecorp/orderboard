<?php
/**
 * Ghost Kitchen Order Board API - Delete Order (Mark as Picked Up)
 * 
 * DELETE /api/delete-order.php
 * 
 * Request Body:
 * {
 *     "id": 1                // OR "order_id": "ORD-XXXX"
 * }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle OPTIONS for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    exit;
}

// Allow DELETE or POST (for clients that don't support DELETE)
if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
    errorResponse('Method not allowed', 405);
}

// Require API key
$apiKey = requireApiKey();
enforceRateLimit();

// Track API usage
trackApiUsage('delete-order');

// Get request data
$data = getJsonBody();

// Find order
$order = null;

if (isset($data['id'])) {
    $order = getOrderById((int)$data['id']);
} elseif (isset($data['order_id'])) {
    $order = getOrderByOrderId($data['order_id']);
} else {
    trackApiUsage('delete-order', true);
    errorResponse('Missing required field: id or order_id');
}

if (!$order) {
    trackApiUsage('delete-order', true);
    errorResponse('Order not found', 404);
}

try {
    $success = deleteOrder($order['id']);
    
    if ($success) {
        jsonResponse([
            'success' => true,
            'message' => 'Order removed (archived as picked up)',
            'order' => [
                'id' => $order['id'],
                'order_id' => $order['order_id'],
                'customer_name' => $order['customer_name']
            ]
        ]);
    } else {
        trackApiUsage('delete-order', true);
        errorResponse('Failed to delete order', 500);
    }
    
} catch (Exception $e) {
    trackApiUsage('delete-order', true);
    errorResponse('Internal server error', 500);
}
