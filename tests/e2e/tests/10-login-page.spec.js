const { test, expect } = require('@playwright/test');
const { gotoLogin, parseColor, waitForTransitionEnd } = require('./helpers');

test.describe('Login page rendering', () => {
	test('renders with the stylesheet loaded and real computed styles applied', async ({ page }) => {
		const cssStatuses = [];
		page.on('response', (response) => {
			if (new URL(response.url()).pathname === '/css/app.css') {
				cssStatuses.push(response.status());
			}
		});

		await gotoLogin(page);

		expect(cssStatuses).toContain(200);
		expect(cssStatuses).not.toContain(404);

		const button = page.getByRole('button', { name: 'Přihlásit se' });
		await expect(button).toHaveCSS('background-color', 'rgb(166, 80, 31)');

		const card = page.locator('.rounded-card');
		const radius = await card.evaluate((el) => getComputedStyle(el).borderRadius);
		expect(radius).not.toBe('0px');
	});

	test('sign-in form has only a password field and a submit button, plus a CSRF token', async ({ page }) => {
		await gotoLogin(page);

		const form = page.locator('#frm-signInForm');
		await expect(form.locator('input:not([type=hidden])')).toHaveCount(1);
		await expect(form.locator('input[type=password]')).toHaveCount(1);
		await expect(form.getByRole('button')).toHaveCount(1);

		const csrfToken = form.locator('input[type=hidden][name="_token_"]');
		await expect(csrfToken).toHaveAttribute('value', /.+/);
	});

	test('password field shows a visible focus indicator', async ({ page }) => {
		await gotoLogin(page);
		const password = page.getByLabel('Heslo');

		await password.evaluate((el) => el.blur());
		await waitForTransitionEnd(password);

		const before = await password.evaluate((el) => {
			const style = getComputedStyle(el);
			return `${style.borderColor}|${style.boxShadow}`;
		});

		await password.focus();
		await waitForTransitionEnd(password);

		const after = await password.evaluate((el) => {
			const style = getComputedStyle(el);
			return `${style.borderColor}|${style.boxShadow}`;
		});

		expect(after).not.toBe(before);
	});

	test('never renders in dark mode, even when the OS prefers it', async ({ page }) => {
		await page.emulateMedia({ colorScheme: 'dark' });
		await gotoLogin(page);

		const bodyBg = parseColor(await page.evaluate(() => getComputedStyle(document.body).backgroundColor));

		expect(bodyBg.lightness).toBeGreaterThan(0.85);
	});
});
