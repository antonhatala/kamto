// @ts-check
// Playwright config for Kamto's E2E harness. Runs exclusively inside the official
// `mcr.microsoft.com/playwright` Docker image (see the `e2e` service in docker-compose.yml,
// profile `tools`) against the docker-compose stack — never on the host.
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './tests',
	globalSetup: require.resolve('./global-setup'),
	// The whole suite shares one server-side SQLite DB and one global login throttle, and the
	// Phase 2 CRUD specs build on each other's data (numbered spec files define the order) —
	// so tests run in a single worker, strictly in declaration order. No parallelism.
	fullyParallel: false,
	workers: 1,
	forbidOnly: !!process.env.CI,
	retries: 0,
	reporter: [['list'], ['html', { open: 'never', outputFolder: 'playwright-report' }]],
	outputDir: 'test-results',
	use: {
		// The e2e container shares nginx's network namespace (see docker-compose.yml `e2e`
		// service) so this resolves to nginx's own port 80 as "localhost" — required for the
		// Fáze 5 service-worker specs (see BASE_URL comment there for why).
		baseURL: process.env.BASE_URL ?? 'http://localhost:80',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		// Suite-wide default: no service worker registration. Only 80-pwa-export.spec.js exercises
		// the SW (offline fallback, cache-security check) and explicitly opts back in via
		// `test.use({ serviceWorkers: 'allow' })` — every other spec gets a plain network fetch,
		// so a caching service worker can never leak state between tests or make an unrelated
		// spec flaky/order-dependent.
		serviceWorkers: 'block',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
});
