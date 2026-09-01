<?php
/**
 * Insufficient pooled stock exception.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Inventory;

defined( 'ABSPATH' ) || exit;

use RuntimeException;

/**
 * Raised when an atomic decrement cannot be satisfied.
 */
final class InsufficientStockException extends RuntimeException {}
