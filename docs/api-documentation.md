# Ghost Kitchen Order Board - API Documentation

Complete API reference for the Ghost Kitchen Order Board system.

## Overview

- **Base URL**: `http://localhost:8000/api/`
- **Authentication**: API key via header or parameter
- **Rate Limit**: 60 requests/minute per IP or API key
- **Response Format**: JSON

## Authentication

All endpoints except `/api/display.php` require an API key.

### Methods

1. **HTTP Header** (recommended):
   ```
   X-API-Key: YOUR_API_KEY
   ```

2. **Query Parameter**:
   ```
   ?api_key=YOUR_API_KEY
   ```

3. **POST Body**:
   ```json
   {"api_key": "YOUR_API_KEY", ...}
   ```

## Endpoints

### Create Order

**POST** `/api/create-order.php`

Create a new order on the board.

#### Request

```json
{
    "customer_name": "John Doe",        // Required
    "platform": "doordash",             // Required: doordash, ubereats, grubhub
    "order_id": "CUSTOM-123",           // Optional: auto-generated if empty
    "status": "preparing",              // Optional: preparing (default) or ready
    "shelf_location": "A",              // Optional: A-F
    "notes": "Extra napkins"            // Optional
}
```

#### Response (201 Created)

```json
{
    "success": true,
    "message": "Order created successfully",
    "order": {
        "id": 1,
        "order_id": "ORD-A1B2C3D4",
        "customer_name": "John Doe",
        "display_name": "JOHN D",
        "platform": "doordash",
        "status": "preparing",
        "shelf_location": null,
        "created_at": "2026-01-29 12:00:00"
    }
}
```

---

### Update Order

**POST/PUT** `/api/update-order.php`

Update an existing order.

#### Request

```json
{
    "order_id": "ORD-A1B2C3D4",         // OR "id": 1
    "status": "ready",                   // Optional
    "shelf_location": "B",               // Optional
    "customer_name": "Jane Doe",         // Optional
    "notes": "Updated notes"             // Optional
}
```

#### Response (200 OK)

```json
{
    "success": true,
    "message": "Order updated successfully",
    "order": {
        "id": 1,
        "order_id": "ORD-A1B2C3D4",
        "customer_name": "Jane Doe",
        "display_name": "JANE D",
        "platform": "doordash",
        "status": "ready",
        "shelf_location": "B",
        "updated_at": "2026-01-29 12:05:00"
    }
}
```

---

### List Orders

**GET** `/api/list-orders.php`

List all active orders with optional filters.

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `status` | string | Filter by status: `preparing` or `ready` |
| `platform` | string | Filter by platform: `doordash`, `ubereats`, `grubhub` |
| `limit` | int | Maximum results (1-100) |
| `offset` | int | Pagination offset |

#### Example

```
GET /api/list-orders.php?status=ready&platform=doordash&limit=10
```

#### Response (200 OK)

```json
{
    "success": true,
    "orders": [
        {
            "id": 1,
            "order_id": "ORD-A1B2C3D4",
            "customer_name": "John Doe",
            "display_name": "JOHN D",
            "platform": "doordash",
            "status": "ready",
            "shelf_location": "B",
            "notes": null,
            "created_at": "2026-01-29 12:00:00",
            "updated_at": "2026-01-29 12:05:00",
            "ready_at": "2026-01-29 12:05:00"
        }
    ],
    "count": 1
}
```

---

### Get Order

**GET** `/api/get-order.php`

Get a single order by ID.

#### Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `order_id` | string | Order ID (e.g., "ORD-A1B2C3D4") |
| `id` | int | Database ID (alternative to order_id) |

#### Example

```
GET /api/get-order.php?order_id=ORD-A1B2C3D4
```

#### Response (200 OK)

```json
{
    "success": true,
    "order": {
        "id": 1,
        "order_id": "ORD-A1B2C3D4",
        "customer_name": "John Doe",
        "display_name": "JOHN D",
        "platform": "doordash",
        "status": "ready",
        "shelf_location": "B",
        "notes": null,
        "created_at": "2026-01-29 12:00:00",
        "updated_at": "2026-01-29 12:05:00",
        "ready_at": "2026-01-29 12:05:00"
    }
}
```

---

### Delete Order

**DELETE** `/api/delete-order.php`

Remove an order from the board (archives as picked up).

#### Request

```json
{
    "order_id": "ORD-A1B2C3D4"          // OR "id": 1
}
```

#### Response (200 OK)

```json
{
    "success": true,
    "message": "Order removed (archived as picked up)",
    "order": {
        "id": 1,
        "order_id": "ORD-A1B2C3D4",
        "customer_name": "John Doe"
    }
}
```

---

### Display Feed

