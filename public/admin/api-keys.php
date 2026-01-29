<?php
/**
 * Ghost Kitchen Order Board - API Key Management
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/functions.php';

requireLogin();

$message = '';
$error = '';
$newKey = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCsrfToken()) {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            $keyName = trim($_POST['key_name'] ?? '');
            if ($keyName) {
                $newKey = createApiKey($keyName);
                $message = "API key created successfully. Copy it now - it won't be shown again!";
            } else {
                $error = "Key name is required.";
            }
            break;
            
        case 'delete':
            $keyId = (int)($_POST['key_id'] ?? 0);
            if ($keyId) {
                $db = getDB();
                $stmt = $db->prepare("DELETE FROM api_keys WHERE id = :id");
                $stmt->bindValue(':id', $keyId, SQLITE3_INTEGER);
                if ($stmt->execute()) {
                    $message = "API key deleted successfully.";
                } else {
                    $error = "Failed to delete API key.";
                }
            }
            break;
    }
}

// Get all API keys
$db = getDB();
$result = $db->query("SELECT id, key_name, api_key, created_at, last_used FROM api_keys ORDER BY created_at DESC");
$apiKeys = [];
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $apiKeys[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>API Keys - Ghost Kitchen Order Board</title>
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
                <a class="nav-link active" href="api-keys.php">
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
    
    <div class="container py-4">
        <h2 class="mb-4"><i class="bi bi-key me-2"></i>API Key Management</h2>
        
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
        
        <?php if ($newKey): ?>
            <div class="alert alert-warning">
                <h5><i class="bi bi-exclamation-triangle me-2"></i>New API Key Created</h5>
                <p class="mb-2">Copy this key now. It will not be shown again!</p>
                <div class="api-key-display mb-2" id="newApiKey"><?= htmlspecialchars($newKey) ?></div>
                <button class="btn btn-sm btn-outline-light" onclick="copyToClipboard()">
                    <i class="bi bi-clipboard me-1"></i>Copy to Clipboard
                </button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Create API Key -->
            <div class="col-md-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-plus-circle me-2"></i>Create New API Key
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="create">
                            
                            <div class="mb-3">
                                <label for="key_name" class="form-label">Key Name</label>
                                <input type="text" class="form-control" id="key_name" name="key_name" 
                                       required placeholder="e.g., POS System, AI Agent">
                                <div class="form-text">A descriptive name to identify this key</div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg me-1"></i>Generate API Key
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Existing API Keys -->
            <div class="col-md-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-list-ul me-2"></i>Existing API Keys
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($apiKeys)): ?>
                            <div class="text-center py-5 text-secondary">
                                <i class="bi bi-key" style="font-size: 3rem;"></i>
                                <p class="mt-2">No API keys created yet</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Key (masked)</th>
                                            <th>Created</th>
                                            <th>Last Used</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($apiKeys as $key): ?>
                                            <tr>
                                                <td><strong><?= htmlspecialchars($key['key_name']) ?></strong></td>
                                                <td>
                                                    <code><?= substr($key['api_key'], 0, 12) ?>...<?= substr($key['api_key'], -4) ?></code>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($key['created_at'])) ?></td>
                                                <td>
                                                    <?php if ($key['last_used']): ?>
                                                        <?= date('M j, Y H:i', strtotime($key['last_used'])) ?>
                                                    <?php else: ?>
                                                        <span class="text-secondary">Never</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <form method="POST" action="" class="d-inline"
                                                          onsubmit="return confirm('Delete this API key? This cannot be undone.')">
                                                        <?= csrfField() ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="key_id" value="<?= $key['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- API Documentation -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-book me-2"></i>API Quick Reference
            </div>
            <div class="card-body">
                <p>Use your API key via header or parameter:</p>
                <pre class="bg-dark text-light p-3 rounded"><code>curl -X POST <?= SITE_URL ?>/api/create-order.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"customer_name": "John Doe", "platform": "doordash"}'</code></pre>
                
                <h6 class="mt-4">Available Endpoints:</h6>
                <ul>
                    <li><code>POST /api/create-order.php</code> - Create new order</li>
                    <li><code>POST /api/update-order.php</code> - Update order (status, shelf)</li>
                    <li><code>GET /api/list-orders.php</code> - List all orders</li>
                    <li><code>GET /api/get-order.php</code> - Get single order</li>
                    <li><code>DELETE /api/delete-order.php</code> - Remove order</li>
                    <li><code>GET /api/display.php</code> - Display feed (no auth)</li>
                    <li><code>GET /api/stats.php</code> - Order statistics</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard() {
            const keyText = document.getElementById('newApiKey').textContent;
            navigator.clipboard.writeText(keyText).then(() => {
                alert('API key copied to clipboard!');
            });
        }
    </script>
</body>
</html>
