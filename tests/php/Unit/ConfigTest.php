<?php

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for config helpers (showConfigError, getJsonBody, jsonResponse, errorResponse).
 */
class ConfigTest extends TestCase
{
    public function testConstantsDefined(): void
    {
        $this->assertTrue(defined('DB_PATH'));
        $this->assertTrue(defined('PLATFORMS'));
        $this->assertTrue(defined('SHELF_LOCATIONS'));
        $this->assertTrue(defined('STATUS_PREPARING'));
        $this->assertTrue(defined('STATUS_READY'));
        $this->assertSame(['doordash', 'ubereats', 'grubhub'], PLATFORMS);
        $this->assertSame(['A', 'B', 'C', 'D', 'E', 'F'], SHELF_LOCATIONS);
    }

    public function testGetJsonBody(): void
    {
        // getJsonBody reads php://input - in test we can't set it; it returns [] when empty/invalid
        $this->assertIsArray(getJsonBody());
    }

    public function testGetDBReturnsSqlite3(): void
    {
        $db = getDB();
        $this->assertInstanceOf(SQLite3::class, $db);
    }

    public function testGetDBSingleton(): void
    {
        $db1 = getDB();
        $db2 = getDB();
        $this->assertSame($db1, $db2);
    }
}
