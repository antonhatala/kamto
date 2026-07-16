// Increment 2 — sliding services ("klouzavé služby", service.is_sliding): a monthly service
// without a fixed due day. Covers:
//   - the add-form progressive disclosure (CSS :has() reveal) incl. the monthly↔yearly edge case
//   - the service list / detail page showing a "Klouzavá" badge instead of a due day
//   - the "Co zaplatit" dashboard: "Kdykoliv během měsíce" wording, accent (not red) treatment,
//     NEVER placed in "Po splatnosti" (even though its placeholder due_day=1 would make a
//     regular service overdue), and the same pay/skip/unskip actions as a regular service
//   - the yearly overview heatmap: a paid month is a full Paid cell, an unpaid one a plain Gap
//     ("Pauza"), never Overdue — no red leaking in via the placeholder due_day.
//
// Each test resets the DB for a clean, order-independent slate (see reset-db.js). The
// "never overdue" dashboard/heatmap checks use a fixed PAST period (2020, same technique as
// 70-overview.spec.js) for determinism; one check also runs against the real current month
// ("aktuální měsíc" per the acceptance criteria), which sliding satisfies unconditionally.
//
// Content queries are scoped to the `main` landmark (the dev-mode Tracy bar injects its own
// list/listitem elements); flash messages render above <main> and stay page-scoped.
const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');
const { resetDb } = require('../reset-db');

const PAST = 2020; // every month is in the past relative to the real clock

/** The app content area (excludes header/flashes and the Tracy debug bar). */
function main(page) {
	return page.getByRole('main');
}

/** The active-list row (listitem) for a service of the given name, on /service/. */
function serviceRow(page, name) {
	return main(page).getByRole('listitem').filter({ has: page.getByRole('link', { name, exact: true }) });
}

/** A dashboard row (listitem) for the service of the given name, on /. */
function dashRow(page, name) {
	return main(page).getByRole('listitem').filter({ hasText: name });
}

