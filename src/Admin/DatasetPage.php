<?php
/**
 * Shared Pro admin dataset pagination arithmetic.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

/** Clamps a requested page against a filtered row count. */
final class DatasetPage {
	const PER_PAGE = 25;

	/**
	 * Clamped page number.
	 *
	 * @var int
	 */
	private $number;

	/**
	 * Rows per page.
	 *
	 * @var int
	 */
	private $per_page;

	/**
	 * Total matching rows.
	 *
	 * @var int
	 */
	private $total;

	/**
	 * Constructor.
	 *
	 * @param int $number   Requested page.
	 * @param int $total    Total matching rows.
	 * @param int $per_page Rows per page.
	 */
	public function __construct( int $number, int $total, int $per_page = self::PER_PAGE ) {
		$this->per_page = max( 1, $per_page );
		$this->total    = max( 0, $total );
		$this->number   = min( max( 1, $number ), $this->total_pages() );
	}

	/**
	 * Read the requested page from the query string.
	 *
	 * @param string $page_arg Query argument carrying the page.
	 * @param int    $total    Total matching rows.
	 * @param int    $per_page Rows per page.
	 * @return self
	 */
	public static function from_query( string $page_arg, int $total, int $per_page = self::PER_PAGE ): self {
		$requested = isset( $_GET[ $page_arg ] ) ? absint( $_GET[ $page_arg ] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only page selection.

		return new self( $requested, $total, $per_page );
	}

	/** Clamped page number. @return int */
	public function number(): int {
		return $this->number;
	}

	/** Row offset for the current page. @return int */
	public function offset(): int {
		return ( $this->number - 1 ) * $this->per_page;
	}

	/** Rows per page. @return int */
	public function per_page(): int {
		return $this->per_page;
	}

	/** Total matching rows. @return int */
	public function total(): int {
		return $this->total;
	}

	/** Total pages, never below one. @return int */
	public function total_pages(): int {
		return max( 1, intdiv( $this->total + $this->per_page - 1, $this->per_page ) );
	}

	/** Whether the filtered dataset matched nothing. @return bool */
	public function is_empty(): bool {
		return 0 === $this->total;
	}

	/**
	 * Localized "showing x-y of z" summary.
	 *
	 * @param string $format    Format taking first row, last row, and total.
	 * @param int    $row_count Rows actually rendered on this page.
	 * @return string
	 */
	public function summary( string $format, int $row_count ): string {
		return sprintf( $format, $this->offset() + 1, $this->offset() + max( 0, $row_count ), $this->total );
	}
}
