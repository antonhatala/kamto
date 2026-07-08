// Phase 4 — Přehledy (Overview, /overview) and the service detail page (/service/detail/{id}):
// year summary, the payments heatmap (6 cell states), the yearly-service "inactive" months,
// year navigation, and the per-service detail with its history heatmap + payments list.
//
// Determinism: cell state (Paid/Skipped/Overdue/Gap/Inactive) is derived against the real clock
// (SystemClock), so the heatmap/detail tests operate in a FIXED PAST YEAR (2020). Every month of
// 2020 is in the past, so an unpaid payment row there is unambiguously "Po splatnosti", a paid
// one "Zaplaceno", etc. — no dependence on today. States are asserted via the cells' aria-labels
// (each gridcell's accessible name is "{service} · {month} {year} · {state}[ · {amount}]").
// Each test is self-contained: beforeEach resets the DB and logs in.
const { test, expect } = require('@playwright/test');
const { login, parseColor } = require('./helpers');
const { resetDb } = require('../reset-db');

const PAST = 2020; // every month is in the past relative to the real clock

/** The app content area (excludes header/flashes and the Tracy debug bar). */
function main(page) {
	return page.getByRole('main');
}

/** A dashboard row (listitem) for the service of the given name. */
function dashRow(page, name) {
	return main(page).getByRole('listitem').filter({ hasText: name });
}

/** A heatmap gridcell whose accessible name (aria-label) matches the given regex. */
function cell(page, re) {
	return main(page).getByRole('gridcell', { name: re });
}

async function addMonthlyService(page, { name, amount, dueDay }) {
	await page.goto('/service/add');
	await page.getByLabel('Název').fill(name);
	await page.getByLabel('Částka (Kč)').fill(amount);
	await page.getByLabel('Den splatnosti').fill(String(dueDay));
	await page.getByRole('button', { name: 'Uložit' }).click();
	await expect(page.getByRole('status')).toHaveText('Služba byla přidána.');
}

async function addYearlyService(page, { name, amount, dueDay, monthLabel }) {
	await page.goto('/service/add');
	await page.getByLabel('Název').fill(name);
	await page.getByLabel('Částka (Kč)').fill(amount);
	await main(page).getByText('Ročně', { exact: true }).click();
	await page.getByLabel('Den splatnosti').fill(String(dueDay));
	await page.getByLabel('Měsíc splatnosti').selectOption({ label: monthLabel });
	await page.getByRole('button', { name: 'Uložit' }).click();
	await expect(page.getByRole('status')).toHaveText('Služba byla přidána.');
}

/** Marks a service paid for a past period by driving the dashboard at that period. */
async function payViaDashboard(page, { year, month, name }) {
	await page.goto(`/?year=${year}&month=${month}`);
	await dashRow(page, name).getByRole('button', { name: `Označit ${name} jako zaplacené` }).click();
	await expect(page.getByRole('status')).toHaveText('Platba byla označena jako zaplacená.');
}

/** Marks a service skipped for a past period via the dashboard. */
async function skipViaDashboard(page, { year, month, name }) {
	await page.goto(`/?year=${year}&month=${month}`);
	await dashRow(page, name).getByRole('button', { name: `Přeskočit ${name} pro toto období` }).click();
	await expect(page.getByRole('status')).toHaveText('Platba byla přeskočena.');
}

