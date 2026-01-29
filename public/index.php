<?php
/**
 * Ghost Kitchen Order Board - Homepage
 * 
 * Redirects to display board or admin based on context.
 */

// Check if this looks like an admin/API request
$requestUri = $_SERVER['REQUEST_URI'] ?? '';

if (strpos($requestUri, '/admin') === 0) {
    header('Location: /admin/');
    exit;
}

if (strpos($requestUri, '/api') === 0) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API endpoint not found']);
    exit;
}

// Default: redirect to display board
header('Location: /display/');
exit;
