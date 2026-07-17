// Phase 3 — "Co zaplatit" dashboard (Home:default at /): empty state, service appearing as a
// payment candidate, the paid / skipped / edit-amount flows, and month navigation incl. the
// yearly-service due-month rule.
//
// Determinism: the dashboard aggregates ALL active services and its default period is the real
// current month (SystemClock). So every test is fully self-contained —
//   1. beforeEach resets the DB (via reset-db.js) and logs in, giving each test a clean slate
//      regardless of order, worker restarts, or leftover data from other spec files, and
//   2. the paid/skipped/amount/yearly flows run on an explicit FAR-FUTURE period
//      (?year=2099&month=11) where a monthly service is always "planned" and the totals are
//      predictable — no dependence on today's date.
// Content is scoped to the `main` landmark (the dev-mode Tracy bar injects its own
// list/listitem elements); flash messages render above <main> and stay page-scoped.
const { test, expect } = require('@playwright/test');
const { login, parseColor } = require('./helpers');
const { resetDb } = require('../reset-db');

// A period comfortably in the future: a monthly service is always Planned (never overdue) here,
// and month 11 avoids colliding with the yearly service's due month (3).
const FUTURE = { year: 2099, month: 11 };
const FUTURE_PATH = `/?year=${FUTURE.year}&month=${FUTURE.month}`;

/** The app content area (excludes header/flashes and the Tracy debug bar). */
function main(page) {
	return page.getByRole('main');
}

/** A dashboard row (listitem) for the service of the given name. */
function dashRow(page, name) {
	return main(page).getByRole('listitem').filter({ hasText: name });
}

/** The summary card's "Zbývá zaplatit" value (the <p> right after that label). */
function remainingTotal(page) {
	const summary = page.locator('section').filter({ hasText: 'Zbývá zaplatit' });
	return summary.getByText('Zbývá zaplatit').locator('xpath=following-sibling::p[1]');
}

/** The summary card's "Zaplaceno" value (scoped to the summary card, not the section heading). */
function paidTotal(page) {
	const summary = page.locator('section').filter({ hasText: 'Zbývá zaplatit' });
	return summary.getByText('Zaplaceno', { exact: true }).locator('xpath=following-sibling::p[1]');
}

/** Adds a monthly service through the UI (redirects to the service list on success). */
async function addMonthlyService(page, { name, amount, dueDay }) {
	await page.goto('/service/add');
	await page.getByLabel('Název').fill(name);
	await page.getByLabel('Částka (Kč)').fill(amount);
	await page.getByLabel('Den splatnosti').fill(String(dueDay));
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

test.describe('Dashboard "Co zaplatit" (Phase 3)', () => {
	// Each test starts from a clean database — the dashboard sums all services, so leftover
	// data (from other specs or earlier tests) would make totals non-deterministic. Resetting
	// per-test also makes the suite immune to Playwright restarting the worker mid-run.
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
		// The due date for the current month is shown as "day. month. year" (NBSP-separated,
		// hence \s in the pattern). due_day 15 → "15. …".
		await expect(row).toContainText(/15\.\s\d{1,2}\.\s\d{4}/);
		// It is an unpaid candidate (planned or overdue, depending on today) — it carries the
		// "Zaplaceno ✓" action and counts toward "Zbývá zaplatit".
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
		// Now paid: the row offers "Vrátit" and the totals swap.
		await expect(dashRow(page, 'Spotify').getByRole('button', { name: 'Vrátit Spotify mezi nezaplacené' })).toBeVisible();
		await expect(remainingTotal(page)).toHaveText('0 Kč');
		await expect(paidTotal(page)).toHaveText('199,50 Kč');

		// "Vrátit" puts it back among the unpaid.
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
		// Skipped is not counted toward the remaining total.
		await expect(remainingTotal(page)).toHaveText('0 Kč');
		// It is no longer an unpaid candidate — no "Zaplaceno ✓" action anywhere.
		await expect(main(page).getByRole('button', { name: 'Označit Spotify jako zaplacené' })).toHaveCount(0);

		// After a reload it stays skipped (doesn't re-appear as a fresh candidate).
		await page.reload();
		await expect(main(page).getByRole('button', { name: 'Označit Spotify jako zaplacené' })).toHaveCount(0);
		await expect(remainingTotal(page)).toHaveText('0 Kč');

		// The skipped service lives in a collapsed <details> — expand it and undo the skip.
		const skipped = main(page).locator('details').filter({ hasText: 'Zrušit přeskočení' });
		await skipped.locator('summary').click();
		await skipped.getByRole('button', { name: 'Zrušit přeskočení u Spotify' }).click();

		await expect(page.getByRole('status')).toHaveText('Přeskočení bylo zrušeno.');
		await expect(dashRow(page, 'Spotify').getByRole('button', { name: 'Označit Spotify jako zaplacené' })).toBeVisible();
		await expect(remainingTotal(page)).toHaveText('199,50 Kč');
	});

	test('editing the amount via the dialog updates the row and total; an invalid amount is rejected', async ({ page }) => {
		await addMonthlyService(page, { name: 'Spotify', amount: '199,50', dueDay: 15 });

		await page.goto(FUTURE_PATH);
		await dashRow(page, 'Spotify').getByRole('button', { name: 'Upravit částku u Spotify' }).click();

		// Native <dialog> opened modally.
		const dialog = page.getByRole('dialog');
		await expect(dialog).toBeVisible();

		// Regression: Tailwind Preflight zeroes margins, which would pin the dialog to the
		// top-left corner — src/css/app.css restores `dialog { margin: auto }` (UA centering).
		const [box, viewport] = [await dialog.boundingBox(), page.viewportSize()];
		const center = { x: box.x + box.width / 2, y: box.y + box.height / 2 };
		expect(Math.abs(center.x - viewport.width / 2)).toBeLessThan(2);
		expect(Math.abs(center.y - viewport.height / 2)).toBeLessThan(2);

		await dialog.getByLabel('Částka (Kč)').fill('349,50');
		await dialog.getByRole('button', { name: 'Uložit' }).click();

		await expect(page.getByRole('status')).toHaveText('Částka byla upravena.');
		await expect(dashRow(page, 'Spotify')).toContainText('349,50 Kč');
		await expect(remainingTotal(page)).toHaveText('349,50 Kč');

		// Invalid amount: the dialog re-opens (data-reopen) with an inline error and nothing is saved.
		await dashRow(page, 'Spotify').getByRole('button', { name: 'Upravit částku u Spotify' }).click();
		const dialog2 = page.getByRole('dialog');
		await expect(dialog2).toBeVisible();
		await dialog2.getByLabel('Částka (Kč)').fill('abc');
		await dialog2.getByRole('button', { name: 'Uložit' }).click();

		// Re-opened on the error, showing the validation message; the stored amount is unchanged.
		await expect(page.getByRole('dialog')).toBeVisible();
		await expect(page.getByRole('dialog').getByText('Zadejte platnou částku.')).toBeVisible();
		await expect(dashRow(page, 'Spotify')).toContainText('349,50 Kč');
	});

	test('a yearly service appears only in its due month', async ({ page }) => {
		await addYearlyService(page, { name: 'PojistkaAuto', amount: '3500', dueDay: 15, monthLabel: 'Březen' });

		// Not a candidate in an unrelated month…
		await page.goto(`/?year=${FUTURE.year}&month=7`);
		await expect(dashRow(page, 'PojistkaAuto')).toHaveCount(0);

		// …but present in March, with its due date and amount.
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

		// "Dnes" jumps back to the current month (default period, no query string).
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
