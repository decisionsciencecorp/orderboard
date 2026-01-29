<?php
/**
 * Ghost Kitchen Order Board API - Statistics
 * 
 * GET /api/stats.php
 * 
 * Query Parameters:
 *     api_key (required) - API key
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
trackApiUsage('stats');

try {
    $stats = getOrderStats();
    
    jsonResponse([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    trackApiUsage('stats', true);
    errorResponse('Internal server error', 500);
}
