#!/usr/bin/env python3
"""
Ghost Kitchen Order Board - SMCP Plugin CLI

An MCP plugin for managing ghost kitchen orders through AI agents.
Provides tools for creating, updating, and managing delivery orders.

Copyright (c) 2025

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Affero General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.
"""

import argparse
import json
import os
import sys
from typing import Dict, Any
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode


# Configuration from environment
API_KEY = os.getenv('ORDERBOARD_API_KEY', '')
BASE_URL = os.getenv('ORDERBOARD_BASE_URL', 'http://localhost:8000')


def make_request(method: str, endpoint: str, data: Dict = None, params: Dict = None) -> Dict:
    """Make an HTTP request to the Order Board API."""
    url = f"{BASE_URL}/api/{endpoint}"
    
    if params:
        if API_KEY:
            params['api_key'] = API_KEY
        url += '?' + urlencode(params)
    
    headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    }
    
    if API_KEY:
        headers['X-API-Key'] = API_KEY
    
    body = None
    if data is not None:
        body = json.dumps(data).encode('utf-8')
    
    request = Request(url, data=body, headers=headers, method=method)
    
    try:
        with urlopen(request, timeout=30) as response:
            return json.loads(response.read().decode('utf-8'))
    except HTTPError as e:
        try:
            error_data = json.loads(e.read().decode('utf-8'))
            return {"success": False, "error": error_data.get('error', str(e))}
        except:
            return {"success": False, "error": str(e)}
    except URLError as e:
        return {"success": False, "error": f"Connection error: {e.reason}"}
    except Exception as e:
        return {"success": False, "error": str(e)}


def create_order(args: Dict[str, Any]) -> Dict[str, Any]:
    """
    Create a new order on the order board.
    
    Agent may use this to add a new order when notified of incoming delivery.
    """
    data = {
        'customer_name': args.get('customer_name'),
        'platform': args.get('platform')
    }
    
    if args.get('order_id'):
        data['order_id'] = args['order_id']
    if args.get('notes'):
        data['notes'] = args['notes']
    
    result = make_request('POST', 'create-order.php', data=data)
    return result


def update_order(args: Dict[str, Any]) -> Dict[str, Any]:
    """
    Update an existing order's status or information.
    
    Agent may use this to change PREPARING → READY and assign shelf location.
    """
    data = {}
    
    if args.get('order_id'):
        data['order_id'] = args['order_id']
    elif args.get('id'):
        data['id'] = args['id']
    else:
        return {"success": False, "error": "Either order_id or id is required"}
    
    if args.get('status'):
        data['status'] = args['status']
    if args.get('shelf_location'):
        data['shelf_location'] = args['shelf_location']
    if args.get('customer_name'):
        data['customer_name'] = args['customer_name']
    if args.get('notes'):
        data['notes'] = args['notes']
    
    result = make_request('POST', 'update-order.php', data=data)
    return result


def mark_ready(args: Dict[str, Any]) -> Dict[str, Any]:
    """
    Mark an order as ready for pickup and assign shelf location.
    
    This is the primary action agents will use when food is ready.
    """
    order_id = args.get('order_id')
    shelf = args.get('shelf_location')
    
    if not order_id:
        return {"success": False, "error": "order_id is required"}
    if not shelf:
        return {"success": False, "error": "shelf_location is required (A-F)"}
    
    data = {
        'order_id': order_id,
        'status': 'ready',
        'shelf_location': shelf.upper()
    }
    
    result = make_request('POST', 'update-order.php', data=data)
    return result


def list_orders(args: Dict[str, Any]) -> Dict[str, Any]:
    """
    List all active orders with optional filters.
    
    Agent can use this to check current board status.
    """
    params = {}
    
    if args.get('status'):
        params['status'] = args['status']
    if args.get('platform'):
        params['platform'] = args['platform']
    if args.get('limit'):
        params['limit'] = args['limit']
    
    result = make_request('GET', 'list-orders.php', params=params)
    return result


