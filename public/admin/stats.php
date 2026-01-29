<?php
/**
 * Ghost Kitchen Order Board - Statistics Dashboard
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$stats = getOrderStats();

// Get API usage stats
$db = getDB();
$apiUsage = [];
$result = $db->query("SELECT endpoint, SUM(requests) as total_requests, SUM(errors) as total_errors 
                       FROM stats_api_usage 
                       WHERE date >= date('now', '-7 days')
                       GROUP BY endpoint 
                       ORDER BY total_requests DESC");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $apiUsage[] = $row;
}

// Get order history (last 7 days)
$orderHistory = [];
$result = $db->query("SELECT DATE(archived_at) as date, COUNT(*) as count, AVG(wait_time_seconds) as avg_wait
                       FROM stats_order_history 
                       WHERE archived_at >= date('now', '-7 days')
                       GROUP BY DATE(archived_at)
                       ORDER BY date DESC");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $orderHistory[] = $row;
}

// Platform breakdown
$platformStats = [];
$result = $db->query("SELECT platform, COUNT(*) as count 
                       FROM stats_order_history 
                       WHERE archived_at >= date('now', '-7 days')
                       GROUP BY platform");
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $platformStats[$row['platform']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistics - Ghost Kitchen Order Board</title>
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
                <a class="nav-link active" href="stats.php">
                    <i class="bi bi-bar-chart me-1"></i>Stats
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid py-4">
        <h2 class="mb-4"><i class="bi bi-bar-chart me-2"></i>Statistics Dashboard</h2>
        
        <!-- Current Stats -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="stat-value"><?= $stats['active_orders'] ?></div>
                    <div class="stat-label">Active Orders</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="stat-value"><?= $stats['today_completed'] ?></div>
                    <div class="stat-label">Completed Today</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="stat-value">
                        <?php 
                        $avgWait = $stats['avg_wait_time'];
                        if ($avgWait > 0) {
                            echo floor($avgWait / 60) . 'm ' . ($avgWait % 60) . 's';
                        } else {
                            echo '—';
                        }
                        ?>
                    </div>
                    <div class="stat-label">Avg Wait Time (Today)</div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="stat-value"><?= $stats['ready'] ?? 0 ?></div>
                    <div class="stat-label">Ready for Pickup</div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Platform Breakdown -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-pie-chart me-2"></i>Orders by Platform (7 days)
                    </div>
                    <div class="card-body">
                        <?php if (empty($platformStats)): ?>
                            <p class="text-secondary text-center">No data available</p>
                        <?php else: ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><span class="badge badge-doordash">DoorDash</span></span>
                                    <strong><?= $platformStats['doordash'] ?? 0 ?></strong>
                                </div>
                                <div class="progress" style="height: 8px; background: var(--bg-input);">
                                    <?php 
                                    $total = array_sum($platformStats);
                                    $pct = $total > 0 ? (($platformStats['doordash'] ?? 0) / $total * 100) : 0;
                                    ?>
                                    <div class="progress-bar" style="width: <?= $pct ?>%; background: #ff3008;"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><span class="badge badge-ubereats">Uber Eats</span></span>
                                    <strong><?= $platformStats['ubereats'] ?? 0 ?></strong>
                                </div>
                                <div class="progress" style="height: 8px; background: var(--bg-input);">
                                    <?php $pct = $total > 0 ? (($platformStats['ubereats'] ?? 0) / $total * 100) : 0; ?>
                                    <div class="progress-bar" style="width: <?= $pct ?>%; background: #06c167;"></div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span><span class="badge badge-grubhub">Grubhub</span></span>
                                    <strong><?= $platformStats['grubhub'] ?? 0 ?></strong>
                                </div>
                                <div class="progress" style="height: 8px; background: var(--bg-input);">
                                    <?php $pct = $total > 0 ? (($platformStats['grubhub'] ?? 0) / $total * 100) : 0; ?>
                                    <div class="progress-bar" style="width: <?= $pct ?>%; background: #f63440;"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Order History -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-calendar3 me-2"></i>Daily Completed Orders (7 days)
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($orderHistory)): ?>
                            <p class="text-secondary text-center py-4">No data available</p>
                        <?php else: ?>
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th class="text-end">Orders</th>
                                        <th class="text-end">Avg Wait</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orderHistory as $day): ?>
                                        <tr>
                                            <td><?= date('M j', strtotime($day['date'])) ?></td>
                                            <td class="text-end"><?= $day['count'] ?></td>
                                            <td class="text-end">
                                                <?php 
                                                $wait = $day['avg_wait'];
                                                echo $wait ? floor($wait / 60) . 'm' : '—';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- API Usage -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-code-slash me-2"></i>API Usage (7 days)
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($apiUsage)): ?>
                            <p class="text-secondary text-center py-4">No API usage yet</p>
                        <?php else: ?>
                            <table class="table table-sm mb-0">
                                <thead>
                                    <tr>
                                        <th>Endpoint</th>
                                        <th class="text-end">Requests</th>
                                        <th class="text-end">Errors</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($apiUsage as $usage): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($usage['endpoint']) ?></code></td>
                                            <td class="text-end"><?= number_format($usage['total_requests']) ?></td>
                                            <td class="text-end">
                                                <?php if ($usage['total_errors'] > 0): ?>
                                                    <span class="text-danger"><?= $usage['total_errors'] ?></span>
                                                <?php else: ?>
                                                    0
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Current Active Orders by Platform -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-activity me-2"></i>Current Active Orders by Platform
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach (PLATFORMS as $platform): ?>
                                <div class="col-md-4 text-center">
                                    <div class="stat-value" style="font-size: 3rem;">
                                        <?= $stats['by_platform'][$platform] ?? 0 ?>
                                    </div>
                                    <span class="badge badge-<?= $platform ?>" style="font-size: 1rem;">
                                        <?= ucfirst($platform) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
