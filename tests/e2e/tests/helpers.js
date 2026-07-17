const { expect } = require('@playwright/test');

async function gotoLogin(page) {
	await page.goto('/sign/in');
	await expect(page.getByRole('heading', { name: 'Kamto' })).toBeVisible();
}

async function submitLogin(page, password) {
	await page.getByLabel('Heslo').fill(password);
	await page.getByRole('button', { name: 'Přihlásit se' }).click();
}

async function login(page, password = 'kamto') {
	await gotoLogin(page);
	await submitLogin(page, password);
	await expect(page).toHaveURL(/\/(\?.*)?$/);
	await expect(page.getByRole('heading', { name: 'Co zaplatit' })).toBeVisible();
}

async function logout(page) {
	await page.getByRole('button', { name: 'Odhlásit se' }).click();
	await expect(page).toHaveURL(/\/sign\/in/);
}

function pathOf(page) {
	return new URL(page.url()).pathname;
}

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

module.exports = { gotoLogin, submitLogin, login, logout, pathOf, parseColor, waitForTransitionEnd };
