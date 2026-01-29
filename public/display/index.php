<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="refresh" content="300"> <!-- Fallback refresh every 5 minutes -->
    <title>Order Board - Ghost Kitchen</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom Display Styles -->
    <link href="/css/display.css" rel="stylesheet">
    
    <!-- Prevent screen sleep/dim on kiosk displays -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
</head>
<body>
    <!-- Header -->
    <header class="display-header">
        <h1>ORDER PICKUP</h1>
        <div class="d-flex align-items-center gap-4">
            <div class="status-indicator">
                <span class="status-dot"></span>
                <span>LIVE</span>
            </div>
            <div class="display-clock" id="clock">--:--:-- --</div>
        </div>
    </header>
    
    <!-- Column Headers -->
    <div class="column-headers">
        <div>NAME</div>
        <div class="text-center">PLATFORM</div>
        <div>STATUS</div>
        <div class="text-center">SHELF</div>
    </div>
    
    <!-- Orders Container -->
    <div class="orders-container" id="orders-container">
        <div class="empty-state">
            <div class="icon">📋</div>
            <div>Loading orders...</div>
        </div>
    </div>
    
    <!-- Display JavaScript -->
    <script src="/js/display.js"></script>
</body>
</html>
