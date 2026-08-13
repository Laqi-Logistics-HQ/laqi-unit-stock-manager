<?php
/**
 * Paid alert channel registry.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Alerts;

defined( 'ABSPATH' ) || exit;

/** Extensible catalog of independent delivery channels. */
final class AlertChannelRegistry {
	/** Registered channels.
	 *
	 * @var array<string,AlertChannelInterface>
	 */
	private $channels = array();
	/** Register.
	 *
	 * @param AlertChannelInterface $channel Channel.
	 * @return void
	 */
	public function register( AlertChannelInterface $channel ): void {
		$this->channels[ $channel->key() ] = $channel;
	}
	/** Enabled channels.
	 *
	 * @param array<string,mixed> $policy Policy.
	 * @return array<string,AlertChannelInterface>
	 */
	public function enabled( array $policy ): array {
		return array_filter(
			$this->channels,
			static function ( AlertChannelInterface $channel ) use ( $policy ): bool {
				return $channel->enabled( $policy );
			}
		);
	}
}
