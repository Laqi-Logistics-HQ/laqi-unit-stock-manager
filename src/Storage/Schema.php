<?php
/**
 * Plugin-owned database schema.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the versioned pooled-stock tables.
 */
final class Schema {

	const VERSION        = 1;
	const VERSION_OPTION = 'laqi_lusm_schema_version';

	/**
	 * Install or upgrade the schema.
	 *
	 * @return void
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$pools   = self::table( 'pools' );
		$units   = self::table( 'units' );
		$maps    = self::table( 'mappings' );
		$parts   = self::table( 'mapping_components' );
		$moves   = self::table( 'movements' );

		dbDelta(
			"CREATE TABLE {$pools} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(191) NOT NULL,
				internal_sku varchar(191) NOT NULL DEFAULT '',
				family varchar(20) NOT NULL,
				base_unit varchar(20) NOT NULL,
				display_unit varchar(20) NOT NULL,
				quantity_base bigint(20) NOT NULL DEFAULT 0,
				allow_backorders tinyint(1) NOT NULL DEFAULT 0,
				policy_json longtext NULL,
				version bigint(20) unsigned NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY family (family),
				KEY internal_sku (internal_sku)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$units} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				unit_key varchar(50) NOT NULL,
				label varchar(191) NOT NULL,
				symbol varchar(50) NOT NULL,
				family varchar(20) NOT NULL,
				base_factor bigint(20) unsigned NOT NULL,
				reference_value varchar(50) NOT NULL,
				reference_unit varchar(50) NOT NULL,
				active tinyint(1) NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY unit_key (unit_key),
				KEY family_active (family,active)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$maps} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				product_id bigint(20) unsigned NOT NULL,
				variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
				calculator_type varchar(50) NOT NULL DEFAULT 'single_pool',
				active tinyint(1) NOT NULL DEFAULT 1,
				version bigint(20) unsigned NOT NULL DEFAULT 1,
				effective_from datetime NULL,
				effective_until datetime NULL,
				metadata_json longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY purchasable (product_id,variation_id),
				KEY active (active)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$parts} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				mapping_id bigint(20) unsigned NOT NULL,
				pool_id bigint(20) unsigned NOT NULL,
				consumption_base bigint(20) unsigned NOT NULL,
				role_key varchar(50) NOT NULL DEFAULT 'contents',
				position smallint(5) unsigned NOT NULL DEFAULT 0,
				metadata_json longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY mapping_pool_role (mapping_id,pool_id,role_key),
				KEY pool_id (pool_id)
			) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$moves} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				pool_id bigint(20) unsigned NOT NULL,
				batch_id bigint(20) unsigned NOT NULL DEFAULT 0,
				type varchar(50) NOT NULL,
				delta_base bigint(20) NOT NULL,
				balance_base bigint(20) NOT NULL,
				source_type varchar(50) NOT NULL DEFAULT '',
				source_id bigint(20) unsigned NOT NULL DEFAULT 0,
				idempotency_key varchar(191) NULL,
				actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
				reason text NULL,
				metadata_json longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY idempotency_key (idempotency_key),
				KEY pool_created (pool_id,created_at),
				KEY source (source_type,source_id)
			) {$charset};"
		);

		update_option( self::VERSION_OPTION, self::VERSION, false );
	}

	/**
	 * Resolve a plugin table name.
	 *
	 * @param string $suffix Table suffix.
	 * @return string
	 */
	public static function table( string $suffix ): string {
		global $wpdb;

		return $wpdb->prefix . 'laqi_lusm_' . $suffix;
	}
}
