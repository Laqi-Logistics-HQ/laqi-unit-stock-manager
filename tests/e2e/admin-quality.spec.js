const AxeBuilder = require( '@axe-core/playwright' ).default;
const { test, expect } = require( '@playwright/test' );

const plugin = {
	slug: 'laqi-unit-stock-manager',
	adminPath: '/wp-admin/admin.php?page=laqi-unit-stock-manager',
	scope: '.laqi-lusm-wrap',
	ready: '.laqi-lusm-wrap h1',
};

async function logIn( page ) {
	await page.goto( '/wp-login.php' );
	await page
		.getByLabel( 'Username or Email Address' )
		.fill( process.env.WP_ADMIN_USER || 'admin' );
	await page
		.locator( '#user_pass' )
		.fill( process.env.WP_ADMIN_PASSWORD || 'password' );
	await Promise.all( [
		page.waitForURL( /\/wp-admin\// ),
		page.getByRole( 'button', { name: 'Log In' } ).click(),
	] );
}

test.beforeEach( async ( { page } ) => {
	await logIn( page );
} );

test( 'admin screen renders without serious accessibility or runtime failures', async ( {
	page,
} ) => {
	const failures = [];
	page.on( 'pageerror', ( error ) => failures.push( error.message ) );
	page.on( 'response', ( response ) => {
		if (
			response.url().includes( `/plugins/${ plugin.slug }/` ) &&
			response.status() >= 400
		) {
			failures.push( `${ response.status() } ${ response.url() }` );
		}
	} );

	await page.emulateMedia( { reducedMotion: 'reduce' } );
	await page.goto( plugin.adminPath, { waitUntil: 'networkidle' } );
	await expect( page.locator( plugin.ready ) ).toBeVisible();

	const results = await new AxeBuilder( { page } )
		.include( plugin.scope )
		.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ] )
		.analyze();
	const blocking = results.violations.filter( ( violation ) =>
		[ 'serious', 'critical' ].includes( violation.impact )
	);

	expect( failures ).toEqual( [] );
	expect( blocking ).toEqual( [] );
} );

test( 'deactivation leaves no plugin assets loaded', async ( { page } ) => {
	await page.goto( '/wp-admin/plugins.php' );
	// Match on data-plugin, not data-slug. WordPress fills data-slug with
	// sanitize_title() of the plugin's DISPLAY NAME for anything it cannot find
	// in the directory API, so `tr[data-slug="<slug>"]` only matches while the
	// display name happens to sanitize to the folder name. That coincidence held
	// for Order Status Automation until it was renamed "… for WooCommerce", and
	// the test failed having never really asserted what it appeared to.
	// data-plugin is the plugin file path, which actually identifies the plugin.
	const row = page.locator(
		`tr[data-plugin="${ plugin.slug }/${ plugin.slug }.php"]`
	);
	await expect( row ).toBeVisible();
	await row.getByRole( 'link', { name: 'Deactivate' } ).click();
	await expect( row.getByRole( 'link', { name: 'Activate' } ) ).toBeVisible();

	await page.goto( '/wp-admin/', { waitUntil: 'networkidle' } );
	const resources = await page.evaluate( () =>
		performance.getEntriesByType( 'resource' ).map( ( entry ) => entry.name )
	);
	expect(
		resources.filter( ( url ) => url.includes( `/plugins/${ plugin.slug }/` ) )
	).toEqual( [] );
} );
