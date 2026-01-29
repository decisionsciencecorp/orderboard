"""
Ghost Kitchen Order Board SDK - Client

Python client for interacting with the Ghost Kitchen Order Board API.
"""

import json
from typing import Optional, Dict, Any, List
from urllib.request import Request, urlopen
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode


class OrderBoardError(Exception):
    """Base exception for OrderBoard SDK errors."""
    def __init__(self, message: str, status_code: int = None, response: Dict = None):
        super().__init__(message)
        self.status_code = status_code
        self.response = response


class OrderBoardClient:
    """
    Client for the Ghost Kitchen Order Board API.
    
    Args:
        api_key: Your API key for authentication
        base_url: Base URL of the Order Board API (default: http://localhost:8000)
        timeout: Request timeout in seconds (default: 30)
    
    Example:
        client = OrderBoardClient(
            api_key="gkob_your_api_key_here",
            base_url="http://localhost:8000"
        )
    """
    
    def __init__(self, api_key: str, base_url: str = "http://localhost:8000", timeout: int = 30):
        self.api_key = api_key
        self.base_url = base_url.rstrip('/')
        self.timeout = timeout
    
    def _make_request(self, method: str, endpoint: str, data: Dict = None, params: Dict = None) -> Dict:
        """Make an HTTP request to the API."""
        url = f"{self.base_url}/api/{endpoint}"
        
        if params:
            params['api_key'] = self.api_key
            url += '?' + urlencode(params)
        
        headers = {
            'X-API-Key': self.api_key,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        }
        
        body = None
        if data is not None:
            body = json.dumps(data).encode('utf-8')
        
        request = Request(url, data=body, headers=headers, method=method)
        
        try:
            with urlopen(request, timeout=self.timeout) as response:
                response_data = json.loads(response.read().decode('utf-8'))
                return response_data
        except HTTPError as e:
            try:
                error_data = json.loads(e.read().decode('utf-8'))
                raise OrderBoardError(
                    error_data.get('error', str(e)),
                    status_code=e.code,
                    response=error_data
                )
            except json.JSONDecodeError:
                raise OrderBoardError(str(e), status_code=e.code)
        except URLError as e:
            raise OrderBoardError(f"Connection error: {e.reason}")
        except Exception as e:
            raise OrderBoardError(str(e))
    
    def create_order(
        self,
        customer_name: str,
        platform: str,
        order_id: Optional[str] = None,
        status: str = "preparing",
        shelf_location: Optional[str] = None,
        notes: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Create a new order.
        
        Args:
            customer_name: Customer's full name (will display as "FIRST L")
            platform: Delivery platform - 'doordash', 'ubereats', or 'grubhub'
            order_id: Optional custom order ID (auto-generated if not provided)
            status: Order status - 'preparing' (default) or 'ready'
            shelf_location: Shelf location A-F (required if status is 'ready')
            notes: Optional internal notes
        
        Returns:
            Created order data
        
        Example:
            order = client.create_order(
                customer_name="John Doe",
                platform="doordash",
                notes="Extra napkins"
            )
            print(f"Created order: {order['order_id']}")
        """
        data = {
            'customer_name': customer_name,
            'platform': platform,
            'status': status
        }
        
        if order_id:
            data['order_id'] = order_id
        if shelf_location:
            data['shelf_location'] = shelf_location
        if notes:
            data['notes'] = notes
        
        response = self._make_request('POST', 'create-order.php', data=data)
        
        if not response.get('success'):
            raise OrderBoardError(response.get('error', 'Failed to create order'))
        
        return response.get('order')
    
    def update_order(
        self,
        order_id: str = None,
        id: int = None,
        customer_name: Optional[str] = None,
        platform: Optional[str] = None,
        status: Optional[str] = None,
        shelf_location: Optional[str] = None,
        notes: Optional[str] = None
    ) -> Dict[str, Any]:
        """
        Update an existing order.
        
        Args:
            order_id: Order ID string (e.g., "ORD-XXXX")
            id: Database ID (alternative to order_id)
            customer_name: Updated customer name
            platform: Updated platform
            status: Updated status ('preparing' or 'ready')
            shelf_location: Shelf location A-F
            notes: Updated notes
        
        Returns:
            Updated order data
        
        Example:
            # Mark order as ready at shelf B
            client.update_order(
                order_id="ORD-A1B2C3D4",
                status="ready",
                shelf_location="B"
            )
        """
        data = {}
        
        if order_id:
            data['order_id'] = order_id
        elif id:
            data['id'] = id
        else:
            raise OrderBoardError("Either order_id or id is required")
        
        if customer_name is not None:
            data['customer_name'] = customer_name
        if platform is not None:
            data['platform'] = platform
        if status is not None:
            data['status'] = status
        if shelf_location is not None:
            data['shelf_location'] = shelf_location
        if notes is not None:
            data['notes'] = notes
        
        response = self._make_request('POST', 'update-order.php', data=data)
        
        if not response.get('success'):
            raise OrderBoardError(response.get('error', 'Failed to update order'))
        
        return response.get('order')
    
    def mark_ready(self, order_id: str, shelf_location: str) -> Dict[str, Any]:
        """
        Convenience method to mark an order as ready with a shelf location.
        
        Args:
            order_id: Order ID string
            shelf_location: Shelf location A-F
        
        Returns:
            Updated order data
        
        Example:
            client.mark_ready("ORD-A1B2C3D4", "C")
        """
        return self.update_order(order_id=order_id, status="ready", shelf_location=shelf_location)
    
    def get_order(self, order_id: str = None, id: int = None) -> Dict[str, Any]:
        """
        Get a single order by ID.
        
        Args:
            order_id: Order ID string (e.g., "ORD-XXXX")
            id: Database ID (alternative to order_id)
        
        Returns:
            Order data
        
        Example:
            order = client.get_order(order_id="ORD-A1B2C3D4")
            print(f"Status: {order['status']}")
        """
        params = {}
        if order_id:
            params['order_id'] = order_id
        elif id:
            params['id'] = id
        else:
            raise OrderBoardError("Either order_id or id is required")
        
        response = self._make_request('GET', 'get-order.php', params=params)
        
        if not response.get('success'):
            raise OrderBoardError(response.get('error', 'Failed to get order'))
        
        return response.get('order')
    
    def list_orders(
        self,
        status: Optional[str] = None,
        platform: Optional[str] = None,
        limit: Optional[int] = None,
        offset: Optional[int] = None
    ) -> List[Dict[str, Any]]:
        """
        List orders with optional filters.
        
        Args:
            status: Filter by status ('preparing' or 'ready')
            platform: Filter by platform ('doordash', 'ubereats', 'grubhub')
            limit: Maximum number of orders to return
            offset: Pagination offset
        
        Returns:
            List of order data
        
        Example:
            # Get all ready orders
            ready_orders = client.list_orders(status="ready")
            
            # Get DoorDash orders
            dd_orders = client.list_orders(platform="doordash")
        """
        params = {}
        if status:
            params['status'] = status
        if platform:
            params['platform'] = platform
        if limit:
            params['limit'] = limit
        if offset:
            params['offset'] = offset
        
        response = self._make_request('GET', 'list-orders.php', params=params)
        
        if not response.get('success'):
            raise OrderBoardError(response.get('error', 'Failed to list orders'))
        
        return response.get('orders', [])
    
    def delete_order(self, order_id: str = None, id: int = None) -> Dict[str, Any]:
        """
        Delete an order (marks as picked up and archives).
        
        Args:
            order_id: Order ID string (e.g., "ORD-XXXX")
            id: Database ID (alternative to order_id)
        
        Returns:
            Deleted order data
        
        Example:
            client.delete_order(order_id="ORD-A1B2C3D4")
        """
        data = {}
        if order_id:
            data['order_id'] = order_id
        elif id:
            data['id'] = id
        else:
            raise OrderBoardError("Either order_id or id is required")
        
        response = self._make_request('DELETE', 'delete-order.php', data=data)
        
        if not response.get('success'):
            raise OrderBoardError(response.get('error', 'Failed to delete order'))
        
        return response.get('order')
    
    def get_display_orders(self) -> List[Dict[str, Any]]:
        """
        Get orders formatted for display board.
        
        This endpoint is public and does not require authentication.
        
        Returns:
            List of display-formatted orders
        
        Example:
            display_orders = client.get_display_orders()
            for order in display_orders:
                print(f"{order['name']} - {order['platform']} - {order['status']}")
        """
        # This endpoint doesn't require auth, but we'll include it anyway
        response = self._make_request('GET', 'display.php', params={})
        
        if not response.get('success'):
            raise OrderBoardError(response.get('error', 'Failed to get display orders'))
        
        return response.get('orders', [])
    
    def get_stats(self) -> Dict[str, Any]:
        """
        Get order statistics.
        
        Returns:
            Statistics data including active orders, completed today, avg wait time, etc.
        
        Example:
            stats = client.get_stats()
            print(f"Active orders: {stats['active_orders']}")
            print(f"Completed today: {stats['today_completed']}")
        """
        response = self._make_request('GET', 'stats.php', params={})
        
        if not response.get('success'):
            raise OrderBoardError(response.get('error', 'Failed to get stats'))
        
        return response.get('stats')
