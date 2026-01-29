<?php
/**
 * Ghost Kitchen Order Board API - Create Order
 * 
 * POST /api/create-order.php
 * 
 * Request Body:
 * {
 *     "customer_name": "John Doe",       // Required
 *     "platform": "doordash",            // Required: doordash, ubereats, grubhub
 *     "order_id": "ORD-CUSTOM",          // Optional, auto-generated if empty
 *     "status": "preparing",             // Optional: preparing (default) or ready
 *     "shelf_location": "A",             // Optional: A-F
 *     "notes": "Extra sauce"             // Optional
 * }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle OPTIONS for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

// Require API key
$apiKey = requireApiKey();
enforceRateLimit();

// Track API usage
trackApiUsage('create-order');

// Get request data
$data = getJsonBody();

// Validate required fields
if (empty($data['customer_name'])) {
    trackApiUsage('create-order', true);
    errorResponse('Missing required field: customer_name');
}

if (empty($data['platform'])) {
    trackApiUsage('create-order', true);
    errorResponse('Missing required field: platform');
}

try {
    $order = createOrder($data);
    
    jsonResponse([
        'success' => true,
        'message' => 'Order created successfully',
        'order' => [
            'id' => $order['id'],
            'order_id' => $order['order_id'],
            'customer_name' => $order['customer_name'],
            'display_name' => formatCustomerName($order['customer_name']),
            'platform' => $order['platform'],
            'status' => $order['status'],
            'shelf_location' => $order['shelf_location'],
            'created_at' => $order['created_at']
        ]
    ], 201);
    
} catch (InvalidArgumentException $e) {
    trackApiUsage('create-order', true);
    errorResponse($e->getMessage(), 400);
} catch (Exception $e) {
    trackApiUsage('create-order', true);
    errorResponse('Internal server error', 500);
}
