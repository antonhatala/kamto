// Phase 2 — service CRUD: onboarding empty state, add (monthly + yearly incl. the CSS-only
// period toggle), validation, edit, archive/reactivate, and manual sorting.
//
// These tests build on each other in declaration order (single worker, see playwright.config):
// global-setup resets the DB, so the suite always starts from an empty database.
//
// All content queries are scoped to the `main` landmark — in dev mode the Tracy debug bar
// injects its own list/listitem/link elements into <body>, which would otherwise leak into
// page-wide role queries. Flash messages render above <main>, so those stay page-scoped.
const { test, expect } = require('@playwright/test');
const { login, parseColor } = require('./helpers');

/** The app content area (excludes header/flashes and the Tracy debug bar). */
function main(page) {
	return page.getByRole('main');
}

/** The active-list row (listitem) for a service of the given name. */
function serviceRow(page, name) {
	return main(page).getByRole('listitem').filter({ has: page.getByRole('link', { name, exact: true }) });
}

test.describe('Services (Phase 2)', () => {
	test.beforeEach(async ({ page }) => {
		// login() lands on the dashboard (/); these tests operate on the service list.
		await login(page);
		await page.goto('/service/');
	});

	test('empty database shows the onboarding CTA which leads to the add form', async ({ page }) => {
		await expect(main(page).getByRole('heading', { name: 'Zatím tu nemáte žádnou službu' })).toBeVisible();

		await main(page).getByRole('link', { name: 'Přidat první službu' }).click();

		await expect(page).toHaveURL(/\/service\/add(\?.*)?$/);
		await expect(main(page).getByRole('heading', { name: 'Přidat službu' })).toBeVisible();
	});

	test('server-side validation: empty name, zero amount and day 32 show inline errors and keep values', async ({ page }) => {
		await page.goto('/service/add');

		await page.getByLabel('Částka (Kč)').fill('0');
		await page.getByLabel('Den splatnosti').fill('32');

		// The inputs carry native constraint attributes (required, min/max) which would block
		// submission in the browser — disable them so Nette's server-side validation is what
		// we exercise here.
		await page.locator('#frm-serviceForm').evaluate((form) => {
			form.noValidate = true;
		});
		await page.getByRole('button', { name: 'Uložit' }).click();

		await expect(page).toHaveURL(/\/service\/add(\?.*)?$/);
		await expect(main(page).getByText('Zadejte název.')).toBeVisible();
		await expect(main(page).getByText('Zadejte platnou částku')).toBeVisible();
		await expect(main(page).getByText('Den splatnosti musí být 1–31.')).toBeVisible();

		// Submitted values survive the round-trip — the user doesn't have to retype the form.
		await expect(page.getByLabel('Částka (Kč)')).toHaveValue('0');
		await expect(page.getByLabel('Den splatnosti')).toHaveValue('32');

		// Nothing was created.
		await page.goto('/service/');
		await expect(main(page).getByRole('heading', { name: 'Zatím tu nemáte žádnou službu' })).toBeVisible();
	});

	test('adding a monthly service shows it in the list with amount, due day and badge', async ({ page }) => {
		await main(page).getByRole('link', { name: 'Přidat první službu' }).click();

		await page.getByLabel('Název').fill('Netflix');
		await page.getByLabel('Částka (Kč)').fill('199,50');
		await page.getByLabel('Den splatnosti').fill('15');
		// Period defaults to "Měsíčně" — left as is.
		await page.getByRole('button', { name: 'Uložit' }).click();

		await expect(page).toHaveURL(/\/service\/(\?.*)?$/);
		await expect(page.getByRole('status')).toHaveText('Služba byla přidána.');

		const row = serviceRow(page, 'Netflix');
		await expect(row).toContainText('199,50 Kč');
		await expect(row).toContainText('Splatnost 15.');
		await expect(row.getByText('Měsíčně', { exact: true })).toBeVisible();
		await expect(row).toContainText('Bez kategorie');
	});

	test('switching period to yearly reveals the month field (pure CSS) and the service lists a full due date', async ({ page }) => {
		await main(page).getByRole('link', { name: 'Přidat službu' }).click();

		// The "Měsíc splatnosti" field is hidden while the default monthly period is selected —
		// selecting "Ročně" must actually reveal it (CSS :has() progressive disclosure, no JS).
		const monthField = page.getByLabel('Měsíc splatnosti');
		await expect(monthField).toBeHidden();

		await main(page).getByText('Ročně', { exact: true }).click();
		await expect(monthField).toBeVisible();

		await page.getByLabel('Název').fill('Doména');
		await page.getByLabel('Částka (Kč)').fill('350');
		await page.getByLabel('Den splatnosti').fill('15');
		await monthField.selectOption({ label: 'Červen' });
		await page.getByRole('button', { name: 'Uložit' }).click();

		await expect(page.getByRole('status')).toHaveText('Služba byla přidána.');

		const row = serviceRow(page, 'Doména');
		await expect(row).toContainText('350 Kč');
		await expect(row).toContainText('Splatnost 15. 6.');
		await expect(row.getByText('Ročně', { exact: true })).toBeVisible();
	});

	test('editing a monthly service updates the amount in the list', async ({ page }) => {
		// KNOWN APP BUG (reported): opening the edit form of a MONTHLY service throws
		// Nette\InvalidArgumentException — ServicePresenter::createComponentServiceForm()
		// coalesces the NULL due_month to '' but the select (setPrompt) only accepts null.
		// This test encodes the correct expected behavior and stays red until that is fixed.
		await serviceRow(page, 'Netflix').getByRole('link', { name: 'Upravit' }).click();

		await expect(main(page).getByRole('heading', { name: 'Upravit službu' })).toBeVisible();
		// The form is prefilled with the stored value.
		await expect(page.getByLabel('Částka (Kč)')).toHaveValue('199,50');

		await page.getByLabel('Částka (Kč)').fill('249');
		await page.getByRole('button', { name: 'Uložit' }).click();

		await expect(page.getByRole('status')).toHaveText('Služba byla upravena.');
		await expect(serviceRow(page, 'Netflix')).toContainText('249 Kč');
	});

	test('reordering: moving the first service down swaps the order and survives a reload', async ({ page }) => {
		const items = main(page).getByRole('listitem');
		await expect(items).toHaveCount(2);
		await expect(items.first()).toContainText('Netflix');

		await main(page).getByRole('button', { name: 'Posunout Netflix dolů' }).click();

		await expect(items.first()).toContainText('Doména');
		await expect(items.last()).toContainText('Netflix');

		await page.reload();
		await expect(items.first()).toContainText('Doména');
		await expect(items.last()).toContainText('Netflix');
	});

	test('archiving hides a service from the active list; the archive shows it muted and can restore it', async ({ page }) => {
		await serviceRow(page, 'Doména').getByRole('button', { name: 'Archivovat' }).click();

		await expect(page.getByRole('status')).toHaveText('Služba byla archivována.');
		// Gone from the active list (active names are edit links, archived names are plain text).
		await expect(main(page).getByRole('link', { name: 'Doména', exact: true })).toHaveCount(0);

		// The archive is a collapsed <details> with a count — open it.
		const archive = main(page).locator('details');
		await main(page).getByText('Archiv (1)').click();
		const archivedName = archive.getByText('Doména');
		await expect(archivedName).toBeVisible();

		// Muted rendering: the archived name is noticeably lighter than regular stone-900 text.
		const color = parseColor(await archivedName.evaluate((el) => getComputedStyle(el).color));
		expect(color.lightness).toBeGreaterThan(0.4);

		await archive.getByRole('button', { name: 'Obnovit' }).click();

		await expect(page.getByRole('status')).toHaveText('Služba byla obnovena.');
		await expect(main(page).getByRole('link', { name: 'Doména', exact: true })).toBeVisible();
		// No archived services left — the archive section disappears entirely.
		await expect(main(page).getByText('Archiv (', { exact: false })).toHaveCount(0);
	});

	test('service list never renders in dark mode, even when the OS prefers it', async ({ page }) => {
		await page.emulateMedia({ colorScheme: 'dark' });
		await page.goto('/service/');

		const bodyBg = parseColor(await page.evaluate(() => getComputedStyle(document.body).backgroundColor));
		expect(bodyBg.lightness).toBeGreaterThan(0.85);
	});
});
