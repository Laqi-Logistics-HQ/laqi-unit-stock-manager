<?php
/**
 * Paid movement ledger CSV export.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Presentation\MovementPresenter;
use LaqiUnitStockManager\Storage\MovementRepository;

/** Streams matching immutable movements without loading the whole ledger. */
final class MovementLedgerExportController {
	const BATCH_SIZE = 500;

	/**
	 * Movement reads.
	 *
	 * @var MovementRepository
	 */
	private $movements;

	/**
	 * Movement presentation.
	 *
	 * @var MovementPresenter
	 */
	private $presenter;

	/** Constructor.
	 *
	 * @param MovementRepository $movements Movement reads.
	 * @param MovementPresenter  $presenter Movement presentation.
	 */
	public function __construct( MovementRepository $movements, MovementPresenter $presenter ) {
		$this->movements = $movements;
		$this->presenter = $presenter;
	}

	/** Register the authenticated download endpoint. @return void */
	public function register(): void {
		add_action( 'admin_post_laqi_lusm_export_ledger', array( $this, 'handle' ) );
	}

	/** Validate and stream a CSV download. @return void */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to export the stock ledger.', 'laqi-unit-stock-manager' ) );
		}

		check_admin_referer( 'laqi_lusm_export_ledger' );
		$term = isset( $_GET['ledger_search'] ) ? sanitize_text_field( wp_unslash( $_GET['ledger_search'] ) ) : '';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="laqi-unit-stock-ledger-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'The stock ledger export could not be created.', 'laqi-unit-stock-manager' ) );
		}

		fwrite( $output, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streaming a download, not writing a file.
		fputcsv( $output, $this->headers() );
		$offset    = 0;
		$row_count = 0;
		do {
			$rows = $this->movements->search( $term, self::BATCH_SIZE, $offset );
			foreach ( $rows as $row ) {
				fputcsv( $output, $this->csv_row( $this->presenter->present( $row ) ) );
			}
			$row_count = count( $rows );
			$offset   += $row_count;
		} while ( self::BATCH_SIZE === $row_count );
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing the download stream.
		exit;
	}

	/** CSV headings. @return array<int, string> */
	private function headers(): array {
		return array(
			__( 'Movement ID', 'laqi-unit-stock-manager' ),
			__( 'Date (UTC)', 'laqi-unit-stock-manager' ),
			__( 'Inventory pool', 'laqi-unit-stock-manager' ),
			__( 'Movement', 'laqi-unit-stock-manager' ),
			__( 'Change', 'laqi-unit-stock-manager' ),
			__( 'Balance', 'laqi-unit-stock-manager' ),
			__( 'Source type', 'laqi-unit-stock-manager' ),
			__( 'Source ID', 'laqi-unit-stock-manager' ),
			__( 'Actor ID', 'laqi-unit-stock-manager' ),
			__( 'Actor', 'laqi-unit-stock-manager' ),
			__( 'Reason', 'laqi-unit-stock-manager' ),
		);
	}

	/** Build one spreadsheet-safe CSV row.
	 *
	 * @param array<string, mixed> $row Presented movement.
	 * @return array<int, string|int>
	 */
	private function csv_row( array $row ): array {
		return array(
			$row['id'],
			self::spreadsheet_safe( $row['created_at'] ),
			self::spreadsheet_safe( $row['pool_name'] ),
			self::spreadsheet_safe( $row['type_label'] ),
			self::spreadsheet_safe( $row['delta_display'] ),
			self::spreadsheet_safe( $row['balance_display'] ),
			self::spreadsheet_safe( $row['source_type'] ),
			$row['source_id'],
			$row['actor_id'],
			self::spreadsheet_safe( '' !== $row['actor_name'] ? $row['actor_name'] : __( 'System or deleted user', 'laqi-unit-stock-manager' ) ),
			self::spreadsheet_safe( $row['reason'] ),
		);
	}

	/** Prevent exported text from becoming a spreadsheet formula.
	 *
	 * @param string $value Cell value.
	 * @return string
	 */
	public static function spreadsheet_safe( string $value ): string {
		return preg_match( '/^[=+\-@]/', $value ) ? "'" . $value : $value;
	}
}
