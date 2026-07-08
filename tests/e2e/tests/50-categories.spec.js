// Phase 2 — categories: create with a color swatch, assign to a service (colored dot in the
// service list), and delete with a confirmation that reports the affected service count.
//
// Runs after 40-services.spec.js (single worker, declaration order) — the services created
// there are reused. The category is assigned via the YEARLY service ("Doména"): editing a
// monthly service currently 500s (known reported bug, see 40-services), and these tests
// cover the category flows independently of that defect.
//
// Queries are scoped to the `main` landmark (the dev-mode Tracy bar injects its own
// list/listitem elements); flashes render above <main> and stay page-scoped.
const { test, expect } = require('@playwright/test');
const { login } = require('./helpers');

// "Modrá" from the server-side palette whitelist (CategoryPresenter::Palette) — #2168a3.
const BLUE_RGB = 'rgb(33, 104, 163)';

/** The app content area (excludes header/flashes and the Tracy debug bar). */
function main(page) {
	return page.getByRole('main');
}

/** The active-list row (listitem) for a service of the given name. */
function serviceRow(page, name) {
	return main(page).getByRole('listitem').filter({ has: page.getByRole('link', { name, exact: true }) });
}

/** The single colored dot (inline-styled span) inside a list row. */
function colorDot(row) {
	return row.locator('span[style*="background-color"]');
}

test.describe('Categories (Phase 2)', () => {
	test.beforeEach(async ({ page }) => {
		// login() lands on the dashboard (/); these tests operate on the service/category lists.
		await login(page);
		await page.goto('/service/');
	});

	test('creating a category with a chosen swatch shows it in the list with its color', async ({ page }) => {
		await page.goto('/category/');
		await expect(main(page).getByRole('heading', { name: 'Zatím tu nemáte žádnou kategorii' })).toBeVisible();

		await main(page).getByRole('link', { name: 'Přidat první kategorii' }).click();
		await expect(main(page).getByRole('heading', { name: 'Přidat kategorii' })).toBeVisible();

		await page.getByLabel('Název').fill('Zábava');
		// Swatches are radio inputs visually replaced by color squares; pick by their
		// accessible name (visible square carries the title, sr-only text names the radio).
		await page.getByTitle('Modrá').click();
		await expect(page.getByRole('radio', { name: 'Modrá' })).toBeChecked();

		await page.getByRole('button', { name: 'Uložit' }).click();

		await expect(page.getByRole('status')).toHaveText('Kategorie byla přidána.');
		const row = main(page).getByRole('listitem').filter({ hasText: 'Zábava' });
		await expect(row).toContainText('Žádná služba');
		await expect(colorDot(row)).toHaveCSS('background-color', BLUE_RGB);
	});

	test('assigning a category to a service shows its name and colored dot in the service list', async ({ page }) => {
		await serviceRow(page, 'Doména').getByRole('link', { name: 'Upravit' }).click();

		await page.getByLabel('Kategorie').selectOption({ label: 'Zábava' });
		await page.getByRole('button', { name: 'Uložit' }).click();

		await expect(page.getByRole('status')).toHaveText('Služba byla upravena.');
		const row = serviceRow(page, 'Doména');
		await expect(row).toContainText('Zábava');
		await expect(row).not.toContainText('Bez kategorie');
		await expect(colorDot(row)).toHaveCSS('background-color', BLUE_RGB);

		// The category list now reflects the usage too.
		await page.getByRole('banner').getByRole('link', { name: 'Kategorie' }).click();
		await expect(main(page).getByRole('listitem').filter({ hasText: 'Zábava' })).toContainText('1 služba');
	});

	test('deleting a category asks for confirmation with the affected count and unassigns services', async ({ page }) => {
		await page.goto('/category/');
		await main(page).getByRole('listitem').filter({ hasText: 'Zábava' }).getByRole('link', { name: 'Smazat' }).click();

		// Confirmation page reports how many services will lose the category.
		await expect(main(page).getByRole('heading', { name: /Smazat kategorii .Zábava/ })).toBeVisible();
		await expect(main(page).getByText('U 1 služby se kategorie odebere')).toBeVisible();

		await page.getByRole('button', { name: 'Smazat kategorii' }).click();

		await expect(page.getByRole('status')).toHaveText('Kategorie byla smazána.');
		await expect(main(page).getByRole('heading', { name: 'Zatím tu nemáte žádnou kategorii' })).toBeVisible();

		// The service that used it falls back to "Bez kategorie".
		await page.goto('/service/');
		const row = serviceRow(page, 'Doména');
		await expect(row).toContainText('Bez kategorie');
		await expect(colorDot(row)).toHaveCount(0);
	});
});
