<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for public/includes/functions.php (pure helpers and CRUD with test DB).
 */
class FunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/../public/includes/functions.php';
    }

    public function testFormatCustomerNameTwoParts(): void
    {
        $this->assertSame('JOHN D', formatCustomerName('John Doe'));
        $this->assertSame('JANE S', formatCustomerName('Jane Smith'));
        $this->assertSame('BOB M', formatCustomerName('Bob McDonald'));
    }

    public function testFormatCustomerNameSingleWord(): void
    {
        $this->assertSame('MADONNA', formatCustomerName('Madonna'));
        $this->assertSame('PRINCE', formatCustomerName('Prince'));
    }

    public function testFormatCustomerNameTrimsAndMultipleSpaces(): void
    {
        $this->assertSame('JOHN D', formatCustomerName('  John   Doe  '));
        $this->assertSame('A B', formatCustomerName('A B'));
    }

    public function testFormatCustomerNameThreePartsUsesLastAsInitial(): void
    {
        $this->assertSame('MARY W', formatCustomerName('Mary Jane Watson'));
    }

    public function testIsValidPlatform(): void
    {
        $this->assertTrue(isValidPlatform('doordash'));
        $this->assertTrue(isValidPlatform('ubereats'));
        $this->assertTrue(isValidPlatform('grubhub'));
        $this->assertTrue(isValidPlatform('DoorDash'));
        $this->assertTrue(isValidPlatform('UBEREATS'));
        $this->assertFalse(isValidPlatform('postmates'));
        $this->assertFalse(isValidPlatform(''));
        $this->assertFalse(isValidPlatform('other'));
    }

    public function testIsValidShelfLocation(): void
    {
        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $shelf) {
            $this->assertTrue(isValidShelfLocation($shelf));
            $this->assertTrue(isValidShelfLocation(strtolower($shelf)));
        }
        $this->assertTrue(isValidShelfLocation(null));
        $this->assertTrue(isValidShelfLocation(''));
        $this->assertFalse(isValidShelfLocation('G'));
        $this->assertFalse(isValidShelfLocation('Z'));
    }

    public function testGenerateOrderIdFormat(): void
    {
        $id = generateOrderId();
        $this->assertStringStartsWith('ORD-', $id);
        $this->assertSame(12, strlen($id)); // ORD- + 8 hex chars
        $this->assertMatchesRegularExpression('/^ORD-[A-F0-9]{8}$/', $id);
    }

    public function testGenerateOrderIdUnique(): void
    {
        $ids = [];
        for ($i = 0; $i < 20; $i++) {
            $ids[] = generateOrderId();
        }
        $this->assertCount(20, array_unique($ids));
    }

    public function testCreateOrderMinimal(): void
    {
        $order = createOrder([
            'customer_name' => 'Test User',
            'platform' => 'doordash',
        ]);
        $this->assertIsArray($order);
        $this->assertArrayHasKey('order_id', $order);
        $this->assertArrayHasKey('customer_name', $order);
        $this->assertSame('Test User', $order['customer_name']);
        $this->assertSame('doordash', $order['platform']);
        $this->assertSame('preparing', $order['status']);
        $this->assertNull($order['shelf_location']);
        $this->assertStringStartsWith('ORD-', $order['order_id']);
    }

    public function testCreateOrderWithOptionalFields(): void
    {
        $order = createOrder([
            'customer_name' => 'Full Order',
            'platform' => 'ubereats',
            'order_id' => 'ORD-CUSTOM123',
            'status' => 'ready',
            'shelf_location' => 'B',
            'notes' => 'Extra sauce',
        ]);
        $this->assertSame('ORD-CUSTOM123', $order['order_id']);
        $this->assertSame('ready', $order['status']);
        $this->assertSame('B', $order['shelf_location']);
        $this->assertSame('Extra sauce', $order['notes']);
    }

    public function testCreateOrderThrowsOnMissingCustomerName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Customer name is required');
        createOrder(['platform' => 'doordash']);
    }

    public function testCreateOrderThrowsOnEmptyCustomerName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        createOrder(['customer_name' => '', 'platform' => 'doordash']);
    }

    public function testCreateOrderThrowsOnInvalidPlatform(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Valid platform');
        createOrder(['customer_name' => 'Test', 'platform' => 'invalid']);
    }

    public function testCreateOrderThrowsOnInvalidShelfLocation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid shelf location');
        createOrder([
            'customer_name' => 'Test',
            'platform' => 'doordash',
            'shelf_location' => 'Z',
        ]);
    }

    public function testGetOrderById(): void
    {
        $created = createOrder(['customer_name' => 'Get By Id', 'platform' => 'grubhub']);
        $order = getOrderById($created['id']);
        $this->assertNotNull($order);
        $this->assertSame($created['id'], $order['id']);
        $this->assertSame($created['order_id'], $order['order_id']);
    }

    public function testGetOrderByIdReturnsNullForMissing(): void
    {
        $this->assertNull(getOrderById(999999));
    }

    public function testGetOrderByOrderId(): void
    {
        $created = createOrder([
            'customer_name' => 'By Order Id',
            'platform' => 'doordash',
            'order_id' => 'ORD-UNIQUE99',
        ]);
        $order = getOrderByOrderId('ORD-UNIQUE99');
        $this->assertNotNull($order);
        $this->assertSame($created['id'], $order['id']);
    }

    public function testGetOrderByOrderIdReturnsNullForMissing(): void
    {
        $this->assertNull(getOrderByOrderId('ORD-NONEXIST'));
    }

    public function testUpdateOrder(): void
    {
        $created = createOrder(['customer_name' => 'Update Me', 'platform' => 'doordash']);
        $updated = updateOrder($created['id'], [
            'status' => 'ready',
            'shelf_location' => 'A',
        ]);
        $this->assertSame('ready', $updated['status']);
        $this->assertSame('A', $updated['shelf_location']);
    }

    public function testUpdateOrderThrowsOnNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Order not found');
        updateOrder(999999, ['status' => 'ready']);
    }

    public function testMarkOrderReady(): void
    {
        $created = createOrder(['customer_name' => 'Mark Ready', 'platform' => 'ubereats']);
        $ready = markOrderReady($created['id'], 'C');
        $this->assertSame('ready', $ready['status']);
        $this->assertSame('C', $ready['shelf_location']);
    }

    public function testDeleteOrder(): void
    {
        $created = createOrder(['customer_name' => 'Delete Me', 'platform' => 'grubhub']);
        $ok = deleteOrder($created['id']);
        $this->assertTrue($ok);
        $this->assertNull(getOrderById($created['id']));
    }

    public function testDeleteOrderReturnsFalseForMissing(): void
    {
        $this->assertFalse(deleteOrder(999999));
    }

    public function testListOrdersEmpty(): void
    {
        $orders = listOrders();
        $this->assertIsArray($orders);
    }

    public function testListOrdersWithFilters(): void
    {
        createOrder(['customer_name' => 'Preparing One', 'platform' => 'doordash']);
        $ready = createOrder(['customer_name' => 'Ready One', 'platform' => 'ubereats']);
        markOrderReady($ready['id'], 'A');
        $all = listOrders();
        $preparing = listOrders(['status' => 'preparing']);
        $readyList = listOrders(['status' => 'ready']);
        $this->assertGreaterThanOrEqual(2, count($all));
        $this->assertGreaterThanOrEqual(1, count($preparing));
        $this->assertGreaterThanOrEqual(1, count($readyList));
    }

    public function testGetDisplayOrders(): void
    {
        $orders = getDisplayOrders();
        $this->assertIsArray($orders);
        foreach ($orders as $o) {
            $this->assertArrayHasKey('name', $o);
            $this->assertArrayHasKey('platform', $o);
            $this->assertArrayHasKey('status', $o);
            $this->assertArrayHasKey('shelf', $o);
        }
    }

    public function testGetOrderStats(): void
    {
        $stats = getOrderStats();
        $this->assertArrayHasKey('active_orders', $stats);
        $this->assertArrayHasKey('preparing', $stats);
        $this->assertArrayHasKey('ready', $stats);
        $this->assertArrayHasKey('today_completed', $stats);
        $this->assertArrayHasKey('by_platform', $stats);
        $this->assertArrayHasKey('avg_wait_time', $stats);
    }

    public function testTrackApiUsage(): void
    {
        trackApiUsage('test-endpoint');
        trackApiUsage('test-endpoint', true);
        $this->assertTrue(true);
    }
}
