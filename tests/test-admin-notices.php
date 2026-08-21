<?php
/**
 * Admin notice scoping tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Plugin;

/**
 * Ensures the plugin's two admin notices stay on the screen that can act on
 * them instead of following administrators around the dashboard.
 */
class Test_Admin_Notices extends WP_UnitTestCase {

	/** Act as an administrator by default. */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/** Leave no screen behind for the next test. */
	public function tear_down(): void {
		unset( $GLOBALS['current_screen'] );
		parent::tear_down();
	}

	/**
	 * Render a callable's admin output.
	 *
	 * @param callable $callback Notice renderer.
	 * @return string
	 */
	private function render( callable $callback ): string {
		ob_start();
		$callback();
		return (string) ob_get_clean();
	}

	/** The dependency notice appears where WooCommerce can be activated. */
	public function test_missing_woocommerce_notice_renders_on_the_plugins_screen(): void {
		set_current_screen( 'plugins' );

		$this->assertStringContainsString(
			'requires WooCommerce',
			$this->render( array( Plugin::instance(), 'render_missing_woocommerce_notice' ) )
		);
	}

	/** The dependency notice stays off every other admin screen. */
	public function test_missing_woocommerce_notice_is_not_shown_dashboard_wide(): void {
		foreach ( array( 'dashboard', 'edit-post', 'options-general' ) as $screen_id ) {
			set_current_screen( $screen_id );

			$this->assertSame(
				'',
				$this->render( array( Plugin::instance(), 'render_missing_woocommerce_notice' ) ),
				$screen_id . ' must not receive the dependency notice.'
			);
		}
	}

	/** Users who cannot act on the dependency are never told about it. */
	public function test_missing_woocommerce_notice_requires_the_plugin_capability(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		set_current_screen( 'plugins' );

		$this->assertSame( '', $this->render( array( Plugin::instance(), 'render_missing_woocommerce_notice' ) ) );
	}

	/** The duplicate-edition notice appears where an edition is deactivated. */
	public function test_duplicate_edition_notice_renders_on_the_plugins_screen(): void {
		$this->load_duplicate_edition_notice();
		set_current_screen( 'plugins' );

		$this->assertStringContainsString(
			'Two editions',
			$this->render( 'laqi_lusm_render_duplicate_edition_notice' )
		);
	}

	/** The duplicate-edition notice stays off every other admin screen. */
	public function test_duplicate_edition_notice_is_not_shown_dashboard_wide(): void {
		$this->load_duplicate_edition_notice();

		foreach ( array( 'dashboard', 'edit-product', 'options-general' ) as $screen_id ) {
			set_current_screen( $screen_id );

			$this->assertSame(
				'',
				$this->render( 'laqi_lusm_render_duplicate_edition_notice' ),
				$screen_id . ' must not receive the duplicate-edition notice.'
			);
		}
	}

	/**
	 * Define the duplicate-edition renderer, which only exists once a second
	 * edition has loaded the bootstrap file over an already-defined version.
	 *
	 * @return void
	 */
	private function load_duplicate_edition_notice(): void {
		if ( ! function_exists( 'laqi_lusm_render_duplicate_edition_notice' ) ) {
			include dirname( __DIR__ ) . '/laqi-unit-stock-manager.php';
		}
	}
}
