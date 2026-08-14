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
	const blocking = [];
	for ( const path of [
		plugin.adminPath,
		`${ plugin.adminPath }&section=setup`,
		`${ plugin.adminPath }&section=activity`,
		`${ plugin.adminPath }&section=ledger`,
		`${ plugin.adminPath }&section=forecast`,
		`${ plugin.adminPath }&section=reports`,
		`${ plugin.adminPath }&section=receiving`,
		`${ plugin.adminPath }&section=reorder`,
		`${ plugin.adminPath }&section=costs`,
		`${ plugin.adminPath }&section=reservations`,
	] ) {
		await page.goto( path, { waitUntil: 'networkidle' } );
		await expect( page.locator( plugin.ready ) ).toBeVisible();
		const results = await new AxeBuilder( { page } )
			.include( plugin.scope )
			.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ] )
			.analyze();
		blocking.push(
			...results.violations.filter( ( violation ) =>
				[ 'serious', 'critical' ].includes( violation.impact )
			)
		);
	}

	expect( failures ).toEqual( [] );
	expect( blocking ).toEqual( [] );
} );

test( 'setup pool picker performs an authorized paginated search', async ( {
	page,
} ) => {
	await page.goto( `${ plugin.adminPath }&section=setup`, {
		waitUntil: 'networkidle',
	} );
	let picker = page.locator( '.laqi-lusm-pool-search' ).first();
	if ( ( await picker.count() ) === 0 ) {
		await page.locator( '#laqi-lusm-pool_name' ).fill( 'Browser test pool' );
		await page.locator( '#laqi-lusm-opening_balance' ).fill( '1' );
		await Promise.all( [
			page.waitForURL( /section=setup/ ),
			page.getByRole( 'button', { name: 'Create pool' } ).click(),
		] );
		picker = page.locator( '.laqi-lusm-pool-search' ).first();
	}
	await expect( picker ).toBeAttached();
	await picker
		.locator( 'xpath=following-sibling::span[contains(@class,"select2")]' )
		.click();
	const responsePromise = page.waitForResponse( ( response ) =>
		response.url().includes( 'action=laqi_lusm_search_pools' )
	);
	await page.locator( '.select2-search__field' ).last().fill( 'a' );
	const response = await responsePromise;
	const payload = await response.json();

	expect( response.status() ).toBe( 200 );
	expect( payload.success ).toBe( true );
	expect( Array.isArray( payload.data.results ) ).toBe( true );
	expect( typeof payload.data.pagination.more ).toBe( 'boolean' );
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
