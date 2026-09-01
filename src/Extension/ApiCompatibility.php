<?php
/**
 * Extension API compatibility checks.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Extension;

defined( 'ABSPATH' ) || exit;

/** Helps add-ons fail closed before registering incompatible behavior. */
final class ApiCompatibility {

	/**
	 * Whether the running Free API is inside a supported half-open range.
	 *
	 * @param string $minimum           Oldest supported API version.
	 * @param string $before_exclusive  First unsupported API version.
	 * @return bool
	 */
	public static function supports( string $minimum, string $before_exclusive ): bool {
		if ( ! defined( 'LAQI_LUSM_API_VERSION' ) ) {
			return false;
		}

		return version_compare( LAQI_LUSM_API_VERSION, $minimum, '>=' )
			&& version_compare( LAQI_LUSM_API_VERSION, $before_exclusive, '<' );
	}
}
