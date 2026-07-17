const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');
const { resetDb } = require('../reset-db');

function main(page) {
	return page.getByRole('main');
}

function serviceRow(page, name) {
	return main(page).getByRole('listitem').filter({ has: page.getByRole('link', { name, exact: true }) });
}

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
		await addMonthlyService(page, { name: 'Rent', amount: '10000', dueDay: 20 });
		await addMonthlyService(page, { name: 'Internet', amount: '600', dueDay: 1 });
		await addMonthlyService(page, { name: 'Cleaning', amount: '300', dueDay: 5 });
		await addMonthlyService(page, { name: 'Coffee', amount: '150', sliding: true });
		await addYearlyService(page, { name: 'Insurance', amount: '3500', dueDay: 10, monthLabel: 'Červen' });

		await page.goto('/service/');
		const items = main(page).getByRole('listitem');
		await expect(items).toHaveCount(5);

		await expect(listedOrder(page)).resolves.toEqual(['Internet', 'Cleaning', 'Insurance', 'Rent', 'Coffee']);

		await expect(serviceRow(page, 'Internet')).toContainText('Splatnost 1.');
		await expect(serviceRow(page, 'Cleaning')).toContainText('Splatnost 5.');
		await expect(serviceRow(page, 'Insurance')).toContainText('Splatnost 10. 6.');
		await expect(serviceRow(page, 'Rent')).toContainText('Splatnost 20.');
		await expect(serviceRow(page, 'Coffee').getByText('Kdykoliv', { exact: true })).toBeVisible();
		await expect(serviceRow(page, 'Coffee')).not.toContainText('Splatnost');

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

		await page.goto('/overview/');
		await expect(page).toHaveTitle('Přehledy – Kamto');
		await expect(main(page).getByRole('heading', { name: 'Přehledy', level: 1 })).toBeVisible();

		const rowHeaders = main(page).getByRole('rowheader');
		await expect(rowHeaders).toHaveCount(5);
		const heatmapOrder = (await rowHeaders.locator('a').allTextContents())
			.map((name) => name.trim());
		expect(heatmapOrder).toEqual(listOrder);

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

		await addMonthlyService(page, { name: 'Spotify', amount: '99', dueDay: 3 });
		await page.goto('/service/');
		await expect(listedOrder(page)).resolves.toEqual(['Spotify', 'Netflix']);

		await serviceRow(page, 'Netflix').getByRole('link', { name: 'Upravit' }).click();
		await page.getByLabel('Částka (Kč)').fill('249');
		await page.getByRole('button', { name: 'Uložit' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla upravena.');
		await expect(listedOrder(page)).resolves.toEqual(['Spotify', 'Netflix']);
		await expect(serviceRow(page, 'Netflix')).toContainText('249 Kč');

		await serviceRow(page, 'Netflix').getByRole('button', { name: 'Archivovat' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla archivována.');
		await expect(listedOrder(page)).resolves.toEqual(['Spotify']);

		await main(page).getByText('Archiv (1)').click();
		await main(page).locator('details').getByRole('button', { name: 'Obnovit' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla obnovena.');
		await expect(listedOrder(page)).resolves.toEqual(['Spotify', 'Netflix']);
	});
});
