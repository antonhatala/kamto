// Sign-in form validation and the happy path (Phase 0 acceptance criteria).
const { test, expect } = require('@playwright/test');
const { gotoLogin, submitLogin, pathOf, parseColor } = require('./helpers');

test.describe('Sign-in flow', () => {
	test('empty password shows a validation error and stays on the login page', async ({ page }) => {
		await gotoLogin(page);

		// The rendered <input> carries a native HTML5 "required" attribute, which would make
		// the browser block submission client-side with its own tooltip before the request
		// ever reaches the server. Disable constraint validation for this submission so we
		// exercise (and can assert on) Nette's own server-side "Zadejte heslo." message.
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

		// Tailwind's `text-red-700` — assert it's a genuinely reddish, saturated color rather
		// than the default black body text (works whether the browser reports rgb() or oklch()).
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

		// Confirm we really aren't logged in — / must still bounce to the login page.
		await page.goto('/');
		expect(pathOf(page)).toBe('/sign/in');
	});

	test('correct password logs in and lands on the service list', async ({ page }) => {
		await gotoLogin(page);
		await submitLogin(page, 'kamto');

		// Home:default forwards straight to Service:default since Phase 2.
		await expect(page).toHaveURL(/\/service\/(\?.*)?$/);
		await expect(page).toHaveTitle('Služby – Kamto');
		// Logout is a POST form in the header now, so its control is a button, not a link.
		await expect(page.getByRole('banner').getByRole('button', { name: 'Odhlásit se' })).toBeVisible();
	});
});
