// Shared helpers for the sign-in flow, used across spec files.
const { expect } = require('@playwright/test');

/** Navigates straight to the login page and waits for it to render. */
async function gotoLogin(page) {
	await page.goto('/sign/in');
	await expect(page.getByRole('heading', { name: 'Kamto' })).toBeVisible();
}

/** Fills in the password field and submits the sign-in form (does not wait for the result). */
async function submitLogin(page, password) {
	await page.getByLabel('Heslo').fill(password);
	await page.getByRole('button', { name: 'Přihlásit se' }).click();
}

/** Logs in with the given password (the local dev password by default) and waits for Home. */
async function login(page, password = 'kamto') {
	await gotoLogin(page);
	await submitLogin(page, password);
	await expect(page).toHaveURL(/\/$/);
}

/** Path portion of the page's current URL, ignoring query string (e.g. flash `?_fid=`). */
function pathOf(page) {
	return new URL(page.url()).pathname;
}

/**
 * Parses a browser-reported CSS color string for semantic style assertions (lightness / hue /
 * saturation) without pinning tests to one exact numeric encoding. Tailwind v4's built-in
 * palette (red-700, stone-50, ...) is defined via oklch(), and this Chromium build reports
 * `getComputedStyle` values back in that same notation instead of down-converting to rgb — only
 * literal hex/rgb sources (like this app's custom `accent-*` tokens) come back as rgb().
 */
function parseColor(cssColor) {
	const oklch = cssColor.match(/^oklch\(([\d.]+)\s+([\d.]+)\s+([\d.]+)/);
	if (oklch) {
		return { space: 'oklch', lightness: Number(oklch[1]), chroma: Number(oklch[2]), hue: Number(oklch[3]) };
	}

	const rgb = cssColor.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
	if (rgb) {
		const [r, g, b] = [Number(rgb[1]), Number(rgb[2]), Number(rgb[3])];
		return { space: 'rgb', r, g, b, lightness: (r + g + b) / 3 / 255 };
	}

	throw new Error(`Unrecognized color format: ${cssColor}`);
}

/**
 * Waits for an element's CSS transition (e.g. the focus ring's `transition` utility) to settle,
 * so a computed-style read afterwards reflects the final state rather than an in-flight
 * interpolated value. Resolves on the real `transitionend` event, with a small timeout fallback
 * for elements that aren't actually transitioning (duration 0).
 */
async function waitForTransitionEnd(locator) {
	await locator.evaluate((el) => new Promise((resolve) => {
		const durationMs = (parseFloat(getComputedStyle(el).transitionDuration) || 0) * 1000;
		const timer = setTimeout(resolve, durationMs + 100);
		el.addEventListener('transitionend', () => {
			clearTimeout(timer);
			resolve();
		}, { once: true });
	}));
}

module.exports = { gotoLogin, submitLogin, login, pathOf, parseColor, waitForTransitionEnd };
