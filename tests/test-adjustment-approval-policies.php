<?php
/** Adjustment reason and approval-policy tests. @package LaqiUnitStockManager */

use LaqiUnitStockManager\Container;
use LaqiUnitStockManager\Domain\Quantity;
use LaqiUnitStockManager\Premium\Approvals\AdjustmentPolicy;
use LaqiUnitStockManager\Premium\Approvals\AdjustmentPolicyRepository;
use LaqiUnitStockManager\Storage\Schema;

/** Verifies reusable reasons and capability-gated sensitive changes. */
class Test_Adjustment_Approval_Policies extends WP_UnitTestCase {
	/** @var Container */ private $container;
	/** @var AdjustmentPolicyRepository */ private $settings;
	/** @var AdjustmentPolicy */ private $policy;
	/** @var int */ private $pool_id;
	/** @var mixed */ private $previous;

	/** Create an isolated policy and pool. */
	public function set_up(): void {
		parent::set_up();
		$this->previous  = get_option( AdjustmentPolicyRepository::OPTION, null );
		$this->container = new Container();
		$this->settings  = new AdjustmentPolicyRepository();
		$this->settings->save( array( 'Cycle count', 'Supplier correction' ), 0.25, 'manage_options' );
		$this->policy = new AdjustmentPolicy( $this->settings, $this->container->pool_repository() );
		$this->policy->register();
		$this->pool_id = $this->container->pool_repository()->create( 'Approval pool ' . wp_generate_uuid4(), new Quantity( 'count', 100 ), 'unit', 'unit' )->id();
	}

	/** Remove policy and stock fixtures. */
	public function tear_down(): void {
		global $wpdb;
		remove_filter( 'laqi_lusm_adjustment_reason_templates', array( $this->policy, 'templates' ) );
		remove_filter( 'laqi_lusm_adjustment_authorized', array( $this->policy, 'authorize' ), 10 );
		if ( null === $this->previous ) {
			delete_option( AdjustmentPolicyRepository::OPTION );
		} else {
			update_option( AdjustmentPolicyRepository::OPTION, $this->previous, false );
		}
		$wpdb->delete( Schema::table( 'movements' ), array( 'pool_id' => $this->pool_id ), array( '%d' ) );
		$wpdb->delete( Schema::table( 'pools' ), array( 'id' => $this->pool_id ), array( '%d' ) );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/** Saved reason labels extend every adjustment form's shared suggestions. */
	public function test_reason_templates_are_reusable(): void {
		$templates = apply_filters( 'laqi_lusm_adjustment_reason_templates', array( 'Existing reason' ), 'manual' );
		$this->assertContains( 'Existing reason', $templates );
		$this->assertContains( 'Cycle count', $templates );
		$this->assertContains( 'Supplier correction', $templates );
	}

	/** Stock managers may make ordinary changes but need an administrator for sensitive ones. */
	public function test_sensitive_adjustment_requires_approver_capability(): void {
		$user_id = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		$service = $this->container->stock_adjustment_service();
		$small   = $service->adjust( $this->pool_id, 'add', '10', 'unit', 'Cycle count', $user_id, 'approval-small-' . $this->pool_id );
		$this->assertSame( 110, $small->balance() );

		try {
			$service->adjust( $this->pool_id, 'subtract', '30', 'unit', 'Damaged stock', $user_id, 'approval-denied-' . $this->pool_id );
			$this->fail( 'Expected sensitive adjustment denial.' );
		} catch ( InvalidArgumentException $error ) {
			$this->assertStringContainsString( 'requires approval', $error->getMessage() );
		}

		$administrator = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$approved      = $service->adjust( $this->pool_id, 'subtract', '30', 'unit', 'Approved damage', $administrator, 'approval-admin-' . $this->pool_id );
		$this->assertSame( 80, $approved->balance() );
	}

	/** A zero threshold explicitly disables the extra permission requirement. */
	public function test_zero_threshold_disables_sensitive_permission_check(): void {
		$this->settings->save( array(), 0, 'manage_options' );
		$user_id = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		$result  = $this->container->stock_adjustment_service()->adjust( $this->pool_id, 'set', '1', 'unit', 'Emergency count', $user_id, 'approval-disabled-' . $this->pool_id );
		$this->assertSame( 1, $result->balance() );
	}
}
