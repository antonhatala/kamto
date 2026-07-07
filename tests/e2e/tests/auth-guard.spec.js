// Session/guard behaviour: unauthenticated users get bounced to the login page, and logging
// out actually ends the session rather than just changing what's displayed.
const { test, expect } = require('@playwright/test');
const { login, pathOf } = require('./helpers');

test.describe('Auth guard', () => {
	test('unauthenticated access to / redirects to the login page', async ({ page }) => {
		await page.goto('/');

		expect(pathOf(page)).toBe('/sign/in');
		await expect(page.getByRole('heading', { name: 'Kamto' })).toBeVisible();
	});

	test('unauthenticated direct access to the Home presenter also redirects to login', async ({ page }) => {
		await page.goto('/home/default');

		expect(pathOf(page)).toBe('/sign/in');
	});

	test('logging out ends the session — / redirects to login again', async ({ page }) => {
		await login(page);
		await expect(page.getByRole('heading', { name: 'Přihlášen' })).toBeVisible();

		await page.getByRole('link', { name: 'Odhlásit se' }).click();

		expect(pathOf(page)).toBe('/sign/in');
		await expect(page.getByText('Byli jste odhlášeni.')).toBeVisible();

		// The session is gone — the protected Home page bounces back to login again.
		await page.goto('/');
		expect(pathOf(page)).toBe('/sign/in');
	});

	test('direct access to a protected page after logout redirects to login', async ({ page }) => {
		await login(page);
		await page.getByRole('link', { name: 'Odhlásit se' }).click();
		expect(pathOf(page)).toBe('/sign/in');

		await page.goto('/home/default');
		expect(pathOf(page)).toBe('/sign/in');
	});
});
