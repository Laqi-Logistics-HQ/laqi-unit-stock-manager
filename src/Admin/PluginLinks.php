<?php
/**
 * Links on the Installed Plugins screen.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

/** Adds a product link for merchants using the free edition. */
final class PluginLinks {

	/**
	 * Register links for this plugin's row.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'plugin_action_links_' . plugin_basename( LAQI_LUSM_FILE ), array( $this, 'add_pro_link' ) );
	}

	/**
	 * Offer Pro when its add-on is not active.
	 *
	 * @param array<string,string> $links Existing plugin action links.
	 * @return array<string,string>
	 */
	public function add_pro_link( array $links ): array {
		// WordPress includes network activation in this check. Owners should
		// not see another purchase prompt before activating their licence.
		if ( is_plugin_active( 'laqi-unit-stock-manager-pro/laqi-unit-stock-manager-pro.php' ) || ! current_user_can( 'activate_plugins' ) ) {
			return $links;
		}

		$links['laqi_lusm_get_pro'] = sprintf(
			'<a href="%s"><strong>%s</strong></a>',
			esc_url( 'https://laqi-logistics.com/plugins/laqi-unit-stock-manager/' ),
			esc_html__( 'Get PRO', 'laqi-unit-stock-manager' )
		);

		return $links;
	}
}
