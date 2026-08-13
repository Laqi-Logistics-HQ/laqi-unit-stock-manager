const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 60_000,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: 'list',
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		browserName: 'chromium',
		trace: 'retain-on-failure',
	},
} );
