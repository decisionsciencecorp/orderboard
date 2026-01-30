import { test, expect } from '@playwright/test';

test.describe('Display board', () => {
  test('display page loads', async ({ page }) => {
    await page.goto('/display/');
    await expect(page).toHaveTitle(/Order Board|Ghost Kitchen/i);
    await expect(page.getByText(/ORDER PICKUP|LIVE/i)).toBeVisible();
    await expect(page.getByText(/NAME|PLATFORM|STATUS|SHELF/i)).toBeVisible();
  });

  test('display page has orders container', async ({ page }) => {
    await page.goto('/display/');
    await expect(page.locator('#orders-container')).toBeVisible();
  });
});
