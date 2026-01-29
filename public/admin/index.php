<?php
/**
 * Ghost Kitchen Order Board - Admin Dashboard
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

// Handle order actions
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken()) {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $order = createOrder([
                    'customer_name' => $_POST['customer_name'] ?? '',
                    'platform' => $_POST['platform'] ?? '',
                    'notes' => $_POST['notes'] ?? ''
                ]);
                $message = "Order {$order['order_id']} created successfully.";
                break;
                
            case 'mark_ready':
                $id = (int)($_POST['order_id'] ?? 0);
                $shelf = $_POST['shelf_location'] ?? '';
                if ($id && $shelf) {
                    markOrderReady($id, $shelf);
                    $message = "Order marked as ready at shelf $shelf.";
                }
                break;
                
            case 'delete':
                $id = (int)($_POST['order_id'] ?? 0);
                if ($id && deleteOrder($id)) {
                    $message = "Order removed (picked up).";
                }
                break;
                
            case 'update_status':
                $id = (int)($_POST['order_id'] ?? 0);
                $status = $_POST['status'] ?? '';
                if ($id) {
                    updateOrder($id, ['status' => $status]);
                    $message = "Order status updated.";
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get current orders and stats
$orders = listOrders();
$stats = getOrderStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Ghost Kitchen Order Board</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-grid-3x3-gap-fill me-2"></i>Ghost Kitchen Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/display/" target="_blank">
                    <i class="bi bi-display me-1"></i>View Display
                </a>
                <a class="nav-link" href="api-keys.php">
                    <i class="bi bi-key me-1"></i>API Keys
                </a>
                <a class="nav-link" href="stats.php">
                    <i class="bi bi-bar-chart me-1"></i>Stats
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid py-4">
        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Stats Cards -->
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="stat-value"><?= $stats['active_orders'] ?></div>
                    <div class="stat-label">Active Orders</div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="stat-value"><?= $stats['preparing'] ?? 0 ?></div>
                    <div class="stat-label">Preparing</div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="stat-value" style="color: var(--accent-success)"><?= $stats['ready'] ?? 0 ?></div>
                    <div class="stat-label">Ready</div>
                </div>
            </div>
            <div class="col-md-3 mb-4">
                <div class="card stat-card">
                    <div class="stat-value"><?= $stats['today_completed'] ?></div>
                    <div class="stat-label">Completed Today</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- New Order Form -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-plus-circle me-2"></i>New Order
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="create">
                            
                            <div class="mb-3">
                                <label for="customer_name" class="form-label">Customer Name</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" 
                                       required placeholder="e.g., John Doe">
                                <div class="form-text">Will display as: JOHN D</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="platform" class="form-label">Platform</label>
                                <select class="form-select" id="platform" name="platform" required>
                                    <option value="">Select platform...</option>
                                    <option value="doordash">DoorDash</option>
                                    <option value="ubereats">Uber Eats</option>
                                    <option value="grubhub">Grubhub</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes (optional)</label>
                                <input type="text" class="form-control" id="notes" name="notes" 
                                       placeholder="Internal notes">
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg me-1"></i>Create Order
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Orders List -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-list-ul me-2"></i>Active Orders</span>
                        <span class="badge bg-secondary"><?= count($orders) ?> orders</span>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-5 text-secondary">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">No active orders</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Order ID</th>
                                            <th>Customer</th>
                                            <th>Platform</th>
                                            <th>Status</th>
                                            <th>Shelf</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($orders as $order): ?>
                                            <tr>
                                                <td>
                                                    <code><?= htmlspecialchars($order['order_id']) ?></code>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars(formatCustomerName($order['customer_name'])) ?></strong>
                                                    <br><small class="text-secondary"><?= htmlspecialchars($order['customer_name']) ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= $order['platform'] ?>">
                                                        <?= ucfirst($order['platform']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge badge-<?= $order['status'] ?>">
                                                        <?= strtoupper($order['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($order['shelf_location']): ?>
                                                        <span class="shelf-badge"><?= $order['shelf_location'] ?></span>
                                                    <?php else: ?>
                                                        <span class="text-secondary">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="order-actions">
                                                    <?php if ($order['status'] === 'preparing'): ?>
                                                        <!-- Mark Ready Modal Trigger -->
                                                        <button type="button" class="btn btn-sm btn-success" 
                                                                data-bs-toggle="modal" 
                                                                data-bs-target="#readyModal<?= $order['id'] ?>">
                                                            <i class="bi bi-check-lg"></i> Ready
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Delete Button -->
                                                    <form method="POST" action="" class="d-inline" 
                                                          onsubmit="return confirm('Remove this order (mark as picked up)?')">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-x-lg"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                            
                                            <!-- Mark Ready Modal -->
                                            <?php if ($order['status'] === 'preparing'): ?>
                                            <div class="modal fade" id="readyModal<?= $order['id'] ?>" tabindex="-1">
                                                <div class="modal-dialog modal-sm">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Mark Ready</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <form method="POST" action="">
                                                            <?= csrfField() ?>
                                                            <input type="hidden" name="action" value="mark_ready">
                                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                            <div class="modal-body">
                                                                <p><strong><?= htmlspecialchars(formatCustomerName($order['customer_name'])) ?></strong></p>
                                                                <label class="form-label">Select Shelf Location</label>
                                                                <div class="d-flex gap-2 flex-wrap">
                                                                    <?php foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $shelf): ?>
                                                                        <div class="form-check">
                                                                            <input class="form-check-input" type="radio" 
                                                                                   name="shelf_location" value="<?= $shelf ?>" 
                                                                                   id="shelf<?= $order['id'] . $shelf ?>" required>
                                                                            <label class="form-check-label shelf-badge" 
                                                                                   for="shelf<?= $order['id'] . $shelf ?>"
                                                                                   style="background: var(--bg-input); cursor: pointer;">
                                                                                <?= $shelf ?>
                                                                            </label>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="bi bi-check-lg"></i> Mark Ready
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh page every 30 seconds
        setTimeout(() => location.reload(), 30000);
    </script>
</body>
</html>
