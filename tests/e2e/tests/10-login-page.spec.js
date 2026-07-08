// Login page rendering: real CSS applied (not just markup), form shape, and the
// no-dark-mode / focus-indicator accessibility sanity checks from the Phase 0 acceptance
// criteria.
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

		// accent-600 (#a6501f) is the app's branded button color — a plain unstyled <button>
		// would render in the browser's default control color, so this proves Tailwind's
		// output actually applied rather than the template rendering bare HTML.
		const button = page.getByRole('button', { name: 'Přihlásit se' });
		await expect(button).toHaveCSS('background-color', 'rgb(166, 80, 31)');

		// The login card uses the bespoke `rounded-card` radius token, not a default 0.
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

		// Nette's CSRF protection field, added via Form::addProtection().
		const csrfToken = form.locator('input[type=hidden][name="_token_"]');
		await expect(csrfToken).toHaveAttribute('value', /.+/);
	});

	test('password field shows a visible focus indicator', async ({ page }) => {
		await gotoLogin(page);
		const password = page.getByLabel('Heslo');

		// The field carries `autofocus`, which can win the race and already be focused by the
		// time we read "before" — blur it first for a deterministic unfocused baseline. Both
		// states also animate in via a CSS `transition`, so wait each one out before reading
		// computed style — otherwise an in-flight interpolated value can be sampled instead of
		// the settled one (and, coincidentally, look identical on both sides).
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

		// bg-stone-50 (~#fafaf9) — a light background (lightness close to 1) regardless of the
		// OS color-scheme preference; a real dark-mode background would score well below this.
		expect(bodyBg.lightness).toBeGreaterThan(0.85);
	});
});
