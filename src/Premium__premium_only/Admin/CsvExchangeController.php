<?php
/**
 * Operations CSV import and export controller.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Admin;

defined( 'ABSPATH' ) || exit;
// phpcs:disable Squiz.Commenting.VariableComment, Squiz.Commenting.FunctionComment
// phpcs:disable Squiz.Commenting.FunctionCommentThrowTag
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
use LaqiUnitStockManager\Admin\UnitStockPage;
use LaqiUnitStockManager\Premium\Exchange\OperationsCsvService;
use Throwable;

/** Secures versioned operational CSV exchange. */
final class CsvExchangeController {
	/** Exchange. @var OperationsCsvService */ private $exchange;
	/** Constructor. @param OperationsCsvService $exchange Exchange. */ public function __construct( OperationsCsvService $exchange ) {
		$this->exchange = $exchange; }
	/** Register endpoints. @return void */ public function register(): void {
		add_action( 'admin_post_laqi_lusm_export_operations', array( $this, 'export' ) );
		add_action( 'admin_post_laqi_lusm_import_operations', array( $this, 'import' ) ); }
	/** Stream export. @return void */
	public function export(): void {
		$this->authorize( 'laqi_lusm_export_operations' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="laqi-unit-stock-operations-' . gmdate( 'Y-m-d-His' ) . '.csv"' );
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			wp_die( esc_html__( 'The operations export could not be created.', 'laqi-unit-stock-manager' ) );
		} fwrite( $output, "\xEF\xBB\xBF" );
		fputcsv( $output, $this->exchange->headers() );
		foreach ( $this->exchange->rows() as $row ) {
			fputcsv( $output, array_values( $row ) );
		} fclose( $output );
		exit; }
	/** Import upload. @return void */
	public function import(): void {
		$this->authorize( 'laqi_lusm_import_operations' );
		try {
			$file = isset( $_FILES['operations_csv'] ) && is_array( $_FILES['operations_csv'] ) ? $_FILES['operations_csv'] : array();
			if ( UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || (int) ( $file['size'] ?? 0 ) > 2097152 || ! isset( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
				throw new \InvalidArgumentException( 'The CSV upload is invalid.' );
			} $result = $this->exchange->import_file( $file['tmp_name'] );
			$this->redirect( 'csv_imported', $result );
		} catch ( Throwable $error ) {
			unset( $error );
			$this->redirect( 'csv_error', array() ); } }
	/** Authorization. */ private function authorize( string $nonce ): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You are not allowed to exchange stock operations.', 'laqi-unit-stock-manager' ) );
		} check_admin_referer( $nonce ); }
	/** Redirect. */ private function redirect( string $result, array $counts ): void {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                       => UnitStockPage::SLUG,
					'section'                    => 'receiving',
					'laqi_lusm_receiving_result' => $result,
					'created'                    => (int) ( $counts['created'] ?? 0 ),
					'skipped'                    => (int) ( $counts['skipped'] ?? 0 ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit; }
}
