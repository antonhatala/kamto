const { test, expect } = require('@playwright/test');
const { gotoLogin, submitLogin, pathOf, parseColor } = require('./helpers');

test.describe('Sign-in flow', () => {
	test('empty password shows a validation error and stays on the login page', async ({ page }) => {
		await gotoLogin(page);

		await page.locator('#frm-signInForm').evaluate((form) => {
			form.noValidate = true;
		});
		await page.getByRole('button', { name: 'Přihlásit se' }).click();

		await expect(page.getByRole('alert')).toHaveText('Zadejte heslo.');
		expect(pathOf(page)).toBe('/sign/in');
	});

	test('wrong password shows a red error alert and does not log in', async ({ page }) => {
		await gotoLogin(page);
		await submitLogin(page, 'not-the-password');

		const alert = page.getByRole('alert');
		await expect(alert).toHaveText('Nesprávné heslo.');

		const color = parseColor(await alert.evaluate((el) => getComputedStyle(el).color));
		if (color.space === 'oklch') {
			expect(color.chroma).toBeGreaterThan(0.1);
			expect(color.hue).toBeGreaterThan(0);
			expect(color.hue).toBeLessThan(60);
		} else {
			expect(color.r).toBeGreaterThan(color.g);
			expect(color.r).toBeGreaterThan(color.b);
		}

		expect(pathOf(page)).toBe('/sign/in');

		await page.goto('/');
		expect(pathOf(page)).toBe('/sign/in');
	});

	test('correct password logs in and lands on the dashboard', async ({ page }) => {
		await gotoLogin(page);
		await submitLogin(page, 'kamto');

		await expect(page).toHaveURL(/\/(\?.*)?$/);
		await expect(page).toHaveTitle('Přehled – Kamto');
		await expect(page.getByRole('heading', { name: 'Co zaplatit' })).toBeVisible();
		await expect(page.getByRole('banner').getByRole('button', { name: 'Odhlásit se' })).toBeVisible();
	});
});
