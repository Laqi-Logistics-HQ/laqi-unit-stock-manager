<?php
/**
 * One filtered, paginated Pro admin table.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

/** Carries the URL state that a dataset's filter bar and page links share. */
final class DatasetView {
	/**
	 * Dataset key; prefixes the page query argument.
	 *
	 * @var string
	 */
	private $key;

	/**
	 * Active filters.
	 *
	 * @var DatasetFilters
	 */
	private $filters;

	/**
	 * Clamped page.
	 *
	 * @var DatasetPage
	 */
	private $page;

	/**
	 * Screen arguments every link keeps.
	 *
	 * @var array<string, string>
	 */
	private $screen_args;

	/**
	 * Workspace panel fragment, so links land on the right tab.
	 *
	 * @var string
	 */
	private $fragment;

	/**
	 * Constructor.
	 *
	 * @param string                $key         Dataset key.
	 * @param DatasetFilters        $filters     Active filters.
	 * @param DatasetPage           $page        Clamped page.
	 * @param array<string, string> $screen_args Screen arguments every link keeps.
	 * @param string                $fragment    Workspace panel ID, without the hash.
	 */
	public function __construct( string $key, DatasetFilters $filters, DatasetPage $page, array $screen_args, string $fragment = '' ) {
		$this->key         = $key;
		$this->filters     = $filters;
		$this->page        = $page;
		$this->screen_args = $screen_args;
		$this->fragment    = $fragment;
	}

	/** Dataset key. @return string */
	public function key(): string {
		return $this->key;
	}

	/** Active filters. @return DatasetFilters */
	public function filters(): DatasetFilters {
		return $this->filters;
	}

	/** Clamped page. @return DatasetPage */
	public function page(): DatasetPage {
		return $this->page;
	}

	/** Query argument carrying this dataset's page. @return string */
	public function page_arg(): string {
		return $this->key . '_page';
	}

	/**
	 * Workspace panel fragment, including the hash.
	 *
	 * Applies to the controls this plugin renders. Free's page links carry no
	 * fragment, which costs nothing: `receiving_view` is what actually decides
	 * which panel opens, and the fragment only hints where to scroll.
	 *
	 * @return string
	 */
	public function fragment(): string {
		return '' === $this->fragment ? '' : '#' . $this->fragment;
	}

	/**
	 * Screen and filter arguments that every link for this dataset keeps.
	 *
	 * @return array<string, string>
	 */
	public function link_args(): array {
		return array_merge( $this->screen_args, $this->filters->query_args() );
	}

	/** Screen arguments only, with this dataset's filters cleared. @return string */
	public function reset_url(): string {
		return add_query_arg( $this->screen_args, admin_url( 'edit.php' ) ) . $this->fragment();
	}

	/** Action for a GET form that submits this dataset's filters. @return string */
	public function form_action(): string {
		return admin_url( 'edit.php' ) . $this->fragment();
	}
}