def get_order(args: Dict[str, Any]) -> Dict[str, Any]:
    """
    Get details for a specific order.
    """
    params = {}
    
    if args.get('order_id'):
        params['order_id'] = args['order_id']
    elif args.get('id'):
        params['id'] = args['id']
    else:
        return {"success": False, "error": "Either order_id or id is required"}
    
    result = make_request('GET', 'get-order.php', params=params)
    return result


def delete_order(args: Dict[str, Any]) -> Dict[str, Any]:
    """
    Remove an order from the board (marks as picked up).
    
    Agent should use this when driver has collected the order.
    """
    data = {}
    
    if args.get('order_id'):
        data['order_id'] = args['order_id']
    elif args.get('id'):
        data['id'] = args['id']
    else:
        return {"success": False, "error": "Either order_id or id is required"}
    
    result = make_request('DELETE', 'delete-order.php', data=data)
    return result


def get_stats(args: Dict[str, Any]) -> Dict[str, Any]:
    """
    Get order board statistics.
    
    Useful for agents to understand current load and performance.
    """
    result = make_request('GET', 'stats.php', params={})
    return result


def get_description() -> Dict[str, Any]:
    """Return plugin description for SMCP --describe."""
    return {
        "plugin": {
            "name": "orderboard",
            "version": "1.0.0",
            "description": "Ghost Kitchen Order Board - Manage delivery pickup orders"
        },
        "commands": [
            {
                "name": "create-order",
                "description": "Create a new order on the board",
                "parameters": [
                    {"name": "customer_name", "type": "string", "description": "Customer's full name", "required": True},
                    {"name": "platform", "type": "string", "description": "Delivery platform (doordash, ubereats, grubhub)", "required": True},
                    {"name": "order_id", "type": "string", "description": "Custom order ID (auto-generated if omitted)", "required": False},
                    {"name": "notes", "type": "string", "description": "Internal notes", "required": False}
                ]
            },
            {
                "name": "update-order",
                "description": "Update an existing order",
                "parameters": [
                    {"name": "order_id", "type": "string", "description": "Order ID to update", "required": False},
                    {"name": "id", "type": "number", "description": "Database ID to update (alternative to order_id)", "required": False},
                    {"name": "status", "type": "string", "description": "New status (preparing or ready)", "required": False},
                    {"name": "shelf_location", "type": "string", "description": "Shelf location A-F", "required": False},
                    {"name": "customer_name", "type": "string", "description": "Updated customer name", "required": False},
                    {"name": "notes", "type": "string", "description": "Updated notes", "required": False}
                ]
            },
            {
                "name": "mark-ready",
                "description": "Mark an order as ready with shelf location",
                "parameters": [
                    {"name": "order_id", "type": "string", "description": "Order ID to mark ready", "required": True},
                    {"name": "shelf_location", "type": "string", "description": "Shelf location A-F", "required": True}
                ]
            },
            {
                "name": "list-orders",
                "description": "List all active orders",
                "parameters": [
                    {"name": "status", "type": "string", "description": "Filter by status (preparing, ready)", "required": False},
                    {"name": "platform", "type": "string", "description": "Filter by platform", "required": False},
                    {"name": "limit", "type": "number", "description": "Max results to return", "required": False}
                ]
            },
            {
                "name": "get-order",
                "description": "Get details for a specific order",
                "parameters": [
                    {"name": "order_id", "type": "string", "description": "Order ID to retrieve", "required": False},
                    {"name": "id", "type": "number", "description": "Database ID (alternative to order_id)", "required": False}
                ]
            },
            {
                "name": "delete-order",
                "description": "Remove order from board (mark as picked up)",
                "parameters": [
                    {"name": "order_id", "type": "string", "description": "Order ID to delete", "required": False},
                    {"name": "id", "type": "number", "description": "Database ID (alternative to order_id)", "required": False}
                ]
            },
            {
                "name": "stats",
                "description": "Get order board statistics",
                "parameters": []
            }
        ]
    }


