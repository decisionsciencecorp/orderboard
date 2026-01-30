# Ghost Kitchen Order Board

A driver-facing order status board for ghost kitchens, built with PHP/SQLite following the LEMP stack pattern. Designed for AI agent integration with a REST API, Python SDK, and SMCP plugin.

[![License: AGPLv3](https://img.shields.io/badge/License-AGPLv3-blue.svg)](https://www.gnu.org/licenses/agpl-3.0)
[![PHP 7.4+](https://img.shields.io/badge/PHP-7.4+-blue.svg)](https://www.php.net/)
[![SQLite3](https://img.shields.io/badge/SQLite-3-green.svg)](https://www.sqlite.org/)

## Features

- **Driver Display**: Full-screen, auto-refreshing order board optimized for 8-10 ft viewing
- **Admin Panel**: Manual order management with Bootstrap UI
- **REST API**: Complete CRUD operations for programmatic access
- **Python SDK**: Zero-dependency Python client library
- **SMCP Plugin**: MCP-compatible plugin for AI agent integration
- **Platform Support**: DoorDash, Uber Eats, Grubhub with logo display
- **Statistics**: Order tracking, wait times, and API usage metrics

## Quick Start

### Prerequisites

- PHP 7.4 or higher with SQLite3 extension
- Web server (Nginx/Apache) or PHP built-in server

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/your-org/ghost-kitchen-orderboard.git
   cd ghost-kitchen-orderboard
   ```

2. Set permissions:
   ```bash
   mkdir -p db logs
   chmod 755 db/ logs/
   ```

3. Start development server:
   ```bash
   cd public
   php -S localhost:8000
   ```

4. Access the system:
   - **Display Board**: http://localhost:8000/display/
   - **Admin Panel**: http://localhost:8000/admin/
   - **Default Login**: `admin` / `go0dp4ssw0rd`

5. Create an API key in the admin panel for programmatic access.

## Architecture

```
ghost-kitchen-orderboard/
├── db/
│   └── orderboard.db           # SQLite database (auto-created)
├── docs/
│   └── api-documentation.md    # Full API documentation
├── public/
│   ├── admin/                  # Admin panel UI
│   │   ├── index.php           # Dashboard
│   │   ├── login.php           # Login page
│   │   ├── api-keys.php        # API key management
│   │   ├── stats.php           # Statistics dashboard
│   │   └── logout.php          # Logout handler
│   ├── api/                    # REST API endpoints
│   │   ├── create-order.php    # POST - Create order
│   │   ├── update-order.php    # POST/PUT - Update order
│   │   ├── list-orders.php     # GET - List orders
│   │   ├── get-order.php       # GET - Single order
│   │   ├── delete-order.php    # DELETE - Remove order
│   │   ├── display.php         # GET - Display feed (public)
│   │   └── stats.php           # GET - Statistics
│   ├── display/                # Driver-facing display
│   │   └── index.php           # Auto-refresh order board
│   ├── includes/               # PHP includes
│   │   ├── config.php          # Database & configuration
│   │   ├── auth.php            # Authentication
│   │   ├── csrf.php            # CSRF protection
│   │   └── functions.php       # Core functions
│   ├── css/                    # Stylesheets
│   ├── js/                     # JavaScript
│   └── img/                    # Platform logos
├── orderboard_sdk/             # Python SDK
├── smcp_plugin/                # SMCP MCP plugin
│   └── orderboard/
└── README.md
```

## Display Board

The driver-facing display is designed per industry standards (Wingstop, Five Guys, Chipotle pattern):

```
NAME        PLATFORM        STATUS          SHELF
─────────────────────────────────────────────────
TIMMY R     [DoorDash]      READY →         B
SARAH K     [Uber Eats]     PREPARING       —
ALEX M      [Grubhub]       PREPARING       —
```

**Visual Hierarchy:**
1. READY rows (highlighted)
2. Shelf letter (large, bold)
3. Platform logo (instant recognition)
4. Customer name (FIRST + LAST INITIAL)

## API Usage

### Authentication

All API endpoints (except `/api/display.php`) require an API key:

```bash
# Via header (recommended)
curl -H "X-API-Key: YOUR_API_KEY" http://localhost:8000/api/list-orders.php

# Via parameter
curl "http://localhost:8000/api/list-orders.php?api_key=YOUR_API_KEY"
```

### Create Order

```bash
curl -X POST http://localhost:8000/api/create-order.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_name": "John Doe",
    "platform": "doordash"
  }'
```

### Mark Order Ready

```bash
curl -X POST http://localhost:8000/api/update-order.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "order_id": "ORD-A1B2C3D4",
    "status": "ready",
    "shelf_location": "B"
  }'
```

### Remove Order (Picked Up)

```bash
curl -X DELETE http://localhost:8000/api/delete-order.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"order_id": "ORD-A1B2C3D4"}'
```

## Python SDK

```python
from orderboard_sdk import OrderBoardClient

client = OrderBoardClient(
    api_key="your_api_key",
    base_url="http://localhost:8000"
)

# Create order
order = client.create_order(
    customer_name="John Doe",
    platform="doordash"
)

# Mark ready
client.mark_ready(order['order_id'], shelf_location="A")

# List orders
for order in client.list_orders(status="ready"):
    print(f"{order['display_name']} - Shelf {order['shelf_location']}")

# Delete (picked up)
client.delete_order(order['order_id'])
```

## SMCP Plugin

For AI agent integration via Model Context Protocol:

```bash
# Install plugin
cp -r smcp_plugin/orderboard /path/to/smcp/plugins/

# Set environment
export ORDERBOARD_API_KEY="your_api_key"
export ORDERBOARD_BASE_URL="http://localhost:8000"

# Use via SMCP
python cli.py create-order --customer-name "John Doe" --platform doordash
python cli.py mark-ready --order-id ORD-A1B2C3D4 --shelf-location B
```

## Agent Rules

AI agents controlling the board should follow these rules:

### Agent MAY:
- Add new orders when notified of incoming deliveries
- Change `PREPARING` → `READY` status
- Assign shelf location when marking ready
- Remove orders when drivers confirm pickup

### Agent MAY NOT:
- Guess the delivery platform
- Guess order readiness status
- Change display ordering without instruction

## Database Schema

### orders Table
```sql
CREATE TABLE orders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    order_id TEXT UNIQUE NOT NULL,
    customer_name TEXT NOT NULL,
    platform TEXT NOT NULL CHECK(platform IN ('doordash', 'ubereats', 'grubhub')),
    status TEXT NOT NULL DEFAULT 'preparing' CHECK(status IN ('preparing', 'ready')),
    shelf_location TEXT CHECK(shelf_location IN ('A', 'B', 'C', 'D', 'E', 'F')),
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    ready_at DATETIME,
    picked_up_at DATETIME
);
```

## Configuration

### Environment Variables

Edit `public/includes/config.php`:

```php
define('SITE_URL', 'https://yourdomain.com');
define('DISPLAY_REFRESH_INTERVAL', 5000); // milliseconds
define('MAX_DISPLAY_ORDERS', 12);
define('RATE_LIMIT_REQUESTS', 60);
define('RATE_LIMIT_WINDOW', 60); // seconds
```

## Production Deployment

1. **Change default admin password** in admin panel
2. **Configure HTTPS** on your web server
3. **Set proper permissions**:
   ```bash
   chmod 755 db/ logs/
   chmod 644 public/includes/*.php
   ```
4. **Configure Nginx** to serve from `public/` directory
5. **Set up backups** for `db/orderboard.db`

## License

This project uses dual licensing:

- **Code**: GNU Affero General Public License v3.0 (AGPLv3)
- **Documentation**: Creative Commons Attribution-ShareAlike 4.0 (CC-BY-SA 4.0)

## Testing

Full test suite (unit, integration, E2E):

```bash
composer install
composer test                    # PHPUnit: unit + integration (53 tests; 10 HTTP tests skip if server down)
composer test -- --testsuite Unit
composer test -- --testsuite Integration

npm install && npx playwright install
npm run e2e                     # Playwright E2E (starts server automatically)
```

See [tests/README.md](tests/README.md) for details and coverage.

## Support

For issues or questions:
- Check this documentation
- Review PHP error logs in `logs/`
- Verify API key is valid and active
