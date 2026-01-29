"""
Ghost Kitchen Order Board SDK

A Python client for the Ghost Kitchen Order Board API.

Usage:
    from orderboard_sdk import OrderBoardClient
    
    client = OrderBoardClient(
        api_key="your_api_key_here",
        base_url="http://localhost:8000"
    )
    
    # Create an order
    order = client.create_order(
        customer_name="John Doe",
        platform="doordash"
    )
    
    # Mark order as ready
    client.update_order(order['order_id'], status="ready", shelf_location="A")
    
    # List all orders
    orders = client.list_orders()
    
    # Delete/pickup order
    client.delete_order(order['order_id'])
"""

from .client import OrderBoardClient

__version__ = "1.0.0"
__all__ = ["OrderBoardClient"]
