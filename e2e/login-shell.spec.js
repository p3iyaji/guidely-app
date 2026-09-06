import { test, expect } from '@playwright/test';

const email = 'e2e.admin@example.sch.uk';
const password = 'password';

test.describe('authenticated shell smoke', () => {
    test('signs in and lands on Role-aware app shell', async ({ page }) => {
        await page.goto('/login');

        await expect(page.getByRole('heading', { name: 'Sign in' })).toBeVisible();

        await page.locator('#email').fill(email);
        await page.locator('#password').fill(password);
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page).toHaveURL('/');
        await expect(page.getByTestId('app-shell')).toBeVisible();
        await expect(page.getByRole('link', { name: 'Pilot toolkit' })).toBeVisible();
        await expect(page.getByText('Sign in')).toHaveCount(0);
    });

    test('rejects invalid credentials without leaving login', async ({ page }) => {
        await page.goto('/login');

        await page.locator('#email').fill(email);
        await page.locator('#password').fill('wrong-password');
        await page.getByRole('button', { name: 'Sign in' }).click();

        await expect(page.getByTestId('login-error')).toBeVisible();
        await expect(page).toHaveURL(/\/login/);
    });
});
