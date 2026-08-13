<?php
/**
 * Paid alert delivery persistence.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Alerts;

defined( 'ABSPATH' ) || exit;

use wpdb;

/** Records channel attempts and successful-event deduplication. */
final class AlertDeliveryRepository {
	const SCHEMA_OPTION = 'laqi_lusm_alert_schema_version';
	/** Database.
	 *
	 * @var wpdb
	 */
	private $db;
	/** Constructor.
	 *
	 * @param wpdb $db Database.
	 */
	public function __construct( wpdb $db ) {
		$this->db = $db; }
	/** Install the paid delivery table. @return void */
	public function install(): void {
		if ( 1 === (int) get_option( self::SCHEMA_OPTION, 0 ) ) {
			return;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = $this->table();
		$charset = $this->db->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				pool_id bigint(20) unsigned NOT NULL,
				event_key varchar(191) NOT NULL,
				channel varchar(50) NOT NULL,
				success tinyint(1) NOT NULL DEFAULT 0,
				message varchar(255) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY event_channel (event_key,channel),
				KEY pool_created (pool_id,created_at)
			) {$charset};"
		);
		update_option( self::SCHEMA_OPTION, 1, false );
	}
	/** Whether succeeded.
	 *
	 * @param string $event_key Event key.
	 * @param string $channel Channel.
	 * @return bool
	 */
	public function succeeded( string $event_key, string $channel ): bool {
		return (bool) $this->db->get_var( $this->db->prepare( 'SELECT 1 FROM ' . $this->table() . ' WHERE event_key = %s AND channel = %s AND success = 1 LIMIT 1', $event_key, $channel ) );
	}
	/** Record.
	 *
	 * @param int    $pool_id Pool ID.
	 * @param string $event_key Event.
	 * @param string $channel Channel.
	 * @param bool   $success Success.
	 * @param string $message Result.
	 * @return void
	 */
	public function record( int $pool_id, string $event_key, string $channel, bool $success, string $message ): void {
		$this->db->insert(
			$this->table(),
			array(
				'pool_id'    => $pool_id,
				'event_key'  => $event_key,
				'channel'    => $channel,
				'success'    => $success ? 1 : 0,
				'message'    => substr( $message, 0, 255 ),
				'created_at' => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s' )
		);
	}
	/** Recent attempts.
	 *
	 * @param int $limit Limit.
	 * @return array<int,array<string,mixed>>
	 */
	public function recent( int $limit = 25 ): array {
		$rows = $this->db->get_results( $this->db->prepare( 'SELECT d.*, p.name AS pool_name FROM ' . $this->table() . ' d LEFT JOIN ' . $this->db->prefix . 'laqi_lusm_pools p ON p.id = d.pool_id ORDER BY d.id DESC LIMIT %d', max( 1, min( 100, $limit ) ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
	/** Table name. @return string */
	private function table(): string {
		return $this->db->prefix . 'laqi_lusm_alert_deliveries'; }
}
