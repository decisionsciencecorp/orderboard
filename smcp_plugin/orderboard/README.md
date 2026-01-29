# Ghost Kitchen Order Board - SMCP Plugin

An MCP plugin for managing ghost kitchen orders through AI agents.

## Installation

### As SMCP Plugin

1. Copy the `orderboard` folder to your SMCP plugins directory:
   ```bash
   cp -r orderboard /path/to/smcp/plugins/
   ```

2. Make CLI executable:
   ```bash
   chmod +x /path/to/smcp/plugins/orderboard/cli.py
   ```

3. Set environment variables:
   ```bash
   export ORDERBOARD_API_KEY="your_api_key_here"
   export ORDERBOARD_BASE_URL="http://localhost:8000"
   ```

### Standalone Usage

```bash
# Set environment variables
export ORDERBOARD_API_KEY="your_api_key_here"
export ORDERBOARD_BASE_URL="http://localhost:8000"

# Run commands
python cli.py create-order --customer-name "John Doe" --platform doordash
```

## Available Commands

### create-order

Create a new order on the board.

```bash
python cli.py create-order \
  --customer-name "John Doe" \
  --platform doordash \
  --notes "Extra napkins"
```

**Parameters:**
- `--customer-name` (required): Customer's full name
- `--platform` (required): `doordash`, `ubereats`, or `grubhub`
- `--order-id` (optional): Custom order ID
- `--notes` (optional): Internal notes

### mark-ready

Mark an order as ready for pickup and assign shelf location.

```bash
python cli.py mark-ready \
  --order-id ORD-A1B2C3D4 \
  --shelf-location B
```

**Parameters:**
- `--order-id` (required): Order ID to mark ready
- `--shelf-location` (required): Shelf location A-F

### update-order

Update any order fields.

```bash
python cli.py update-order \
  --order-id ORD-A1B2C3D4 \
  --status ready \
  --shelf-location C
```

**Parameters:**
- `--order-id` or `--id` (one required): Order identifier
- `--status` (optional): `preparing` or `ready`
- `--shelf-location` (optional): A-F
- `--customer-name` (optional): Updated name
- `--notes` (optional): Updated notes

### list-orders

List all active orders with optional filters.

```bash
# All orders
python cli.py list-orders

# Ready orders only
python cli.py list-orders --status ready

# DoorDash orders
python cli.py list-orders --platform doordash
```

**Parameters:**
- `--status` (optional): `preparing` or `ready`
- `--platform` (optional): `doordash`, `ubereats`, or `grubhub`
- `--limit` (optional): Maximum results

### get-order

Get details for a specific order.

```bash
python cli.py get-order --order-id ORD-A1B2C3D4
```

**Parameters:**
- `--order-id` or `--id` (one required): Order identifier

### delete-order

Remove an order from the board (marks as picked up).

```bash
python cli.py delete-order --order-id ORD-A1B2C3D4
```

**Parameters:**
- `--order-id` or `--id` (one required): Order identifier

### stats

Get order board statistics.

```bash
python cli.py stats
```

## Agent Rules

When using this plugin, agents should follow these rules:

### Agent MAY:
- Add new orders when notified of incoming deliveries
- Change `PREPARING` → `READY` status
- Add shelf location when marking ready
- Remove orders when drivers pick up

### Agent MAY NOT:
- Guess the platform (must be explicitly provided)
- Guess readiness status (must be confirmed)
- Change order display ordering without instruction

## SMCP Integration

This plugin supports SMCP's `--describe` flag for automatic tool discovery:

```bash
python cli.py --describe
```

Returns JSON specification of all available commands and parameters.

## Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `ORDERBOARD_API_KEY` | Yes | - | API key for authentication |
| `ORDERBOARD_BASE_URL` | No | `http://localhost:8000` | Order Board API URL |

## License

AGPLv3 - See LICENSE file for details.
