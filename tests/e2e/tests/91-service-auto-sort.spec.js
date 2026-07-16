// Increment 3 — automatic service ordering by due day. The manual ↑/↓ reorder buttons (and the
// underlying service.sort_order column) were removed; the service list, the yearly overview
// heatmap and the "Co zaplatit" dashboard now all read services via
// ServiceRepository::findAll()/findArchived(), ordered `is_sliding ASC, due_day ASC, id ASC` —
// non-sliding services by due day (1→31) regardless of creation order, sliding
// ("Platím kdykoliv v měsíci") services always last. A yearly service's due day interleaves
// with monthly services on equal footing (period is irrelevant to the sort key).
//
// Each test resets the DB for a clean, order-independent slate (see reset-db.js). Content
// queries are scoped to the `main` landmark (the dev-mode Tracy bar injects its own
// list/listitem elements); flash messages render above <main> and stay page-scoped.
const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');
const { resetDb } = require('../reset-db');

/** The app content area (excludes header/flashes and the Tracy debug bar). */
function main(page) {
	return page.getByRole('main');
}

/** The active-list row (listitem) for a service of the given name, on /service/. */
function serviceRow(page, name) {
	return main(page).getByRole('listitem').filter({ has: page.getByRole('link', { name, exact: true }) });
}

/** Adds a monthly service through the UI — either sliding (no due day) or with an explicit due day. */
async function addMonthlyService(page, { name, amount, sliding = false, dueDay }) {
	await page.goto('/service/add');
	await page.getByLabel('Název').fill(name);
	await page.getByLabel('Částka (Kč)').fill(amount);
	if (sliding) {
		await page.getByLabel('Platím kdykoliv v měsíci').check();
	} else {
		await page.getByLabel('Den splatnosti').fill(String(dueDay));
	}
	await page.getByRole('button', { name: 'Uložit' }).click();
	await expect(page.getByRole('status')).toHaveText('Služba byla přidána.');
}

/** Adds a yearly service through the UI (period toggle reveals the month select). */
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

/**
 * Trimmed text of every active card's primary (name/detail) link, in DOM order. Each `<li>`
 * carries a second "Upravit" link, so this must resolve the first `<a>` per item individually —
 * `getByRole('listitem').locator('a').first()` would narrow to a single anchor across the
 * *whole* list, not one per item.
 */
async function listedOrder(page) {
	const items = await main(page).getByRole('listitem').all();
	const names = [];
	for (const item of items) {
		names.push(((await item.locator('a').first().textContent()) ?? '').trim());
	}
	return names;
}

