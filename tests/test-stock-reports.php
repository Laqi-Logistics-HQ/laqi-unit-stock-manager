<?php
/**
 * Scheduled stock report integration tests.
 *
 * @package LaqiUnitStockManager
 */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Alerts\LowStockPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\ForecastPolicyRepository;
use LaqiUnitStockManager\Premium\Forecasting\StockForecastService;
use LaqiUnitStockManager\Premium\Reports\StockReportBuilder;
use LaqiUnitStockManager\Premium\Reports\StockReportScheduler;
use LaqiUnitStockManager\Premium\Reports\StockReportSettings;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies report configuration, CSV generation, mail delivery, and history. */
class Test_Stock_Reports extends WP_UnitTestCase {
	/** @var int */
	private $pool_id;

	/** Install custom tables once. */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		Schema::install();
	}

	/** Create one identifiable pool. */
	public function set_up(): void {
		parent::set_up();
		$pool = ( new Container() )->pool_repository()->create(
			'=Report ingredient',
			new Quantity( 'mass', 10000000000000 ),
			'ng',
			'kg',
			false,
			'+REPORT-SKU'
		);
		$this->pool_id = $pool->id();
	}

	/** Remove report state and custom-table fixtures. */
	public function tear_down(): void {
		global $wpdb;

		wp_clear_scheduled_hook( StockReportScheduler::CRON_HOOK );
		delete_option( StockReportSettings::OPTION );
		delete_option( StockReportScheduler::HISTORY_OPTION );
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		parent::tear_down();
	}

	/** Settings reject unknown frequencies and invalid recipients. */
	public function test_settings_are_normalized(): void {
		$settings = new StockReportSettings();
		$settings->save( true, 'hourly', array( 'ops@example.org', 'invalid', 'ops@example.org' ) );

		$this->assertSame(
			array(
				'enabled'    => true,
				'frequency'  => 'weekly',
				'recipients' => array( 'ops@example.org' ),
			),
			$settings->get()
		);
	}

	/** Schedule reconciliation adds, changes, and removes the single report event. */
	public function test_schedule_tracks_saved_settings(): void {
		$settings  = new StockReportSettings();
		$scheduler = $this->scheduler( $settings );
		add_filter( 'cron_schedules', array( $scheduler, 'schedules' ) );

		$settings->save( true, 'weekly', array( 'ops@example.org' ) );
		$scheduler->sync_schedule();
		$this->assertSame( 'laqi_lusm_weekly', wp_get_scheduled_event( StockReportScheduler::CRON_HOOK )->schedule );

		$settings->save( true, 'daily', array( 'ops@example.org' ) );
		$scheduler->sync_schedule();
		$this->assertSame( 'daily', wp_get_scheduled_event( StockReportScheduler::CRON_HOOK )->schedule );

		$settings->save( false, 'daily', array() );
		$scheduler->sync_schedule();
		$this->assertFalse( wp_next_scheduled( StockReportScheduler::CRON_HOOK ) );
		remove_filter( 'cron_schedules', array( $scheduler, 'schedules' ) );
	}

	/** CSV rows include operational data and neutralize spreadsheet formulas. */
	public function test_builder_produces_safe_stock_rows(): void {
		$rows = $this->builder()->rows();
		$row  = current(
			array_values(
				array_filter(
					$rows,
					function ( array $candidate ): bool {
						return $this->pool_id === (int) $candidate[0];
					}
				)
			)
		);

		$this->assertIsArray( $row );
		$this->assertSame( "'=Report ingredient", $row[1] );
		$this->assertSame( "'+REPORT-SKU", $row[2] );
		$this->assertSame( '10', $row[3] );
		$this->assertSame( 'kg', $row[4] );
		$this->assertSame( 'not configured', $row[5] );
		$this->assertSame( 'insufficient_data', $row[6] );
	}

	/** Mail receives a real CSV attachment which is deleted after delivery. */
	public function test_manual_send_attaches_csv_and_records_delivery_history(): void {
		$settings = new StockReportSettings();
		$settings->save( false, 'weekly', array( 'ops@example.org' ) );
		$captured = array();
		$filter   = static function ( $return, array $attributes ) use ( &$captured ) {
			$file                 = $attributes['attachments'][0];
			$captured['file']     = $file;
			$captured['exists']   = file_exists( $file );
			$captured['contents'] = file_get_contents( $file );
			$captured['to']       = $attributes['to'];
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );

		$result = $this->scheduler( $settings )->send( true );
		remove_filter( 'pre_wp_mail', $filter, 10 );

		$this->assertTrue( $result );
		$this->assertTrue( $captured['exists'] );
		$this->assertFalse( file_exists( $captured['file'] ) );
		$this->assertSame( array( 'ops@example.org' ), $captured['to'] );
		$this->assertStringStartsWith( "\xEF\xBB\xBF", $captured['contents'] );
		$this->assertStringContainsString( "'=Report ingredient", $captured['contents'] );
		$history = get_option( StockReportScheduler::HISTORY_OPTION );
		$this->assertSame( 'manual', $history[0]['trigger'] );
		$this->assertTrue( $history[0]['success'] );
		$this->assertSame( 1, $history[0]['recipients'] );
		$this->assertGreaterThanOrEqual( 1, $history[0]['rows'] );
	}

	/** Construct the production report builder. */
	private function builder(): StockReportBuilder {
		global $wpdb;

		$container = new Container();
		return new StockReportBuilder(
			$container->pool_repository(),
			$container->quantity_formatter(),
			new LowStockPolicyRepository( $wpdb ),
			new ForecastPolicyRepository( $wpdb ),
			new StockForecastService( $container->movement_repository() )
		);
	}

	/** Construct the production scheduler. */
	private function scheduler( StockReportSettings $settings ): StockReportScheduler {
		return new StockReportScheduler( $settings, $this->builder() );
	}
}