/** Adds a monthly service through the UI — either sliding (no due day) or with an explicit due day. */
async function addMonthlyService(page, { name, amount, sliding = false, dueDay }) {
	await page.goto('/service/add');
	await page.getByLabel('Název').fill(name);
	await page.getByLabel('Částka (Kč)').fill(amount);
	if (sliding) {
		await page.getByLabel('Klouzavá (bez pevného dne)').check();
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

		const sliding = page.getByLabel('Klouzavá (bez pevného dne)');
		const dueDay = page.getByLabel('Den splatnosti');
		const dueMonth = page.getByLabel('Měsíc splatnosti');

		// Default period is monthly: the checkbox is offered, due day visible, due month hidden.
		await expect(sliding).toBeVisible();
		await expect(dueDay).toBeVisible();
		await expect(dueMonth).toBeHidden();

		// Checking "Klouzavá" hides the due day field (pure CSS :has(), no JS).
		await sliding.check();
		await expect(dueDay).toBeHidden();

		// Switching to yearly: "Klouzavá" makes no sense there (server ignores it anyway) — the
		// checkbox itself hides, and BOTH date fields become visible regardless of the
		// (now-hidden, still-checked) sliding checkbox.
		await main(page).getByText('Ročně', { exact: true }).click();
		await expect(sliding).toBeHidden();
		await expect(dueDay).toBeVisible();
		await expect(dueMonth).toBeVisible();

		// Back to monthly: the checkbox re-appears still checked, and the due day hides again.
		await main(page).getByText('Měsíčně', { exact: true }).click();
		await expect(sliding).toBeVisible();
		await expect(sliding).toBeChecked();
		await expect(dueDay).toBeHidden();
	});

	test('creating a sliding service without a due day succeeds; the list and detail show a "Klouzavá" badge instead of a due day', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });
		await addMonthlyService(page, { name: 'Internet', amount: '600', dueDay: 20 });

		await page.goto('/service/');
		const barberRow = serviceRow(page, 'Barber');
		await expect(barberRow.getByText('Klouzavá', { exact: true })).toBeVisible();
		await expect(barberRow).not.toContainText('Splatnost');

		const internetRow = serviceRow(page, 'Internet');
		await expect(internetRow).toContainText('Splatnost 20.');
		await expect(internetRow.getByText('Klouzavá', { exact: true })).toHaveCount(0);

		// Detail page mirrors the same "badge instead of due day" rule.
		await barberRow.getByRole('link', { name: 'Barber', exact: true }).click();
		await expect(page).toHaveURL(/\/service\/detail\/\d+$/);
		await expect(main(page).getByText('Klouzavá', { exact: true })).toBeVisible();
		await expect(main(page).getByRole('heading', { name: 'Barber', level: 1 })).toBeVisible();
		await expect(main(page).locator('p').first()).not.toContainText('Splatnost');
	});

	test('editing a sliding service (e.g. changing only the amount) keeps it sliding — guardrail against silently resetting is_sliding to 0', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await serviceRow(page, 'Barber').getByRole('link', { name: 'Upravit' }).click();
		await expect(main(page).getByRole('heading', { name: 'Upravit službu' })).toBeVisible();

		// Pre-filled from the stored service: the checkbox is already checked and, per the same
		// CSS rule as the add form, the due day stays hidden.
		const sliding = page.getByLabel('Klouzavá (bez pevného dne)');
		await expect(sliding).toBeChecked();
		await expect(page.getByLabel('Den splatnosti')).toBeHidden();

		// Touch only the amount — is_sliding must survive the round-trip untouched.
		await page.getByLabel('Částka (Kč)').fill('500');
		await page.getByRole('button', { name: 'Uložit' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla upravena.');

		// List: still a "Klouzavá" badge (not a due day), amount updated.
		const row = serviceRow(page, 'Barber');
		await expect(row).toContainText('500 Kč');
		await expect(row.getByText('Klouzavá', { exact: true })).toBeVisible();
		await expect(row).not.toContainText('Splatnost');

		// Detail: same rule.
		await row.getByRole('link', { name: 'Barber', exact: true }).click();
		await expect(main(page).getByText('Klouzavá', { exact: true })).toBeVisible();

		// Dashboard: still "Kdykoliv během měsíce" + badge, never a due date — the guardrail's
		// whole point (a reset is_sliding would turn this into an ordinary dated service here).
		await page.goto('/');
		const dashboardRow = dashRow(page, 'Barber');
		await expect(dashboardRow).toContainText('Kdykoliv během měsíce');
		await expect(dashboardRow.getByText('Klouzavá', { exact: true })).toBeVisible();
		await expect(dashboardRow).not.toContainText('Splatnost');
	});

	test('editing a sliding service to uncheck "Klouzavá" reveals the due day and requires it server-side; filling it in saves as a regular service', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await serviceRow(page, 'Barber').getByRole('link', { name: 'Upravit' }).click();

		const sliding = page.getByLabel('Klouzavá (bez pevného dne)');
		const dueDay = page.getByLabel('Den splatnosti');
		await expect(dueDay).toBeHidden();

		await sliding.uncheck();
		await expect(dueDay).toBeVisible();

		// due_day carries no HTML `required` (its requirement is conditional on is_sliding) — an
		// empty submit must still be rejected server-side, not silently accepted.
		await page.getByRole('button', { name: 'Uložit' }).click();
		await expect(page).toHaveURL(/\/service\/edit\/\d+$/);
		await expect(main(page).getByText('Zadejte den splatnosti.')).toBeVisible();

		// Filling in a due day and resubmitting saves it as a regular (non-sliding) service.
		await page.getByLabel('Den splatnosti').fill('10');
		await page.getByRole('button', { name: 'Uložit' }).click();
		await expect(page.getByRole('status')).toHaveText('Služba byla upravena.');

		const row = serviceRow(page, 'Barber');
		await expect(row).toContainText('Splatnost 10.');
		await expect(row.getByText('Klouzavá', { exact: true })).toHaveCount(0);
	});

	test('dashboard (current month): a sliding service is a "K zaplacení" candidate showing "Kdykoliv během měsíce" + a "Klouzavá" badge, never "Po splatnosti"', async ({ page }) => {
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await page.goto('/');
		const row = dashRow(page, 'Barber');
		await expect(row).toBeVisible();
		await expect(row).toContainText('Kdykoliv během měsíce');
		await expect(row.getByText('Klouzavá', { exact: true })).toBeVisible();
		// No concrete due date and no "Splatnost" wording for a sliding row.
		await expect(row).not.toContainText('Splatnost');
		await expect(row).not.toContainText(/\d{1,2}\.\s\d{1,2}\.\s\d{4}/);

		// It carries the unpaid "Zaplaceno ✓" action (i.e. lives under "K zaplacení") and there is
		// no "Po splatnosti" section at all (it only renders when non-empty).
		await expect(row.getByRole('button', { name: 'Označit Barber jako zaplacené' })).toBeVisible();
		await expect(main(page).getByRole('heading', { name: 'Po splatnosti' })).toHaveCount(0);
	});

	test('dashboard (deterministic past period): a sliding service stays "K zaplacení" (accent, not red) while a regular service with the same due_day=1 is "Po splatnosti"', async ({ page }) => {
		// due_day 1 is already in the past for any month of PAST (2020) — a regular service is
		// overdue there. A sliding service persists the exact same placeholder due_day=1 in the DB
		// (see ServicePresenter::serviceFormSucceeded) yet must never become Overdue.
		await addMonthlyService(page, { name: 'Rent', amount: '10000', dueDay: 1 });
		await addMonthlyService(page, { name: 'Barber', amount: '450', sliding: true });

		await page.goto(`/?year=${PAST}&month=6`);

		// Regular service: overdue, in the red "Po splatnosti" section.
		await expect(main(page).getByRole('heading', { name: 'Po splatnosti' })).toBeVisible();
		const rentRow = dashRow(page, 'Rent');
		await expect(rentRow).toContainText('Po splatnosti');

		// Sliding service: still a plain "K zaplacení" candidate — never overdue.
		const barberRow = dashRow(page, 'Barber');
		await expect(barberRow).toContainText('Kdykoliv během měsíce');
		await expect(barberRow).not.toContainText('Po splatnosti');

		// Visual distinction: the sliding row does not share the overdue row's red-tinted background.
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
		await expect(paidRow.getByText('Klouzavá', { exact: true })).toBeVisible();

		// "Vrátit" puts it back among the unpaid, still sliding.
		await paidRow.getByRole('button', { name: 'Vrátit Barber mezi nezaplacené' }).click();
		await expect(page.getByRole('status')).toHaveText('Platba byla vrácena mezi nezaplacené.');
		await expect(dashRow(page, 'Barber').getByRole('button', { name: 'Označit Barber jako zaplacené' })).toBeVisible();

		// Skip → "Přeskočeno"; "Zrušit přeskočení" reverses it.
		await dashRow(page, 'Barber').getByRole('button', { name: 'Přeskočit Barber pro toto období' }).click();
		await expect(page.getByRole('status')).toHaveText('Platba byla přeskočena.');
		await expect(main(page).getByRole('button', { name: 'Označit Barber jako zaplacené' })).toHaveCount(0);

		const skipped = main(page).locator('details').filter({ hasText: 'Zrušit přeskočení' });
		await skipped.locator('summary').click();
		await expect(skipped.getByText('Klouzavá', { exact: true })).toBeVisible();
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

		// A month with no payment row is a plain "Pauza" (Gap) — not overdue, even though the
		// service's placeholder due_day=1 is long past for every other 2020 month.
		await expect(main(page).getByRole('gridcell', { name: /Barber · Duben 2020 · Pauza/ })).toBeVisible();
		await expect(main(page).getByRole('gridcell', { name: /Barber · .* · Po splatnosti/ })).toHaveCount(0);
	});
});
