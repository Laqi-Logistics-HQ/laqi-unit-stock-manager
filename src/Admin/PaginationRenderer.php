<?php
/**
 * Shared Unit Stock screen pagination.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders consistent, accessible pagination for Free and registered Pro tabs.
 */
final class PaginationRenderer {

	/**
	 * Render a result summary and page links.
	 *
	 * @param string               $summary     Localized result summary.
	 * @param string               $aria_label  Localized navigation label.
	 * @param string               $page_arg    Query argument carrying the page.
	 * @param array<string, mixed> $query_args  Query arguments preserved in links.
	 * @param int                  $current     Current page.
	 * @param int                  $total_pages Total pages.
	 * @return void
	 */
	public function render( string $summary, string $aria_label, string $page_arg, array $query_args, int $current, int $total_pages ): void {
		?>
		<div class="laqi-lusm-pagination">
			<p><?php echo esc_html( $summary ); ?></p>
			<?php if ( $total_pages > 1 ) : ?>
				<nav aria-label="<?php echo esc_attr( $aria_label ); ?>">
				<?php
				$query_args[ $page_arg ] = 999999999;
				$url                     = add_query_arg( $query_args, admin_url( 'admin.php' ) );
				$links                   = paginate_links(
					array(
						'base'      => str_replace( '999999999', '%#%', $url ),
						'current'   => $current,
						'total'     => $total_pages,
						'type'      => 'list',
						'prev_text' => __( 'Previous', 'laqi-unit-stock-manager' ),
						'next_text' => __( 'Next', 'laqi-unit-stock-manager' ),
					)
				);
				echo wp_kses_post( $links );
				?>
				</nav>
			<?php endif; ?>
		</div>
		<?php
	}
}