test.describe('Automatic service sort by due day (Increment 3)', () => {
	test.beforeEach(async ({ page }) => {
		resetDb();
		await login(page);
	});

	test('service list orders by due day ascending regardless of creation order, sliding services always last, yearly interleaves by its own day', async ({ page }) => {
		// Deliberately created out of due-day order: 20, then 1, then 5, then a sliding one
		// (no due day at all), then a yearly service whose due day (10) falls between the
		// monthly ones above.
		await addMonthlyService(page, { name: 'Rent', amount: '10000', dueDay: 20 });
		await addMonthlyService(page, { name: 'Internet', amount: '600', dueDay: 1 });
		await addMonthlyService(page, { name: 'Cleaning', amount: '300', dueDay: 5 });
		await addMonthlyService(page, { name: 'Coffee', amount: '150', sliding: true });
		await addYearlyService(page, { name: 'Insurance', amount: '3500', dueDay: 10, monthLabel: 'Červen' });

		await page.goto('/service/');
		const items = main(page).getByRole('listitem');
		await expect(items).toHaveCount(5);

		// Expected order: due day 1, 5, 10 (yearly, interleaved), 20, then the sliding service
		// last — independent of the 20/1/5/sliding/10 creation order above.
		await expect(listedOrder(page)).resolves.toEqual(['Internet', 'Cleaning', 'Insurance', 'Rent', 'Coffee']);

		// Sanity on the individual rows' displayed metadata.
		await expect(serviceRow(page, 'Internet')).toContainText('Splatnost 1.');
		await expect(serviceRow(page, 'Cleaning')).toContainText('Splatnost 5.');
		await expect(serviceRow(page, 'Insurance')).toContainText('Splatnost 10. 6.');
		await expect(serviceRow(page, 'Rent')).toContainText('Splatnost 20.');
		await expect(serviceRow(page, 'Coffee').getByText('Kdykoliv', { exact: true })).toBeVisible();
		await expect(serviceRow(page, 'Coffee')).not.toContainText('Splatnost');

		// Order is stable across a reload (freshly re-queried from the DB, not client state).
		await page.reload();
		await expect(listedOrder(page)).resolves.toEqual(['Internet', 'Cleaning', 'Insurance', 'Rent', 'Coffee']);
	});

	test('no manual reordering controls: cards only carry "Upravit" (left) and "Archivovat" (right), no ↑/↓ of any kind', async ({ page }) => {
		await addMonthlyService(page, { name: 'Rent', amount: '10000', dueDay: 20 });
		await addMonthlyService(page, { name: 'Internet', amount: '600', dueDay: 1 });

		await page.goto('/service/');

		await expect(main(page).getByRole('button', { name: /Posunout/ })).toHaveCount(0);
		await expect(main(page).getByText('↑', { exact: true })).toHaveCount(0);
		await expect(main(page).getByText('↓', { exact: true })).toHaveCount(0);

		// Each card's footer has exactly two controls: "Upravit" first (left), "Archivovat" last
		// (right, pushed by `ml-auto`) — verified per row via DOM order.
		const items = main(page).getByRole('listitem');
		for (const item of await items.all()) {
			const controls = item.locator('div.border-t').locator('a, button');
			await expect(controls).toHaveCount(2);
			await expect(controls.first()).toHaveText('Upravit');
			await expect(controls.last()).toHaveText('Archivovat');
		}
	});

	test('the yearly overview heatmap lists services in the same automatic order as the service list, and both pages render without error', async ({ page }) => {
		await addMonthlyService(page, { name: 'Rent', amount: '10000', dueDay: 20 });
		await addMonthlyService(page, { name: 'Internet', amount: '600', dueDay: 1 });
		await addMonthlyService(page, { name: 'Cleaning', amount: '300', dueDay: 5 });
		await addMonthlyService(page, { name: 'Coffee', amount: '150', sliding: true });
		await addYearlyService(page, { name: 'Insurance', amount: '3500', dueDay: 10, monthLabel: 'Červen' });

		await page.goto('/service/');
		const listOrder = await listedOrder(page);

		// Heatmap (freshly created services show up in the current year without needing a
		// payment yet, see YearHeatmap::build) — renders without error and its row order matches
		// the service list order exactly (same repository query/sort key).
		await page.goto('/overview/');
		await expect(page).toHaveTitle('Přehledy – Kamto');
		await expect(main(page).getByRole('heading', { name: 'Přehledy', level: 1 })).toBeVisible();

		const rowHeaders = main(page).getByRole('rowheader');
		await expect(rowHeaders).toHaveCount(5);
		const heatmapOrder = (await rowHeaders.locator('a').allTextContents())
			.map((name) => name.trim());
		expect(heatmapOrder).toEqual(listOrder);

		// Dashboard "Co zaplatit" also renders without error against the same (formerly
		// sort_order-dependent) service set — the monthly ones are always candidates.
		await page.goto('/');
		await expect(page).toHaveTitle('Přehled – Kamto');
		await expect(main(page).getByRole('heading', { name: 'Co zaplatit' })).toBeVisible();
		await expect(main(page).getByText('Rent', { exact: true })).toBeVisible();
		await expect(main(page).getByText('Internet', { exact: true })).toBeVisible();
		await expect(main(page).getByText('Coffee', { exact: true })).toBeVisible();
	});

	test('happy path still works: adding an earlier due day re-sorts the list, editing keeps position stable, and archiving/reactivating works', async ({ page }) => {
		await addMonthlyService(page, { name: 'Netflix', amount: '199', dueDay: 15 });
		await page.goto('/service/');
		await expect(listedOrder(page)).resolves.toEqual(['Netflix']);

		// Adding a service with an earlier due day puts it first automatically.
		await addMonthlyService(page, { name: 'Spotify', amount: '99', dueDay: 3 });
		await page.goto('/service/');
		await expect(listedOrder(page)).resolves.toEqual(['Spotify', 'Netflix']);

		// Editing an unrelated field (amount) does not disturb the order.
		await serviceRow(page, 'Netflix').getByRole('link', { name: 'Upravit' }).click();
		await page.getByLabel('Částka (Kč)').fill('249');
		await page.getByRole('button', { name: 'Uložit' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla upravena.');
		await expect(listedOrder(page)).resolves.toEqual(['Spotify', 'Netflix']);
		await expect(serviceRow(page, 'Netflix')).toContainText('249 Kč');

		// Archiving removes it from the active list; reactivating restores it (and its due-day
		// position — Spotify's due day is unchanged).
		await serviceRow(page, 'Netflix').getByRole('button', { name: 'Archivovat' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla archivována.');
		await expect(listedOrder(page)).resolves.toEqual(['Spotify']);

		await main(page).getByText('Archiv (1)').click();
		await main(page).locator('details').getByRole('button', { name: 'Obnovit' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla obnovena.');
		await expect(listedOrder(page)).resolves.toEqual(['Spotify', 'Netflix']);
	});
});