test.describe('Overview & detail (Phase 4)', () => {
	test.beforeEach(async ({ page }) => {
		resetDb();
		await login(page);
	});

	test('empty year renders the overview without crashing (zero summary, empty heatmap/chart)', async ({ page }) => {
		await page.goto('/overview/');

		await expect(page).toHaveTitle('Přehledy – Kamto');
		await expect(main(page).getByRole('heading', { name: 'Přehledy', level: 1 })).toBeVisible();
		// No services and no payments → explicit empty messaging, no exception.
		await expect(main(page).getByText(/nejsou žádné služby/)).toBeVisible();
		await expect(main(page).getByText(/zatím žádná zaplacená platba/)).toBeVisible();
	});

	test('heatmap shows Paid / Skipped / Overdue / Gap states via cell aria-labels', async ({ page }) => {
		await addMonthlyService(page, { name: 'Netflix', amount: '199', dueDay: 15 });

		await payViaDashboard(page, { year: PAST, month: 1, name: 'Netflix' });
		await skipViaDashboard(page, { year: PAST, month: 2, name: 'Netflix' });
		// Overdue: create an unpaid payment row in a past month (pay, then "Vrátit").
		await payViaDashboard(page, { year: PAST, month: 3, name: 'Netflix' });
		await dashRow(page, 'Netflix').getByRole('button', { name: 'Vrátit Netflix mezi nezaplacené' }).click();

		await page.goto(`/overview/?year=${PAST}`);

		await expect(cell(page, /Netflix · Leden 2020 · Zaplaceno · 199 Kč/)).toBeVisible();
		await expect(cell(page, /Netflix · Únor 2020 · Přeskočeno/)).toBeVisible();
		await expect(cell(page, /Netflix · Březen 2020 · Po splatnosti/)).toBeVisible();
		await expect(cell(page, /Netflix · Duben 2020 · Pauza/)).toBeVisible();

		// "The paid month lights up" — its inner box carries a solid fill, unlike an empty Gap cell.
		const paidBg = await cell(page, /Leden 2020 · Zaplaceno/).locator('.hm-box').evaluate((el) => getComputedStyle(el).backgroundColor);
		const gapBg = await cell(page, /Duben 2020 · Pauza/).locator('.hm-box').evaluate((el) => getComputedStyle(el).backgroundColor);
		expect(paidBg).not.toBe(gapBg);
		expect(['rgba(0, 0, 0, 0)', 'transparent']).not.toContain(paidBg);
	});

	test('a yearly service has one live heatmap cell in its due month; the rest are Inactive', async ({ page }) => {
		await addYearlyService(page, { name: 'Pojistka', amount: '3500', dueDay: 15, monthLabel: 'Červen' });
		// A payment in the past year makes the yearly service appear in that year's heatmap.
		await payViaDashboard(page, { year: PAST, month: 6, name: 'Pojistka' });

		await page.goto(`/overview/?year=${PAST}`);

		// Its due month (June) is live; the other months are "Neaktivní" (distinct from "Pauza").
		await expect(cell(page, /Pojistka · Červen 2020 · Zaplaceno/)).toBeVisible();
		await expect(cell(page, /Pojistka · Leden 2020 · Neaktivní/)).toBeVisible();
		await expect(cell(page, /Pojistka · Prosinec 2020 · Neaktivní/)).toBeVisible();
		// Exactly the 11 non-due months are Inactive for this service.
		await expect(main(page).getByRole('gridcell', { name: /Pojistka · .* · Neaktivní/ })).toHaveCount(11);
	});

	test('year navigation (◀ / ▶ / Dnes) changes the displayed year', async ({ page }) => {
		await page.goto(`/overview/?year=${PAST}`);
		const nav = main(page).getByRole('navigation', { name: 'Volba roku' });
		await expect(nav.getByText(String(PAST))).toBeVisible();

		await nav.getByRole('link', { name: /Následující rok/ }).click();
		await expect(nav.getByText(String(PAST + 1))).toBeVisible();
		// The summary label reflects the new year too (it's a paragraph, not a heading).
		await expect(main(page).getByText(`Zaplaceno ${PAST + 1}`)).toBeVisible();

		await nav.getByRole('link', { name: /Předchozí rok/ }).click();
		await nav.getByRole('link', { name: /Předchozí rok/ }).click();
		await expect(nav.getByText(String(PAST - 1))).toBeVisible();

		// "Dnes" returns to the current year (default, no year query string).
		await nav.getByRole('link', { name: 'Dnes' }).click();
		await expect(page).toHaveURL(/\/overview\/(\?.*)?$/);
		await expect(page).not.toHaveURL(/year=/);
	});

	test('service detail shows header, payment history and links back to edit', async ({ page }) => {
		await addMonthlyService(page, { name: 'Netflix', amount: '199', dueDay: 15 });
		await payViaDashboard(page, { year: PAST, month: 1, name: 'Netflix' });

		// The service name in the list links to its detail.
		await page.goto('/service/');
		await main(page).getByRole('link', { name: 'Netflix', exact: true }).click();

		await expect(page).toHaveURL(/\/service\/detail\/\d+$/);
		await expect(page).toHaveTitle('Netflix – Kamto');
		await expect(main(page).getByRole('heading', { name: 'Netflix', level: 1 })).toBeVisible();
		// History summary + payments list + mini heatmap are present.
		await expect(main(page).getByRole('heading', { name: 'Historie po měsících' })).toBeVisible();
		await expect(main(page).getByRole('heading', { name: 'Platby' })).toBeVisible();
		await expect(main(page).getByText('Leden 2020', { exact: true })).toBeVisible();
		await expect(main(page).getByRole('table', { name: /Historie plateb služby Netflix/ })).toBeVisible();

		// "Upravit" leads to the edit form.
		await main(page).getByRole('link', { name: 'Upravit' }).click();
		await expect(page).toHaveURL(/\/service\/edit\/\d+$/);
		await expect(main(page).getByRole('heading', { name: 'Upravit službu' })).toBeVisible();
	});

	test('an archived service still has a detail page with history and an archived badge', async ({ page }) => {
		await addMonthlyService(page, { name: 'Netflix', amount: '199', dueDay: 15 });
		await payViaDashboard(page, { year: PAST, month: 1, name: 'Netflix' });

		// Archive it from the service list.
		await page.goto('/service/');
		await dashRow(page, 'Netflix').getByRole('button', { name: 'Archivovat' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla archivována.');

		await page.goto('/service/detail/1');
		await expect(main(page).getByRole('heading', { name: 'Netflix', level: 1 })).toBeVisible();
		await expect(main(page).getByText('Archivováno')).toBeVisible();
		// History is preserved for the archived service.
		await expect(main(page).getByText('Leden 2020', { exact: true })).toBeVisible();
	});

	test('overview is light (no dark mode) and the wide heatmap scrolls inside its own container on mobile', async ({ page }) => {
		await addMonthlyService(page, { name: 'Netflix', amount: '199', dueDay: 15 });
		await page.emulateMedia({ colorScheme: 'dark' });
		await page.setViewportSize({ width: 375, height: 667 });
		await page.goto('/overview/');

		// No dark mode.
		const bodyBg = parseColor(await page.evaluate(() => getComputedStyle(document.body).backgroundColor));
		expect(bodyBg.lightness).toBeGreaterThan(0.85);

		// The heatmap grid is wider than a phone screen, but its overflow-x-auto wrapper keeps it
		// scrolling internally: the wrapper itself fits the viewport, while the grid inside
		// overflows the wrapper (so it scrolls there, not on the page body).
		const container = main(page).locator('.heatmap-grid').locator('xpath=..');
		const metrics = await container.evaluate((el) => ({
			clientWidth: el.clientWidth,
			scrollWidth: el.scrollWidth,
			right: Math.round(el.getBoundingClientRect().right),
		}));
		expect(metrics.scrollWidth).toBeGreaterThan(metrics.clientWidth); // grid scrolls inside
		expect(metrics.right).toBeLessThanOrEqual(376); // wrapper fits the 375px viewport
	});
});
