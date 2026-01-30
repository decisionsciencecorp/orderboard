<?php

use PHPUnit\Framework\TestCase;

/**
 * Full flow integration: create → list → get → update → delete, stats, display.
 */
class FullFlowTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/bootstrap.php';
        require_once dirname(__DIR__, 2) . '/../public/includes/functions.php';
        require_once dirname(__DIR__, 2) . '/../public/includes/auth.php';
    }

    public function testFullOrderLifecycle(): void
    {
        $created = createOrder([
            'customer_name' => 'Flow Test User',
            'platform' => 'doordash',
        ]);
        $this->assertSame('preparing', $created['status']);
        $id = $created['id'];
        $orderId = $created['order_id'];

        $byId = getOrderById($id);
        $this->assertSame($orderId, $byId['order_id']);

        $byOrderId = getOrderByOrderId($orderId);
        $this->assertSame($id, $byOrderId['id']);

        $list = listOrders(['status' => 'preparing']);
        $found = false;
        foreach ($list as $o) {
            if ($o['id'] === $id) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);

        $updated = updateOrder($id, ['status' => 'ready', 'shelf_location' => 'A']);
        $this->assertSame('ready', $updated['status']);
        $this->assertSame('A', $updated['shelf_location']);

        $displayOrders = getDisplayOrders();
        $foundInDisplay = false;
        foreach ($displayOrders as $o) {
            if ($o['order_id'] === $orderId && $o['status'] === 'ready') {
                $foundInDisplay = true;
                break;
            }
        }
        $this->assertTrue($foundInDisplay);

        $deleted = deleteOrder($id);
        $this->assertTrue($deleted);
        $this->assertNull(getOrderById($id));
    }

    public function testStatsReflectOrders(): void
    {
        $before = getOrderStats();
        $created = createOrder(['customer_name' => 'Stats User', 'platform' => 'ubereats']);
        $after = getOrderStats();
        $this->assertSame($before['active_orders'] + 1, $after['active_orders']);
        deleteOrder($created['id']);
    }
}