**GET** `/api/display.php`

Get orders formatted for the driver display. **No authentication required.**

#### Response (200 OK)

```json
{
    "success": true,
    "timestamp": "2026-01-29T12:00:00+00:00",
    "refresh_interval": 5000,
    "orders": [
        {
            "id": 1,
            "order_id": "ORD-A1B2C3D4",
            "name": "JOHN D",
            "platform": "doordash",
            "status": "ready",
            "shelf": "B",
            "created_at": "2026-01-29 12:00:00"
        }
    ],
    "count": 1
}
```

---

### Statistics

**GET** `/api/stats.php`

Get order board statistics.

#### Response (200 OK)

```json
{
    "success": true,
    "stats": {
        "active_orders": 5,
        "preparing": 3,
        "ready": 2,
        "by_platform": {
            "doordash": 2,
            "ubereats": 2,
            "grubhub": 1
        },
        "today_completed": 42,
        "avg_wait_time": 180
    }
}
```

---

## Error Responses

### 400 Bad Request

```json
{
    "success": false,
    "error": "Missing required field: customer_name"
}
```

### 401 Unauthorized

```json
{
    "success": false,
    "error": "Invalid or missing API key"
}
```

### 404 Not Found

```json
{
    "success": false,
    "error": "Order not found"
}
```

### 405 Method Not Allowed

```json
{
    "success": false,
    "error": "Method not allowed"
}
```

### 429 Too Many Requests

Returned only when rate limiting is enabled (`RATE_LIMIT_ENABLED` in config).

```json
{
    "success": false,
    "error": "Rate limit exceeded"
}
```

### 500 Internal Server Error

```json
{
    "success": false,
    "error": "Internal server error"
}
```

---

## Rate Limiting

Rate limiting is **disabled by default** for internal use. To enable it (e.g. if exposing the API publicly), set `RATE_LIMIT_ENABLED` to `true` in `public/includes/config.php`.

When enabled:
- **Limit**: 60 requests per minute (configurable via `RATE_LIMIT_REQUESTS` / `RATE_LIMIT_WINDOW`)
- **Scope**: Per IP address or API key
- **Response**: HTTP 429 with error message when exceeded

---

## Platforms

| Value | Display Name |
|-------|--------------|
| `doordash` | DoorDash |
| `ubereats` | Uber Eats |
| `grubhub` | Grubhub |

---

## Order Statuses

| Value | Description |
|-------|-------------|
| `preparing` | Order is being prepared |
| `ready` | Order is ready for pickup |

---

## Shelf Locations

Valid values: `A`, `B`, `C`, `D`, `E`, `F`

Shelf location is required when status is `ready`.

---

## Usage Examples

### Python (SDK)

```python
from orderboard_sdk import OrderBoardClient

client = OrderBoardClient(
    api_key="gkob_your_key_here",
    base_url="http://localhost:8000"
)

# Create order
order = client.create_order(
    customer_name="John Doe",
    platform="doordash"
)

# Mark ready
client.mark_ready(order['order_id'], "A")

# List orders
for o in client.list_orders(status="ready"):
    print(f"{o['display_name']} - Shelf {o['shelf_location']}")
```

### Python (requests)

```python
import requests

API_KEY = "gkob_your_key_here"
BASE_URL = "http://localhost:8000/api"

# Create order
response = requests.post(
    f"{BASE_URL}/create-order.php",
    headers={"X-API-Key": API_KEY},
    json={"customer_name": "John Doe", "platform": "doordash"}
)
print(response.json())
```

### cURL

```bash
# Create order
curl -X POST http://localhost:8000/api/create-order.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"customer_name": "John Doe", "platform": "doordash"}'

# Mark ready
curl -X POST http://localhost:8000/api/update-order.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"order_id": "ORD-A1B2C3D4", "status": "ready", "shelf_location": "B"}'

# List orders
curl "http://localhost:8000/api/list-orders.php?api_key=YOUR_API_KEY"

# Delete order
curl -X DELETE http://localhost:8000/api/delete-order.php \
  -H "X-API-Key: YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"order_id": "ORD-A1B2C3D4"}'
```

### JavaScript

```javascript
const API_KEY = 'your_api_key';
const BASE_URL = 'http://localhost:8000/api';

// Create order
async function createOrder(name, platform) {
    const response = await fetch(`${BASE_URL}/create-order.php`, {
        method: 'POST',
        headers: {
            'X-API-Key': API_KEY,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            customer_name: name,
            platform: platform
        })
    });
    return await response.json();
}

// Get display feed (no auth needed)
async function getDisplayOrders() {
    const response = await fetch(`${BASE_URL}/display.php`);
    return await response.json();
}
```
