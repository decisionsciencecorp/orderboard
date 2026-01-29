# Ghost Kitchen Order Board SDK

A Python client for the Ghost Kitchen Order Board API.

## Installation

```bash
cd orderboard_sdk
pip install -e .
```

Or copy to your project:

```bash
cp -r orderboard_sdk /path/to/your/project/
```

## Quick Start

```python
from orderboard_sdk import OrderBoardClient

# Initialize client
client = OrderBoardClient(
    api_key="your_api_key_here",
    base_url="http://localhost:8000"
)

# Create an order
order = client.create_order(
    customer_name="John Doe",
    platform="doordash"
)
print(f"Created: {order['order_id']}")

# Mark order as ready
client.mark_ready(order['order_id'], shelf_location="A")

# List all orders
orders = client.list_orders()
for o in orders:
    print(f"{o['display_name']} - {o['status']}")

# Delete/pickup order
client.delete_order(order['order_id'])
```

## API Reference

### OrderBoardClient

```python
client = OrderBoardClient(
    api_key: str,           # Your API key
    base_url: str,          # API base URL (default: http://localhost:8000)
    timeout: int            # Request timeout in seconds (default: 30)
)
```

### Methods

#### create_order()

Create a new order.

```python
order = client.create_order(
    customer_name="John Doe",       # Required
    platform="doordash",            # Required: doordash, ubereats, grubhub
    order_id="CUSTOM-123",          # Optional: auto-generated if not provided
    status="preparing",             # Optional: preparing (default) or ready
    shelf_location="A",             # Optional: A-F
    notes="Extra napkins"           # Optional
)
```

#### update_order()

Update an existing order.

```python
order = client.update_order(
    order_id="ORD-XXXX",            # OR id=123
    status="ready",
    shelf_location="B"
)
```

#### mark_ready()

Convenience method to mark an order as ready.

```python
client.mark_ready("ORD-XXXX", "B")
```

#### get_order()

Get a single order.

```python
order = client.get_order(order_id="ORD-XXXX")
# or
order = client.get_order(id=123)
```

#### list_orders()

List orders with optional filters.

```python
# All orders
orders = client.list_orders()

# Filter by status
ready_orders = client.list_orders(status="ready")

# Filter by platform
dd_orders = client.list_orders(platform="doordash")

# With pagination
orders = client.list_orders(limit=10, offset=0)
```

#### delete_order()

Delete/pickup an order (archives to history).

```python
client.delete_order(order_id="ORD-XXXX")
```

#### get_display_orders()

Get orders formatted for the display board.

```python
display = client.get_display_orders()
for order in display:
    print(f"{order['name']} - {order['platform']} - {order['status']}")
```

#### get_stats()

Get order statistics.

```python
stats = client.get_stats()
print(f"Active: {stats['active_orders']}")
print(f"Ready: {stats['ready']}")
print(f"Completed today: {stats['today_completed']}")
```

## Error Handling

```python
from orderboard_sdk import OrderBoardClient
from orderboard_sdk.client import OrderBoardError

client = OrderBoardClient(api_key="your_key", base_url="http://localhost:8000")

try:
    order = client.create_order(
        customer_name="",  # Invalid
        platform="doordash"
    )
except OrderBoardError as e:
    print(f"Error: {e}")
    print(f"Status code: {e.status_code}")
    print(f"Response: {e.response}")
```

## Platforms

Supported delivery platforms:
- `doordash` - DoorDash
- `ubereats` - Uber Eats
- `grubhub` - Grubhub

## Shelf Locations

Valid shelf locations: `A`, `B`, `C`, `D`, `E`, `F`

## License

AGPLv3 - See LICENSE file for details.
