/**
 * Laqi Unit Stock Manager for WooCommerce — admin scripts.
 *
 * Strings wrapped in wp.i18n.__() with the 'laqi-unit-stock-manager' text domain are
 * translatable. The 'wp-i18n' dependency (declared in Assets.php) provides the
 * wp.i18n global; `wp i18n make-json` compiles the .po into the JSON file that
 * wp_set_script_translations() loads. See languages/README.md.
 *
 * @param {Object} wp The WordPress global namespace.
 */
( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.i18n ) {
		return;
	}

	const __ = wp.i18n.__;

	// Example: a translatable string ready to use.
	// console.log( __( 'Hello from Laqi Unit Stock Manager for WooCommerce', 'laqi-unit-stock-manager' ) );
	void __;
} )( window.wp );