def main():
    parser = argparse.ArgumentParser(
        description="Ghost Kitchen Order Board - SMCP Plugin",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Available commands:
  create-order    Create a new order
  update-order    Update an existing order
  mark-ready      Mark order as ready with shelf location
  list-orders     List all active orders
  get-order       Get a specific order
  delete-order    Remove order (mark as picked up)
  stats           Get order statistics

Environment variables:
  ORDERBOARD_API_KEY   - API key for authentication
  ORDERBOARD_BASE_URL  - Base URL (default: http://localhost:8000)

Examples:
  python cli.py create-order --customer-name "John Doe" --platform doordash
  python cli.py mark-ready --order-id ORD-A1B2C3D4 --shelf-location B
  python cli.py list-orders --status ready
  python cli.py delete-order --order-id ORD-A1B2C3D4
        """
    )
    
    # Add --describe for SMCP plugin discovery
    parser.add_argument('--describe', action='store_true', help='Output plugin description as JSON')
    
    subparsers = parser.add_subparsers(dest='command', help='Available commands')
    
    # create-order
    create_parser = subparsers.add_parser('create-order', help='Create a new order')
    create_parser.add_argument('--customer-name', required=True, help='Customer full name')
    create_parser.add_argument('--platform', required=True, choices=['doordash', 'ubereats', 'grubhub'], help='Delivery platform')
    create_parser.add_argument('--order-id', help='Custom order ID')
    create_parser.add_argument('--notes', help='Internal notes')
    
    # update-order
    update_parser = subparsers.add_parser('update-order', help='Update an existing order')
    update_parser.add_argument('--order-id', help='Order ID to update')
    update_parser.add_argument('--id', type=int, help='Database ID to update')
    update_parser.add_argument('--status', choices=['preparing', 'ready'], help='New status')
    update_parser.add_argument('--shelf-location', help='Shelf location A-F')
    update_parser.add_argument('--customer-name', help='Updated customer name')
    update_parser.add_argument('--notes', help='Updated notes')
    
    # mark-ready
    ready_parser = subparsers.add_parser('mark-ready', help='Mark order as ready')
    ready_parser.add_argument('--order-id', required=True, help='Order ID')
    ready_parser.add_argument('--shelf-location', required=True, help='Shelf location A-F')
    
    # list-orders
    list_parser = subparsers.add_parser('list-orders', help='List orders')
    list_parser.add_argument('--status', choices=['preparing', 'ready'], help='Filter by status')
    list_parser.add_argument('--platform', choices=['doordash', 'ubereats', 'grubhub'], help='Filter by platform')
    list_parser.add_argument('--limit', type=int, help='Max results')
    
    # get-order
    get_parser = subparsers.add_parser('get-order', help='Get a specific order')
    get_parser.add_argument('--order-id', help='Order ID')
    get_parser.add_argument('--id', type=int, help='Database ID')
    
    # delete-order
    delete_parser = subparsers.add_parser('delete-order', help='Remove order from board')
    delete_parser.add_argument('--order-id', help='Order ID')
    delete_parser.add_argument('--id', type=int, help='Database ID')
    
    # stats
    subparsers.add_parser('stats', help='Get statistics')
    
    args = parser.parse_args()
    
    # Handle --describe
    if args.describe:
        print(json.dumps(get_description(), indent=2))
        sys.exit(0)
    
    if not args.command:
        parser.print_help()
        sys.exit(1)
    
    # Convert args to dict, replacing hyphens with underscores
    args_dict = {}
    for key, value in vars(args).items():
        if key != 'command' and value is not None:
            clean_key = key.replace('-', '_')
            args_dict[clean_key] = value
    
    try:
        if args.command == 'create-order':
            result = create_order(args_dict)
        elif args.command == 'update-order':
            result = update_order(args_dict)
        elif args.command == 'mark-ready':
            result = mark_ready(args_dict)
        elif args.command == 'list-orders':
            result = list_orders(args_dict)
        elif args.command == 'get-order':
            result = get_order(args_dict)
        elif args.command == 'delete-order':
            result = delete_order(args_dict)
        elif args.command == 'stats':
            result = get_stats(args_dict)
        else:
            result = {"error": f"Unknown command: {args.command}"}
        
        print(json.dumps(result, indent=2))
        sys.exit(0 if result.get('success', False) else 1)
        
    except Exception as e:
        print(json.dumps({"success": False, "error": str(e)}))
        sys.exit(1)


if __name__ == "__main__":
    main()
