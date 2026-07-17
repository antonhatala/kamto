const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');
const { resetDb } = require('../reset-db');

const PAST = 2020;

function main(page) {
	return page.getByRole('main');
}

function serviceRow(page, name) {
	return main(page).getByRole('listitem').filter({ has: page.getByRole('link', { name, exact: true }) });
}

function dashRow(page, name) {
	return main(page).getByRole('listitem').filter({ hasText: name });
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

test.describe('Sliding services (Increment 2)', () => {
	test.beforeEach(async ({ page }) => {
		resetDb();
		await login(page);
	});

	test('add form: monthly+sliding hides the due day; switching to yearly hides the checkbox and shows both date fields', async ({ page }) => {
		await page.goto('/service/add');

		const sliding = page.getByLabel('Platím kdykoliv v měsíci');
		const dueDay = page.getByLabel('Den splatnosti');
		const dueMonth = page.getByLabel('Měsíc splatnosti');

		await expect(sliding).toBeVisible();
		await expect(dueDay).toBeVisible();
		await expect(dueMonth).toBeHidden();

		await sliding.check();
		await expect(dueDay).toBeHidden();

		await main(page).getByText('Ročně', { exact: true }).click();
		await expect(sliding).toBeHidden();
		await expect(dueDay).toBeVisible();
		await expect(dueMonth).toBeVisible();

		await main(page).getByText('Měsíčně', { exact: true }).click();
		await expect(sliding).toBeVisible();
		await expect(sliding).toBeChecked();
		await expect(dueDay).toBeHidden();
	});

	test('creating a sliding service without a due day succeeds; the list and detail show a "Kdykoliv" badge instead of a due day', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });
		await addMonthlyService(page, { name: 'Internet', amount: '600', dueDay: 20 });

		await page.goto('/service/');
		const barberRow = serviceRow(page, 'Barber');
		await expect(barberRow.getByText('Kdykoliv', { exact: true })).toBeVisible();
		await expect(barberRow).not.toContainText('Splatnost');

		const internetRow = serviceRow(page, 'Internet');
		await expect(internetRow).toContainText('Splatnost 20.');
		await expect(internetRow.getByText('Kdykoliv', { exact: true })).toHaveCount(0);

		await barberRow.getByRole('link', { name: 'Barber', exact: true }).click();
		await expect(page).toHaveURL(/\/service\/detail\/\d+$/);
		await expect(main(page).getByText('Kdykoliv', { exact: true })).toBeVisible();
		await expect(main(page).getByRole('heading', { name: 'Barber', level: 1 })).toBeVisible();
		await expect(main(page).locator('p').first()).not.toContainText('Splatnost');
	});

	test('editing a sliding service (e.g. changing only the amount) keeps it sliding — guardrail against silently resetting is_sliding to 0', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await serviceRow(page, 'Barber').getByRole('link', { name: 'Upravit' }).click();
		await expect(main(page).getByRole('heading', { name: 'Upravit službu' })).toBeVisible();

		const sliding = page.getByLabel('Platím kdykoliv v měsíci');
		await expect(sliding).toBeChecked();
		await expect(page.getByLabel('Den splatnosti')).toBeHidden();

		await page.getByLabel('Částka (Kč)').fill('500');
		await page.getByRole('button', { name: 'Uložit' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla upravena.');

		const row = serviceRow(page, 'Barber');
		await expect(row).toContainText('500 Kč');
		await expect(row.getByText('Kdykoliv', { exact: true })).toBeVisible();
		await expect(row).not.toContainText('Splatnost');

		await row.getByRole('link', { name: 'Barber', exact: true }).click();
		await expect(main(page).getByText('Kdykoliv', { exact: true })).toBeVisible();

		await page.goto('/');
		const dashboardRow = dashRow(page, 'Barber');
		await expect(dashboardRow).toContainText('Kdykoliv během měsíce');
		await expect(dashboardRow.getByText('Kdykoliv', { exact: true })).toBeVisible();
		await expect(dashboardRow).not.toContainText('Splatnost');
	});

	test('editing a sliding service to uncheck "Platím kdykoliv v měsíci" reveals the due day and requires it server-side; filling it in saves as a regular service', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await serviceRow(page, 'Barber').getByRole('link', { name: 'Upravit' }).click();

		const sliding = page.getByLabel('Platím kdykoliv v měsíci');
		const dueDay = page.getByLabel('Den splatnosti');
		await expect(dueDay).toBeHidden();

		await sliding.uncheck();
		await expect(dueDay).toBeVisible();

		await page.getByRole('button', { name: 'Uložit' }).click();
		await expect(page).toHaveURL(/\/service\/edit\/\d+$/);
		await expect(main(page).getByText('Zadejte den splatnosti.')).toBeVisible();

		await page.getByLabel('Den splatnosti').fill('10');
		await page.getByRole('button', { name: 'Uložit' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla upravena.');

		const row = serviceRow(page, 'Barber');
		await expect(row).toContainText('Splatnost 10.');
		await expect(row.getByText('Kdykoliv', { exact: true })).toHaveCount(0);
	});

	test('dashboard (current month): a sliding service is a "K zaplacení" candidate showing "Kdykoliv během měsíce" + a "Kdykoliv" badge, never "Po splatnosti"', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await page.goto('/');
		const row = dashRow(page, 'Barber');
		await expect(row).toBeVisible();
		await expect(row).toContainText('Kdykoliv během měsíce');
		await expect(row.getByText('Kdykoliv', { exact: true })).toBeVisible();
		await expect(row).not.toContainText('Splatnost');
		await expect(row).not.toContainText(/\d{1,2}\.\s\d{1,2}\.\s\d{4}/);

		await expect(row.getByRole('button', { name: 'Označit Barber jako zaplacené' })).toBeVisible();
		await expect(main(page).getByRole('heading', { name: 'Po splatnosti' })).toHaveCount(0);
	});

	test('dashboard (deterministic past period): a sliding service stays "K zaplacení" (accent, not red) while a regular service with the same due_day=1 is "Po splatnosti"', async ({ page }) => {
		await addMonthlyService(page, { name: 'Rent', amount: '10000', dueDay: 1 });
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await page.goto(`/?year=${PAST}&month=6`);

		await expect(main(page).getByRole('heading', { name: 'Po splatnosti' })).toBeVisible();
		const rentRow = dashRow(page, 'Rent');
		await expect(rentRow).toContainText('Po splatnosti');

		const barberRow = dashRow(page, 'Barber');
		await expect(barberRow).toContainText('Kdykoliv během měsíce');
		await expect(barberRow).not.toContainText('Po splatnosti');

		const rentBg = await rentRow.evaluate((el) => getComputedStyle(el).backgroundColor);
		const barberBg = await barberRow.evaluate((el) => getComputedStyle(el).backgroundColor);
		expect(barberBg).not.toBe(rentBg);
	});

	test('paying, returning, skipping and un-skipping a sliding service works the same as a regular one', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await page.goto(`/?year=${PAST}&month=6`);
		await dashRow(page, 'Barber').getByRole('button', { name: 'Označit Barber jako zaplacené' }).click();

		await expect(page.getByRole('status')).toHaveText('Platba byla označena jako zaplacená.');
		const paidRow = dashRow(page, 'Barber');
		await expect(paidRow.getByRole('button', { name: 'Vrátit Barber mezi nezaplacené' })).toBeVisible();
		await expect(paidRow.getByText('Kdykoliv', { exact: true })).toBeVisible();

		await paidRow.getByRole('button', { name: 'Vrátit Barber mezi nezaplacené' }).click();
		await expect(page.getByRole('status')).toHaveText('Platba byla vrácena mezi nezaplacené.');
		await expect(dashRow(page, 'Barber').getByRole('button', { name: 'Označit Barber jako zaplacené' })).toBeVisible();

		await dashRow(page, 'Barber').getByRole('button', { name: 'Přeskočit Barber pro toto období' }).click();
		await expect(page.getByRole('status')).toHaveText('Platba byla přeskočena.');
		await expect(main(page).getByRole('button', { name: 'Označit Barber jako zaplacené' })).toHaveCount(0);

		const skipped = main(page).locator('details').filter({ hasText: 'Zrušit přeskočení' });
		await skipped.locator('summary').click();
		await expect(skipped.getByText('Kdykoliv', { exact: true })).toBeVisible();
		await skipped.getByRole('button', { name: 'Zrušit přeskočení u Barber' }).click();

		await expect(page.getByRole('status')).toHaveText('Přeskočení bylo zrušeno.');
		await expect(dashRow(page, 'Barber').getByRole('button', { name: 'Označit Barber jako zaplacené' })).toBeVisible();
	});

	test('overview heatmap: a paid month for a sliding service is a full Paid cell; an unpaid month is a plain Gap, never Overdue', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await page.goto(`/?year=${PAST}&month=3`);
		await dashRow(page, 'Barber').getByRole('button', { name: 'Označit Barber jako zaplacené' }).click();
		await expect(page.getByRole('status')).toHaveText('Platba byla označena jako zaplacená.');

		await page.goto(`/overview/?year=${PAST}`);

		const paidCell = main(page).getByRole('gridcell', { name: /Barber · Březen 2020 · Zaplaceno · 450 Kč/ });
		await expect(paidCell).toBeVisible();
		const paidBg = await paidCell.locator('.hm-box').evaluate((el) => getComputedStyle(el).backgroundColor);
		expect(['rgba(0, 0, 0, 0)', 'transparent']).not.toContain(paidBg);

		await expect(main(page).getByRole('gridcell', { name: /Barber · Duben 2020 · Pauza/ })).toBeVisible();
		await expect(main(page).getByRole('gridcell', { name: /Barber · .* · Po splatnosti/ })).toHaveCount(0);
	});
});
