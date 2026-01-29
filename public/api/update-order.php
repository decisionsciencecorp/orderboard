<?php
/**
 * Ghost Kitchen Order Board API - Update Order
 * 
 * POST/PUT /api/update-order.php
 * 
 * Request Body:
 * {
 *     "id": 1,                           // OR "order_id": "ORD-XXXX"
 *     "customer_name": "Jane Doe",       // Optional
 *     "platform": "ubereats",            // Optional
 *     "status": "ready",                 // Optional: preparing or ready
 *     "shelf_location": "B",             // Optional: A-F or null to clear
 *     "notes": "Updated notes"           // Optional
 * }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Handle OPTIONS for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
    exit;
}

// Only allow POST/PUT
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    errorResponse('Method not allowed', 405);
}

// Require API key
$apiKey = requireApiKey();
enforceRateLimit();

// Track API usage
trackApiUsage('update-order');

// Get request data
$data = getJsonBody();

// Find order by id or order_id
$order = null;
$id = null;

if (isset($data['id'])) {
    $id = (int)$data['id'];
    $order = getOrderById($id);
} elseif (isset($data['order_id'])) {
    $order = getOrderByOrderId($data['order_id']);
    if ($order) {
        $id = $order['id'];
    }
} else {
    trackApiUsage('update-order', true);
    errorResponse('Missing required field: id or order_id');
}

if (!$order) {
    trackApiUsage('update-order', true);
    errorResponse('Order not found', 404);
}

try {
    $updatedOrder = updateOrder($id, $data);
    
    jsonResponse([
        'success' => true,
        'message' => 'Order updated successfully',
        'order' => [
            'id' => $updatedOrder['id'],
            'order_id' => $updatedOrder['order_id'],
            'customer_name' => $updatedOrder['customer_name'],
            'display_name' => formatCustomerName($updatedOrder['customer_name']),
            'platform' => $updatedOrder['platform'],
            'status' => $updatedOrder['status'],
            'shelf_location' => $updatedOrder['shelf_location'],
            'updated_at' => $updatedOrder['updated_at']
        ]
    ]);
    
} catch (InvalidArgumentException $e) {
    trackApiUsage('update-order', true);
    errorResponse($e->getMessage(), 400);
} catch (Exception $e) {
    trackApiUsage('update-order', true);
    errorResponse('Internal server error', 500);
}
