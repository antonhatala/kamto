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
		// Inside the compose network the app is reachable at the nginx service's internal
		// port 80 (see docker/nginx.conf `listen 80`), not the host-mapped 8080.
		baseURL: process.env.BASE_URL ?? 'http://nginx:80',
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
});
