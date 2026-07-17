const fs = require('fs');
const { test, expect } = require('@playwright/test');
const { login, logout } = require('./helpers');
const { resetDb } = require('../reset-db');

test.use({ serviceWorkers: 'allow' });

const PAST = 2020;

function main(page) {
	return page.getByRole('main');
}

async function addMonthlyService(page, { name, amount, dueDay }) {
	await page.goto('/service/add');
	await page.getByLabel('Název').fill(name);
	await page.getByLabel('Částka (Kč)').fill(amount);
	await page.getByLabel('Den splatnosti').fill(String(dueDay));
	await page.getByRole('button', { name: 'Uložit' }).click();
	await expect(page.getByRole('status')).toHaveText('Služba byla přidána.');
}

async function payViaDashboard(page, { year, month, name }) {
	await page.goto(`/?year=${year}&month=${month}`);
	await main(page).getByRole('listitem').filter({ hasText: name })
		.getByRole('button', { name: `Označit ${name} jako zaplacené` }).click();
	await expect(page.getByRole('status')).toHaveText('Platba byla označena jako zaplacená.');
}

async function waitForServiceWorker(page, timeoutMs = 10_000) {
	return page.evaluate((timeout) => Promise.race([
		navigator.serviceWorker.ready.then(() => true),
		new Promise((resolve) => setTimeout(() => resolve(false), timeout)),
	]), timeoutMs);
}

test.describe('Web app manifest & head tags (Phase 5)', () => {
	test.beforeEach(() => {
		resetDb();
	});

	test('manifest.json is served with name/short_name/start_url/display/icons', async ({ request }) => {
		const response = await request.get('/manifest.json');
		expect(response.status()).toBe(200);
		const manifest = await response.json();

		expect(manifest.name).toBeTruthy();
		expect(manifest.short_name).toBeTruthy();
		expect(manifest.start_url).toBe('/');
		expect(manifest.display).toBe('standalone');

		const sizes = manifest.icons.map((icon) => icon.sizes);
		expect(sizes).toEqual(expect.arrayContaining(['192x192', '512x512']));
		expect(manifest.icons.some((icon) => icon.purpose === 'maskable')).toBe(true);
	});

	test('the signed-in page head links the manifest, a theme-color and an apple-touch-icon', async ({ page }) => {
		await login(page);

		await expect(page.locator('link[rel="manifest"]')).toHaveAttribute('href', '/manifest.json');
		await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', /^#[0-9a-f]{6}$/i);
		await expect(page.locator('link[rel="apple-touch-icon"]')).toHaveAttribute('href', '/icons/apple-touch-icon.png');
	});
});

test.describe('Service worker (Phase 5)', () => {
	test.beforeEach(() => {
		resetDb();
	});

	test('sw.js is served, app.js wires up registration, and it actually registers', async ({ page, request }) => {
		const swResponse = await request.get('/sw.js');
		expect(swResponse.status()).toBe(200);
		expect(await swResponse.text()).toContain('CACHE_VERSION');

		const appJs = await (await request.get('/js/app.js')).text();
		expect(appJs).toContain("serviceWorker.register('/sw.js')");

		await login(page);
		const becameReady = await waitForServiceWorker(page);
		expect(becameReady).toBe(true);

		const scope = await page.evaluate(async () => {
			const registration = await navigator.serviceWorker.getRegistration();
			return registration ? registration.scope : null;
		});
		expect(scope).toBeTruthy();
	});

	test('SECURITY: authenticated HTML is never written to the Cache Storage', async ({ page }) => {
		await login(page);
		await waitForServiceWorker(page);

		await page.goto('/overview/');
		await page.goto('/service/');
		await page.goto('/');

		let cacheUrls = null;
		try {
			cacheUrls = await page.evaluate(async () => {
				const [cacheName] = await caches.keys();
				if (!cacheName) {
					return [];
				}
				const cache = await caches.open(cacheName);
				const keys = await cache.keys();
				return keys.map((req) => new URL(req.url).pathname);
			});
		} catch {
			cacheUrls = null;
		}

		if (cacheUrls) {
			expect(cacheUrls.length).toBeGreaterThan(0);
			for (const pathname of cacheUrls) {
				expect(pathname, `unexpected cache entry (looks like a page route, not an asset): ${pathname}`)
					.toMatch(/^\/(css|js|icons)\/|^\/manifest\.json$|^\/offline\.html$/);
			}
			expect(cacheUrls).not.toContain('/');
			expect(cacheUrls).not.toContain('/overview/');
			expect(cacheUrls).not.toContain('/service/');
			expect(cacheUrls).not.toContain('/sign/in');
		}

		await logout(page);
		await page.goto('/overview/');
		expect(new URL(page.url()).pathname).toBe('/sign/in');
	});

	test('offline fallback: navigation shows the static offline page when the network is unavailable', async ({ page, context }) => {
		await login(page);
		await waitForServiceWorker(page);

		await context.setOffline(true);
		await page.goto('/overview/');
		await expect(page).toHaveTitle(/Offline/);
		await expect(page.getByText(/offline/i)).toBeVisible();

		await context.setOffline(false);
	});
});

test.describe('CSV export (Phase 5)', () => {
	test.beforeEach(async ({ page }) => {
		resetDb();
		await login(page);
	});

	test('exports a semicolon-separated, BOM-prefixed CSV that includes the added service', async ({ page }) => {
		await addMonthlyService(page, { name: 'Netflix', amount: '199', dueDay: 15 });
		await payViaDashboard(page, { year: PAST, month: 1, name: 'Netflix' });

		await page.goto(`/overview/?year=${PAST}`);
		const [download] = await Promise.all([
			page.waitForEvent('download'),
			main(page).getByRole('link', { name: /Exportovat platby/ }).click(),
		]);

		expect(download.suggestedFilename()).toBe(`kamto-platby-${PAST}.csv`);

		const filePath = await download.path();
		const buffer = fs.readFileSync(filePath);

		expect(buffer.subarray(0, 3)).toEqual(Buffer.from([0xef, 0xbb, 0xbf]));

		const text = buffer.toString('utf8');
		expect(text).toContain(';');
		expect(text.startsWith('\uFEFFSlužba;Kategorie')).toBe(true);
		expect(text).toContain('Netflix');
	});

	test('exporting an out-of-range year 404s instead of downloading', async ({ page }) => {
		const response = await page.goto('/overview/export?year=1999');
		expect(response?.status()).toBe(404);
	});
});

test.describe('Accessibility additions (Phase 5)', () => {
	test.beforeEach(async ({ page }) => {
		resetDb();
		await login(page);
	});

	test('skip-link is the first Tab stop and jumps to #main-content', async ({ page }) => {
		await page.keyboard.press('Tab');
		const skipLink = page.getByRole('link', { name: 'Přeskočit na obsah' });
		await expect(skipLink).toBeFocused();

		await page.keyboard.press('Enter');
		await expect(page).toHaveURL(/#main-content$/);
	});

	test('the active nav link carries aria-current="page"; others do not', async ({ page }) => {
		await expect(page.getByRole('link', { name: 'Co zaplatit' })).toHaveAttribute('aria-current', 'page');
		await expect(page.getByRole('link', { name: 'Přehledy' })).not.toHaveAttribute('aria-current', 'page');

		await page.goto('/overview/');
		await expect(page.getByRole('link', { name: 'Přehledy' })).toHaveAttribute('aria-current', 'page');
		await expect(page.getByRole('link', { name: 'Co zaplatit' })).not.toHaveAttribute('aria-current', 'page');
	});
});
