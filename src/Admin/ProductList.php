<?php
/**
 * Unit Stock status in the WooCommerce product list.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use LaqiUnitStockManager\Storage\MappingRepository;
use LaqiUnitStockManager\Storage\Schema;
use WP_Query;

/** Adds bulk-loaded mapping status, filters, and product-owned edit routes. */
final class ProductList {

	/**
	 * Mapping read projections.
	 *
	 * @var MappingRepository
	 */
	private $mappings;

	/**
	 * Summaries for the current product-list page.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	private $summaries = array();

	/**
	 * Constructor.
	 *
	 * @param MappingRepository $mappings Mapping reads.
	 */
	public function __construct( MappingRepository $mappings ) {
		$this->mappings = $mappings;
	}

	/** Register product-list hooks. @return void */
	public function register(): void {
		add_filter( 'manage_edit-product_columns', array( $this, 'columns' ), 30 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filter' ), 30, 2 );
		add_filter( 'posts_where', array( $this, 'filter_where' ), 20, 2 );
		add_filter( 'the_posts', array( $this, 'preload' ), 20, 2 );
	}

	/** Add the Unit Stock column after product type.
	 *
	 * @param array<string, string> $columns Product columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'product_type' === $key ) {
				$result['laqi_lusm_status'] = __( 'Unit Stock', 'laqi-unit-stock-manager' );
			}
		}
		if ( ! isset( $result['laqi_lusm_status'] ) ) {
			$result['laqi_lusm_status'] = __( 'Unit Stock', 'laqi-unit-stock-manager' );
		}
		return $result;
	}

	/** Preload the current page's mapping projection in one query.
	 *
	 * @param WP_Post[] $posts Query posts.
	 * @param WP_Query  $query Query.
	 * @return WP_Post[]
	 */
	public function preload( array $posts, WP_Query $query ): array {
		if ( is_admin() && $query->is_main_query() && 'product' === $query->get( 'post_type' ) && array() !== $posts ) {
			$this->summaries = $this->mappings->summaries_for_products( wp_list_pluck( $posts, 'ID' ) );
		}
		return $posts;
	}

	/** Render one status cell.
	 *
	 * @param string $column Column key.
	 * @param int    $product_id Product ID.
	 * @return void
	 */
	public function column( string $column, int $product_id ): void {
		if ( 'laqi_lusm_status' !== $column ) {
			return;
		}
		$summary = $this->summaries[ $product_id ] ?? null;
		$product = wc_get_product( $product_id );
		$url     = $this->edit_url( $product_id, $product && $product->is_type( 'variable' ) );
		if ( null === $summary ) {
			echo '<a class="laqi-lusm-product-status laqi-lusm-product-status--muted" href="' . esc_url( $url ) . '">' . esc_html__( 'Not linked', 'laqi-unit-stock-manager' ) . '</a>';
			return;
		}

		echo '<a class="laqi-lusm-product-status" href="' . esc_url( $url ) . '">';
		if ( (int) $summary['variation_count'] > 0 ) {
			printf(
				/* translators: 1: mapped variation count, 2: total variation count. */
				esc_html__( '%1$d of %2$d variations configured', 'laqi-unit-stock-manager' ),
				(int) $summary['variation_mapping_count'],
				(int) $summary['variation_count']
			);
		} elseif ( 1 === count( $summary['pool_names'] ) ) {
			/* translators: %s: inventory pool name. */
			echo esc_html( sprintf( __( 'Linked to %s', 'laqi-unit-stock-manager' ), $summary['pool_names'][0] ) );
		} else {
			esc_html_e( 'Linked', 'laqi-unit-stock-manager' );
		}
		echo '</a>';

		if ( (int) $summary['recipe_count'] > 0 ) {
			printf(
				'<span class="laqi-lusm-product-status-detail">%s</span>',
				esc_html(
					sprintf(
						/* translators: %d: total recipe component count. */
						_n( 'Recipe with %d component', 'Recipe with %d components', (int) $summary['recipe_component_count'], 'laqi-unit-stock-manager' ),
						(int) $summary['recipe_component_count']
					)
				)
			);
		}
		if ( (int) $summary['warning_count'] > 0 || ( (int) $summary['variation_count'] > 0 && (int) $summary['variation_mapping_count'] < (int) $summary['variation_count'] ) ) {
			echo '<span class="laqi-lusm-product-status-warning">' . esc_html__( 'Configuration warning', 'laqi-unit-stock-manager' ) . '</span>';
		}
	}

	/** Render the product-list mapping filter.
	 *
	 * @param string $post_type Current post type.
	 * @param string $location  Filter location.
	 * @return void
	 */
	public function filter( string $post_type, string $location ): void {
		if ( 'product' !== $post_type || 'top' !== $location ) {
			return;
		}
		$current = isset( $_GET['laqi_lusm_status'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<label class="screen-reader-text" for="laqi-lusm-product-status-filter"><?php esc_html_e( 'Filter by Unit Stock status', 'laqi-unit-stock-manager' ); ?></label>
		<select id="laqi-lusm-product-status-filter" name="laqi_lusm_status">
			<option value=""><?php esc_html_e( 'All Unit Stock states', 'laqi-unit-stock-manager' ); ?></option>
			<option value="linked" <?php selected( 'linked', $current ); ?>><?php esc_html_e( 'Linked', 'laqi-unit-stock-manager' ); ?></option>
			<option value="unlinked" <?php selected( 'unlinked', $current ); ?>><?php esc_html_e( 'Not linked', 'laqi-unit-stock-manager' ); ?></option>
			<option value="attention" <?php selected( 'attention', $current ); ?>><?php esc_html_e( 'Incomplete or warning', 'laqi-unit-stock-manager' ); ?></option>
			<option value="recipe" <?php selected( 'recipe', $current ); ?>><?php esc_html_e( 'Recipe link', 'laqi-unit-stock-manager' ); ?></option>
		</select>
		<?php
	}

	/** Apply the selected status with SQL EXISTS projections.
	 *
	 * @param string   $where Existing query WHERE clause.
	 * @param WP_Query $query Query.
	 * @return string
	 */
	public function filter_where( string $where, WP_Query $query ): string {
		global $wpdb;
		if ( ! is_admin() || 'product' !== $query->get( 'post_type' ) ) {
			return $where;
		}
		$status = isset( $_GET['laqi_lusm_status'] ) ? sanitize_key( wp_unslash( $_GET['laqi_lusm_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $status, array( 'linked', 'unlinked', 'attention', 'recipe' ), true ) ) {
			return $where;
		}

		$mappings = Schema::table( 'mappings' );
		$parts    = Schema::table( 'mapping_components' );
		$pools    = Schema::table( 'pools' );
		$active   = 'EXISTS (SELECT 1 FROM ' . $mappings . ' lm WHERE lm.product_id = ' . $wpdb->posts . '.ID AND lm.active = 1)';
		if ( 'linked' === $status ) {
			return $where . ' AND ' . $active;
		}
		if ( 'unlinked' === $status ) {
			return $where . ' AND NOT ' . $active;
		}
		if ( 'recipe' === $status ) {
			return $where . ' AND EXISTS (SELECT 1 FROM ' . $mappings . ' lm WHERE lm.product_id = ' . $wpdb->posts . '.ID AND lm.active = 1 AND lm.calculator_type = "recipe")';
		}

		$incomplete = '(SELECT COUNT(*) FROM ' . $wpdb->posts . ' lv WHERE lv.post_parent = ' . $wpdb->posts . '.ID AND lv.post_type = "product_variation" AND lv.post_status NOT IN ("trash", "auto-draft")) > (SELECT COUNT(*) FROM ' . $mappings . ' lm WHERE lm.product_id = ' . $wpdb->posts . '.ID AND lm.variation_id > 0 AND lm.active = 1)';
		$warning    = 'EXISTS (SELECT 1 FROM ' . $mappings . ' lm LEFT JOIN ' . $parts . ' lc ON lc.mapping_id = lm.id LEFT JOIN ' . $pools . ' lp ON lp.id = lc.pool_id LEFT JOIN ' . $wpdb->posts . ' li ON li.ID = CASE WHEN lm.variation_id > 0 THEN lm.variation_id ELSE lm.product_id END AND li.post_status NOT IN ("trash", "auto-draft") LEFT JOIN ' . $wpdb->postmeta . ' ls ON ls.post_id = CASE WHEN lm.variation_id > 0 THEN lm.variation_id ELSE lm.product_id END AND ls.meta_key = "_manage_stock" WHERE lm.product_id = ' . $wpdb->posts . '.ID AND lm.active = 1 AND (li.ID IS NULL OR lc.id IS NULL OR lp.id IS NULL OR ls.meta_value = "yes"))';
		return $where . ' AND ' . $active . ' AND (' . $incomplete . ' OR ' . $warning . ')';
	}

	/** Build the most relevant product-owned edit URL.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $variable Whether the product is variable.
	 * @return string
	 */
	private function edit_url( int $product_id, bool $variable ): string {
		return add_query_arg(
			array( 'laqi_lusm_open' => $variable ? 'variations' : 'unit-stock' ),
			(string) get_edit_post_link( $product_id, '' )
		);
	}
}
