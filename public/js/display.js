/**
 * Ghost Kitchen Order Board - Display JavaScript
 * 
 * Auto-refreshing driver-facing display board
 */

class OrderDisplay {
    constructor() {
        this.refreshInterval = 5000; // Will be updated from API
        this.ordersContainer = document.getElementById('orders-container');
        this.clockElement = document.getElementById('clock');
        this.statusDot = document.querySelector('.status-dot');
        this.orderCount = document.getElementById('order-count');
        
        this.init();
    }
    
    init() {
        this.fetchOrders();
        this.startClock();
        this.startAutoRefresh();
    }
    
    async fetchOrders() {
        try {
            const response = await fetch('/api/display.php');
            const data = await response.json();
            
            if (data.success) {
                this.refreshInterval = data.refresh_interval || 5000;
                this.renderOrders(data.orders);
                this.updateOrderCount(data.count);
                this.setOnlineStatus(true);
            } else {
                this.setOnlineStatus(false);
            }
        } catch (error) {
            console.error('Failed to fetch orders:', error);
            this.setOnlineStatus(false);
        }
    }
    
    renderOrders(orders) {
        if (!orders || orders.length === 0) {
            this.ordersContainer.innerHTML = `
                <div class="empty-state">
                    <div class="icon">📋</div>
                    <div>No active orders</div>
                </div>
            `;
            return;
        }
        
        // Sort: READY first, then by creation time
        orders.sort((a, b) => {
            if (a.status === 'ready' && b.status !== 'ready') return -1;
            if (a.status !== 'ready' && b.status === 'ready') return 1;
            return new Date(b.created_at) - new Date(a.created_at);
        });
        
        const html = orders.map(order => this.renderOrderRow(order)).join('');
        this.ordersContainer.innerHTML = html;
    }
    
    renderOrderRow(order) {
        const statusClass = order.status === 'ready' ? 'ready' : 'preparing';
        const statusText = order.status === 'ready' 
            ? `READY <span class="arrow">→</span>` 
            : 'PREPARING';
        const shelfLocation = order.status === 'ready' && order.shelf 
            ? order.shelf 
            : '';
        
        return `
            <div class="order-row status-${statusClass}" data-order-id="${order.order_id}">
                <div class="order-name">${this.escapeHtml(order.name)}</div>
                <div class="order-platform ${order.platform}">
                    <img src="/img/${order.platform}-logo.svg" 
                         alt="${this.getPlatformName(order.platform)}"
                         onerror="this.style.display='none'; this.parentElement.innerHTML='${this.getPlatformName(order.platform).toUpperCase()}'">
                </div>
                <div class="order-status ${statusClass}">${statusText}</div>
                <div class="order-shelf">${shelfLocation}</div>
            </div>
        `;
    }
    
    getPlatformName(platform) {
        const names = {
            'doordash': 'DoorDash',
            'ubereats': 'Uber Eats',
            'grubhub': 'Grubhub'
        };
        return names[platform] || platform;
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    updateOrderCount(count) {
        if (this.orderCount) {
            this.orderCount.textContent = count;
        }
    }
    
    setOnlineStatus(online) {
        if (this.statusDot) {
            this.statusDot.style.backgroundColor = online ? '#28a745' : '#dc3545';
        }
    }
    
    startClock() {
        this.updateClock();
        setInterval(() => this.updateClock(), 1000);
    }
    
    updateClock() {
        if (this.clockElement) {
            const now = new Date();
            this.clockElement.textContent = now.toLocaleTimeString('en-US', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
        }
    }
    
    startAutoRefresh() {
        setInterval(() => this.fetchOrders(), this.refreshInterval);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.orderDisplay = new OrderDisplay();
});
