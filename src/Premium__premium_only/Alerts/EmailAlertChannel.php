<?php
/**
 * Paid email alert channel.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Alerts;

defined( 'ABSPATH' ) || exit;

/** Delivers normalized events through WordPress mail. */
final class EmailAlertChannel implements AlertChannelInterface {
	/** Channel key. @return string */
	public function key(): string {
		return 'email'; }
	/** Whether enabled.
	 *
	 * @param array<string,mixed> $policy Policy.
	 * @return bool
	 */
	public function enabled( array $policy ): bool {
		return array() !== array_filter( (array) $policy['recipients'], 'is_email' ); }
	/** Deliver email.
	 *
	 * @param array<string,mixed> $event Event.
	 * @param array<string,mixed> $policy Policy.
	 * @return array{success:bool,message:string}
	 */
	public function deliver( array $event, array $policy ): array {
		$success = wp_mail( array_filter( (array) $policy['recipients'], 'is_email' ), $event['subject'], $event['message'] );
		return array(
			'success' => $success,
			'message' => $success ? 'accepted' : 'wp_mail returned false',
		);
	}
}
