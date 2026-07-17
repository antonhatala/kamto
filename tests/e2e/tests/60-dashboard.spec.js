const { test, expect } = require('@playwright/test');
const { login, parseColor } = require('./helpers');
const { resetDb } = require('../reset-db');

const FUTURE = { year: 2099, month: 11 };
const FUTURE_PATH = `/?year=${FUTURE.year}&month=${FUTURE.month}`;

function main(page) {
	return page.getByRole('main');
}

function dashRow(page, name) {
	return main(page).getByRole('listitem').filter({ hasText: name });
}

function remainingTotal(page) {
	const summary = page.locator('section').filter({ hasText: 'Zbývá zaplatit' });
	return summary.getByText('Zbývá zaplatit').locator('xpath=following-sibling::p[1]');
}

function paidTotal(page) {
	const summary = page.locator('section').filter({ hasText: 'Zbývá zaplatit' });
	return summary.getByText('Zaplaceno', { exact: true }).locator('xpath=following-sibling::p[1]');
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

test.describe('Dashboard "Co zaplatit" (Phase 3)', () => {
	test.beforeEach(async ({ page }) => {
		resetDb();
		await login(page);
	});

	test('empty database shows the "nothing to pay" state', async ({ page }) => {
		await expect(page).toHaveTitle('Přehled – Kamto');
		await expect(main(page).getByRole('heading', { name: 'Co zaplatit' })).toBeVisible();
		await expect(main(page).getByText('Tento měsíc nemáte nic k zaplacení')).toBeVisible();
	});

	test('adding a service makes it a payment candidate on the current dashboard', async ({ page }) => {
		await addMonthlyService(page, { name: 'Spotify', amount: '199,50', dueDay: 15 });

		await page.goto('/');
		const row = dashRow(page, 'Spotify');
		await expect(row).toBeVisible();
		await expect(row).toContainText('199,50 Kč');
		await expect(row).toContainText(/15\.\s\d{1,2}\.\s\d{4}/);
		await expect(row.getByRole('button', { name: 'Označit Spotify jako zaplacené' })).toBeVisible();
		await expect(remainingTotal(page)).toHaveText('199,50 Kč');
	});

	test('marking a service paid moves it to "Zaplaceno" and updates totals; "Vrátit" reverses it', async ({ page }) => {
		await addMonthlyService(page, { name: 'Spotify', amount: '199,50', dueDay: 15 });

		await page.goto(FUTURE_PATH);
		const row = dashRow(page, 'Spotify');
		await expect(remainingTotal(page)).toHaveText('199,50 Kč');
		await expect(paidTotal(page)).toHaveText('0 Kč');

		await row.getByRole('button', { name: 'Označit Spotify jako zaplacené' }).click();

		await expect(page.getByRole('status')).toHaveText('Platba byla označena jako zaplacená.');
		await expect(dashRow(page, 'Spotify').getByRole('button', { name: 'Vrátit Spotify mezi nezaplacené' })).toBeVisible();
		await expect(remainingTotal(page)).toHaveText('0 Kč');
		await expect(paidTotal(page)).toHaveText('199,50 Kč');

		await dashRow(page, 'Spotify').getByRole('button', { name: 'Vrátit Spotify mezi nezaplacené' }).click();
		await expect(page.getByRole('status')).toHaveText('Platba byla vrácena mezi nezaplacené.');
		await expect(dashRow(page, 'Spotify').getByRole('button', { name: 'Označit Spotify jako zaplacené' })).toBeVisible();
		await expect(remainingTotal(page)).toHaveText('199,50 Kč');
		await expect(paidTotal(page)).toHaveText('0 Kč');
	});

	test('skipping excludes a service from "Zbývá zaplatit" and it does not regenerate; "Zrušit přeskočení" reverses it', async ({ page }) => {
		await addMonthlyService(page, { name: 'Spotify', amount: '199,50', dueDay: 15 });

		await page.goto(FUTURE_PATH);
		await dashRow(page, 'Spotify').getByRole('button', { name: 'Přeskočit Spotify pro toto období' }).click();

		await expect(page.getByRole('status')).toHaveText('Platba byla přeskočena.');
		await expect(remainingTotal(page)).toHaveText('0 Kč');
		await expect(main(page).getByRole('button', { name: 'Označit Spotify jako zaplacené' })).toHaveCount(0);

		await page.reload();
		await expect(main(page).getByRole('button', { name: 'Označit Spotify jako zaplacené' })).toHaveCount(0);
		await expect(remainingTotal(page)).toHaveText('0 Kč');

		const skipped = main(page).locator('details').filter({ hasText: 'Zrušit přeskočení' });
		await skipped.locator('summary').click();
		await skipped.getByRole('button', { name: 'Zrušit přeskočení u Spotify' }).click();

		await expect(page.getByRole('status')).toHaveText('Přeskočení bylo zrušeno.');
		await expect(dashRow(page, 'Spotify').getByRole('button', { name: 'Označit Spotify jako zaplacené' })).toBeVisible();
		await expect(remainingTotal(page)).toHaveText('199,50 Kč');
	});

	test('confirming the amount dialog pays the service with the edited amount; an invalid amount is rejected', async ({ page }) => {
		await addMonthlyService(page, { name: 'Spotify', amount: '199,50', dueDay: 15 });

		await page.goto(FUTURE_PATH);
		await dashRow(page, 'Spotify').getByRole('button', { name: 'Upravit částku u Spotify' }).click();

		const dialog = page.getByRole('dialog');
		await expect(dialog).toBeVisible();

		const [box, viewport] = [await dialog.boundingBox(), page.viewportSize()];
		const center = { x: box.x + box.width / 2, y: box.y + box.height / 2 };
		expect(Math.abs(center.x - viewport.width / 2)).toBeLessThan(2);
		expect(Math.abs(center.y - viewport.height / 2)).toBeLessThan(2);

		await expect(dialog.getByLabel('Částka (Kč)')).toHaveAttribute('autocomplete', 'off');

		await dialog.getByLabel('Částka (Kč)').fill('abc');
		await dialog.getByRole('button', { name: 'Zaplatit' }).click();

		await expect(page.getByRole('dialog')).toBeVisible();
		await expect(page.getByRole('dialog').getByText('Zadejte platnou částku.')).toBeVisible();
		await expect(remainingTotal(page)).toHaveText('199,50 Kč');
		await expect(paidTotal(page)).toHaveText('0 Kč');

		await page.getByRole('dialog').getByLabel('Částka (Kč)').fill('349,50');
		await page.getByRole('dialog').getByRole('button', { name: 'Zaplatit' }).click();

		await expect(page.getByRole('status')).toHaveText('Platba byla zaplacena s upravenou částkou.');
		await expect(dashRow(page, 'Spotify')).toContainText('349,50 Kč');
		await expect(dashRow(page, 'Spotify').getByRole('button', { name: 'Vrátit Spotify mezi nezaplacené' })).toBeVisible();
		await expect(remainingTotal(page)).toHaveText('0 Kč');
		await expect(paidTotal(page)).toHaveText('349,50 Kč');
	});

	test('a yearly service appears only in its due month', async ({ page }) => {
		await addYearlyService(page, { name: 'PojistkaAuto', amount: '3500', dueDay: 15, monthLabel: 'Březen' });

		await page.goto(`/?year=${FUTURE.year}&month=7`);
		await expect(dashRow(page, 'PojistkaAuto')).toHaveCount(0);

		await page.goto(`/?year=${FUTURE.year}&month=3`);
		const row = dashRow(page, 'PojistkaAuto');
		await expect(row).toBeVisible();
		await expect(row).toContainText('3 500 Kč');
		await expect(row).toContainText(`15. 3. ${FUTURE.year}`);
	});

	test('month navigation (◀ / ▶ / Dnes) changes the displayed period', async ({ page }) => {
		await page.goto(`/?year=${FUTURE.year}&month=3`);
		const period = main(page).getByRole('navigation', { name: 'Volba období' });
		await expect(period.getByText(`Březen ${FUTURE.year}`)).toBeVisible();

		await period.getByRole('link', { name: /Následující měsíc/ }).click();
		await expect(period.getByText(`Duben ${FUTURE.year}`)).toBeVisible();

		await period.getByRole('link', { name: /Předchozí měsíc/ }).click();
		await period.getByRole('link', { name: /Předchozí měsíc/ }).click();
		await expect(period.getByText(`Únor ${FUTURE.year}`)).toBeVisible();

		await period.getByRole('link', { name: 'Dnes' }).click();
		await expect(page).toHaveURL(/\/(\?.*)?$/);
		await expect(page).not.toHaveURL(/month=/);
	});

	test('dashboard never renders in dark mode, even when the OS prefers it', async ({ page }) => {
		await page.emulateMedia({ colorScheme: 'dark' });
		await page.goto('/');

		const bodyBg = parseColor(await page.evaluate(() => getComputedStyle(document.body).backgroundColor));
		expect(bodyBg.lightness).toBeGreaterThan(0.85);
	});
});
