<?php
/**
 * Ghost Kitchen Order Board - Core Functions
 * 
 * CRUD operations and helpers for order management.
 */

require_once __DIR__ . '/config.php';

/**
 * Generate unique order ID
 */
function generateOrderId(): string {
    return 'ORD-' . strtoupper(bin2hex(random_bytes(4)));
}

/**
 * Format customer name for display (FIRST NAME + LAST INITIAL, ALL CAPS)
 */
function formatCustomerName(string $name): string {
    $name = trim($name);
    $parts = preg_split('/\s+/', $name);
    
    if (count($parts) >= 2) {
        $firstName = $parts[0];
        $lastInitial = strtoupper(substr(end($parts), 0, 1));
        return strtoupper($firstName) . ' ' . $lastInitial;
    }
    
    return strtoupper($name);
}

/**
 * Validate platform
 */
function isValidPlatform(string $platform): bool {
    return in_array(strtolower($platform), PLATFORMS);
}

/**
 * Validate shelf location
 */
function isValidShelfLocation(?string $location): bool {
    if ($location === null || $location === '') {
        return true;
    }
    return in_array(strtoupper($location), SHELF_LOCATIONS);
}

/**
 * Create new order
 */
function createOrder(array $data): array {
    $db = getDB();
    
    // Validate required fields
    if (empty($data['customer_name'])) {
        throw new InvalidArgumentException('Customer name is required');
    }
    if (empty($data['platform']) || !isValidPlatform($data['platform'])) {
        throw new InvalidArgumentException('Valid platform is required (doordash, ubereats, grubhub)');
    }
    
    $orderId = $data['order_id'] ?? generateOrderId();
    $platform = strtolower($data['platform']);
    $status = $data['status'] ?? STATUS_PREPARING;
    $shelfLocation = isset($data['shelf_location']) ? strtoupper($data['shelf_location']) : null;
    
    if ($shelfLocation && !isValidShelfLocation($shelfLocation)) {
        throw new InvalidArgumentException('Invalid shelf location (A-F)');
    }
    
    $stmt = $db->prepare("
        INSERT INTO orders (order_id, customer_name, platform, status, shelf_location, notes)
        VALUES (:order_id, :customer_name, :platform, :status, :shelf_location, :notes)
    ");
    
    $stmt->bindValue(':order_id', $orderId, SQLITE3_TEXT);
    $stmt->bindValue(':customer_name', $data['customer_name'], SQLITE3_TEXT);
    $stmt->bindValue(':platform', $platform, SQLITE3_TEXT);
    $stmt->bindValue(':status', $status, SQLITE3_TEXT);
    $stmt->bindValue(':shelf_location', $shelfLocation, SQLITE3_TEXT);
    $stmt->bindValue(':notes', $data['notes'] ?? null, SQLITE3_TEXT);
    
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to create order');
    }
    
    $id = $db->lastInsertRowID();
    return getOrderById($id);
}

/**
 * Get order by ID
 */
function getOrderById(int $id): ?array {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = :id");
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    $result = $stmt->execute();
    $order = $result->fetchArray(SQLITE3_ASSOC);
    
    return $order ?: null;
}

/**
 * Get order by order_id
 */
function getOrderByOrderId(string $orderId): ?array {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM orders WHERE order_id = :order_id");
    $stmt->bindValue(':order_id', $orderId, SQLITE3_TEXT);
    $result = $stmt->execute();
    $order = $result->fetchArray(SQLITE3_ASSOC);
    
    return $order ?: null;
}

/**
 * Update order
 */
function updateOrder(int $id, array $data): array {
    $db = getDB();
    
    $order = getOrderById($id);
    if (!$order) {
        throw new InvalidArgumentException('Order not found');
    }
    
    $updates = [];
    $params = [];
    
    if (isset($data['customer_name'])) {
        $updates[] = 'customer_name = :customer_name';
        $params[':customer_name'] = $data['customer_name'];
    }
    
    if (isset($data['platform'])) {
        if (!isValidPlatform($data['platform'])) {
            throw new InvalidArgumentException('Invalid platform');
        }
        $updates[] = 'platform = :platform';
        $params[':platform'] = strtolower($data['platform']);
    }
    
    if (isset($data['status'])) {
        $status = strtolower($data['status']);
        if (!in_array($status, [STATUS_PREPARING, STATUS_READY])) {
            throw new InvalidArgumentException('Invalid status (preparing or ready)');
        }
        $updates[] = 'status = :status';
        $params[':status'] = $status;
        
        // Set ready_at timestamp when status changes to ready
        if ($status === STATUS_READY && $order['status'] !== STATUS_READY) {
            $updates[] = 'ready_at = CURRENT_TIMESTAMP';
        }
    }
    
    if (array_key_exists('shelf_location', $data)) {
        $location = $data['shelf_location'] ? strtoupper($data['shelf_location']) : null;
        if ($location && !isValidShelfLocation($location)) {
            throw new InvalidArgumentException('Invalid shelf location (A-F)');
        }
        $updates[] = 'shelf_location = :shelf_location';
        $params[':shelf_location'] = $location;
    }
    
    if (isset($data['notes'])) {
        $updates[] = 'notes = :notes';
        $params[':notes'] = $data['notes'];
    }
    
    if (empty($updates)) {
        return $order;
    }
    
    $updates[] = 'updated_at = CURRENT_TIMESTAMP';
    
    $sql = "UPDATE orders SET " . implode(', ', $updates) . " WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindValue(':id', $id, SQLITE3_INTEGER);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? SQLITE3_INTEGER : SQLITE3_TEXT);
    }
    
    if (!$stmt->execute()) {
        throw new RuntimeException('Failed to update order');
    }
    
    return getOrderById($id);
}

