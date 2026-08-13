<?php
/**
 * Paid webhook alert channel.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Premium\Alerts;

defined( 'ABSPATH' ) || exit;

/** Sends signed JSON without exposing the shared secret in the payload. */
final class WebhookAlertChannel implements AlertChannelInterface {
	/** Channel key. @return string */
	public function key(): string {
		return 'webhook'; }
	/** Whether enabled.
	 *
	 * @param array<string,mixed> $policy Policy.
	 * @return bool
	 */
	public function enabled( array $policy ): bool {
		return ! empty( $policy['webhook_url'] ) && ! empty( $policy['webhook_secret'] ); }
	/** Deliver webhook.
	 *
	 * @param array<string,mixed> $event Event.
	 * @param array<string,mixed> $policy Policy.
	 * @return array{success:bool,message:string}
	 */
	public function deliver( array $event, array $policy ): array {
		$body     = wp_json_encode( $event );
		$response = wp_safe_remote_post(
			$policy['webhook_url'],
			array(
				'timeout' => 10,
				'headers' => array(
					'Content-Type'     => 'application/json',
					'X-Laqi-Signature' => 'sha256=' . hash_hmac( 'sha256', $body, $policy['webhook_secret'] ),
				),
				'body'    => $body,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array(
				'success' => false,
				'message' => $response->get_error_message(),
			);
		}
		$code = wp_remote_retrieve_response_code( $response );
		return array(
			'success' => $code >= 200 && $code < 300,
			'message' => 'HTTP ' . $code,
		);
	}
}
