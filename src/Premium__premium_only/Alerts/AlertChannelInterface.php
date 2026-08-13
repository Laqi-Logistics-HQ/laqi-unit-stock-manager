<?php
/**
 * Paid alert channel contract.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Alerts;

defined( 'ABSPATH' ) || exit;

/** Delivers one normalized alert event. */
interface AlertChannelInterface {
	/** Stable channel key. @return string */
	public function key(): string;
	/** Whether configured.
	 *
	 * @param array<string,mixed> $policy Policy.
	 * @return bool
	 */
	public function enabled( array $policy ): bool;
	/** Deliver.
	 *
	 * @param array<string,mixed> $event Event.
	 * @param array<string,mixed> $policy Policy.
	 * @return array{success:bool,message:string}
	 */
	public function deliver( array $event, array $policy ): array;
}