/**
 * Mark order as ready with shelf location
 */
function markOrderReady(int $id, string $shelfLocation): array {
    return updateOrder($id, [
        'status' => STATUS_READY,
        'shelf_location' => $shelfLocation
    ]);
}

/**
 * Delete/archive order (mark as picked up)
 */
function deleteOrder(int $id): bool {
    $db = getDB();
    
    $order = getOrderById($id);
    if (!$order) {
        return false;
    }
    
    // Archive to history
    $stmt = $db->prepare("
        INSERT INTO stats_order_history (order_id, customer_name, platform, created_at, ready_at, picked_up_at, wait_time_seconds)
        VALUES (:order_id, :customer_name, :platform, :created_at, :ready_at, CURRENT_TIMESTAMP, 
                CASE WHEN :ready_at IS NOT NULL THEN strftime('%s', 'now') - strftime('%s', :ready_at) ELSE NULL END)
    ");
    
    $stmt->bindValue(':order_id', $order['order_id'], SQLITE3_TEXT);
    $stmt->bindValue(':customer_name', $order['customer_name'], SQLITE3_TEXT);
    $stmt->bindValue(':platform', $order['platform'], SQLITE3_TEXT);
    $stmt->bindValue(':created_at', $order['created_at'], SQLITE3_TEXT);
    $stmt->bindValue(':ready_at', $order['ready_at'], SQLITE3_TEXT);
    $stmt->execute();
    
    // Delete from active orders
    $deleteStmt = $db->prepare("DELETE FROM orders WHERE id = :id");
    $deleteStmt->bindValue(':id', $id, SQLITE3_INTEGER);
    
    return $deleteStmt->execute() !== false;
}

/**
 * List orders with optional filters
 */
function listOrders(array $filters = []): array {
    $db = getDB();
    
    $where = [];
    $params = [];
    
    if (isset($filters['status'])) {
        $where[] = 'status = :status';
        $params[':status'] = strtolower($filters['status']);
    }
    
    if (isset($filters['platform'])) {
        $where[] = 'platform = :platform';
        $params[':platform'] = strtolower($filters['platform']);
    }
    
    $sql = "SELECT * FROM orders";
    if (!empty($where)) {
        $sql .= " WHERE " . implode(' AND ', $where);
    }
    
    // Order: READY orders first, then by created_at
    $sql .= " ORDER BY CASE WHEN status = 'ready' THEN 0 ELSE 1 END, created_at DESC";
    
    if (isset($filters['limit'])) {
        $sql .= " LIMIT " . (int)$filters['limit'];
    }
    
    if (isset($filters['offset'])) {
        $sql .= " OFFSET " . (int)$filters['offset'];
    }
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, SQLITE3_TEXT);
    }
    
    $result = $stmt->execute();
    $orders = [];
    
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $orders[] = $row;
    }
    
    return $orders;
}

/**
 * Get orders for display board (formatted for driver view)
 */
function getDisplayOrders(): array {
    $orders = listOrders(['limit' => MAX_DISPLAY_ORDERS]);
    
    $displayOrders = [];
    foreach ($orders as $order) {
        $displayOrders[] = [
            'id' => $order['id'],
            'order_id' => $order['order_id'],
            'name' => formatCustomerName($order['customer_name']),
            'platform' => $order['platform'],
            'status' => $order['status'],
            'shelf' => $order['shelf_location'],
            'created_at' => $order['created_at']
        ];
    }
    
    return $displayOrders;
}

/**
 * Get order statistics
 */
function getOrderStats(): array {
    $db = getDB();
    
    $stats = [
        'active_orders' => 0,
        'preparing' => 0,
        'ready' => 0,
        'by_platform' => [],
        'today_completed' => 0,
        'avg_wait_time' => 0
    ];
    
    // Active orders count
    $result = $db->query("SELECT COUNT(*) as count FROM orders");
    $stats['active_orders'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // By status
    $result = $db->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $stats[$row['status']] = $row['count'];
    }
    
    // By platform
    $result = $db->query("SELECT platform, COUNT(*) as count FROM orders GROUP BY platform");
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $stats['by_platform'][$row['platform']] = $row['count'];
    }
    
    // Today's completed orders
    $result = $db->query("SELECT COUNT(*) as count FROM stats_order_history WHERE DATE(archived_at) = DATE('now')");
    $stats['today_completed'] = $result->fetchArray(SQLITE3_ASSOC)['count'];
    
    // Average wait time (seconds)
    $result = $db->query("SELECT AVG(wait_time_seconds) as avg FROM stats_order_history WHERE wait_time_seconds IS NOT NULL AND DATE(archived_at) = DATE('now')");
    $row = $result->fetchArray(SQLITE3_ASSOC);
    $stats['avg_wait_time'] = $row['avg'] ? round($row['avg']) : 0;
    
    return $stats;
}

/**
 * Track API usage
 */
function trackApiUsage(string $endpoint, bool $error = false): void {
    $db = getDB();
    
    $today = date('Y-m-d');
    $errVal = $error ? 1 : 0;
    
    $db->exec("
        INSERT INTO stats_api_usage (endpoint, requests, errors, date)
        VALUES ('" . $db->escapeString($endpoint) . "', 1, $errVal, '" . $db->escapeString($today) . "')
        ON CONFLICT(endpoint, date) DO UPDATE SET
            requests = requests + 1,
            errors = errors + $errVal
    ");
}
