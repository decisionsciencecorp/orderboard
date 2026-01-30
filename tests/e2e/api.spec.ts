import { test, expect } from '@playwright/test';

test.describe('API (browser fetch)', () => {
  test('display endpoint returns JSON without auth', async ({ request }) => {
    const res = await request.get('/api/display.php');
    expect(res.ok()).toBeTruthy();
    const data = await res.json();
    expect(data.success).toBe(true);
    expect(Array.isArray(data.orders)).toBe(true);
    expect(typeof data.refresh_interval).toBe('number');
  });

  test('list-orders without API key returns 401', async ({ request }) => {
    const res = await request.get('/api/list-orders.php');
    expect(res.status()).toBe(401);
    const data = await res.json();
    expect(data.success).toBe(false);
    expect(data.error?.toLowerCase()).toMatch(/api key|invalid|missing/);
  });

  test('create-order without API key returns 401', async ({ request }) => {
    const res = await request.post('/api/create-order.php', {
      data: { customer_name: 'Test', platform: 'doordash' },
    });
    expect(res.status()).toBe(401);
  });
});
