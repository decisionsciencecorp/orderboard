import { test, expect } from '@playwright/test';

test.describe('Admin', () => {
  test('login page loads', async ({ page }) => {
    await page.goto('/admin/');
    await expect(page).toHaveURL(/\/admin\/login\.php|\/admin\/$/);
    await expect(page.locator('body')).toContainText(/Ghost Kitchen|Admin|Login/i);
  });

  test('login with default credentials redirects to dashboard', async ({ page }) => {
    await page.goto('/admin/login.php');
    await page.getByLabel(/username/i).fill('admin');
    await page.getByLabel(/password/i).fill('go0dp4ssw0rd');
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page).toHaveURL(/\/admin\/index\.php/);
    await expect(page.locator('body')).toContainText(/Admin|Dashboard|Active Orders/i);
  });

  test('invalid login shows error', async ({ page }) => {
    await page.goto('/admin/login.php');
    await page.getByLabel(/username/i).fill('admin');
    await page.getByLabel(/password/i).fill('wrong');
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page).toHaveURL(/\/admin\/login\.php/);
    await expect(page.locator('.alert-danger')).toContainText(/invalid|password/i);
  });

  test('dashboard shows stats and new order form when logged in', async ({ page }) => {
    await page.goto('/admin/login.php');
    await page.getByLabel(/username/i).fill('admin');
    await page.getByLabel(/password/i).fill('go0dp4ssw0rd');
    await page.getByRole('button', { name: /login/i }).click();
    await expect(page).toHaveURL(/\/admin\/index\.php/);
    await expect(page.getByText(/Active Orders|Preparing|Ready/i)).toBeVisible();
    await expect(page.getByText(/New Order/i)).toBeVisible();
    await expect(page.getByRole('button', { name: /create order/i })).toBeVisible();
  });

  test('can create order from dashboard', async ({ page }) => {
    await page.goto('/admin/login.php');
    await page.getByLabel(/username/i).fill('admin');
    await page.getByLabel(/password/i).fill('go0dp4ssw0rd');
    await page.getByRole('button', { name: /login/i }).click();
    await page.goto('/admin/index.php');
    await page.getByLabel(/customer name/i).fill('E2E Test Customer');
    await page.getByLabel(/platform/i).selectOption('doordash');
    await page.getByRole('button', { name: /create order/i }).click();
    await expect(page.locator('.alert-success')).toContainText(/created|success/i);
    await expect(page.locator('table')).toContainText(/E2E Test|JOHN|TIMMY|display name/i);
  });

  test('API Keys page requires login', async ({ page }) => {
    await page.goto('/admin/api-keys.php');
    await expect(page).toHaveURL(/\/admin\/login\.php/);
  });

  test('Stats page requires login', async ({ page }) => {
    await page.goto('/admin/stats.php');
    await expect(page).toHaveURL(/\/admin\/login\.php/);
  });

  test('logout redirects to login', async ({ page }) => {
    await page.goto('/admin/login.php');
    await page.getByLabel(/username/i).fill('admin');
    await page.getByLabel(/password/i).fill('go0dp4ssw0rd');
    await page.getByRole('button', { name: /login/i }).click();
    await page.goto('/admin/logout.php');
    await expect(page).toHaveURL(/\/admin\/login\.php/);
  });
});
