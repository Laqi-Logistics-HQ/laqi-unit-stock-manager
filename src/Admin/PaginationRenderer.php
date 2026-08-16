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
		$previous_url = $current > 1 ? add_query_arg( array_merge( $query_args, array( $page_arg => $current - 1 ) ), admin_url( 'admin.php' ) ) : '';
		$next_url     = $current < $total_pages ? add_query_arg( array_merge( $query_args, array( $page_arg => $current + 1 ) ), admin_url( 'admin.php' ) ) : '';
		?>
		<div class="laqi-lusm-pagination">
			<p><?php echo esc_html( $summary ); ?></p>
			<?php if ( $total_pages > 1 ) : ?>
				<nav aria-label="<?php echo esc_attr( $aria_label ); ?>">
					<?php if ( '' !== $previous_url ) : ?>
						<a class="button" href="<?php echo esc_url( $previous_url ); ?>"><?php esc_html_e( 'Previous', 'laqi-unit-stock-manager' ); ?></a>
					<?php endif; ?>
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="laqi-lusm-page-picker">
						<?php foreach ( $query_args as $key => $value ) : ?>
							<input type="hidden" name="<?php echo esc_attr( (string) $key ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" />
						<?php endforeach; ?>
						<label for="laqi-lusm-page-<?php echo esc_attr( $page_arg ); ?>"><?php esc_html_e( 'Page', 'laqi-unit-stock-manager' ); ?></label>
						<select id="laqi-lusm-page-<?php echo esc_attr( $page_arg ); ?>" name="<?php echo esc_attr( $page_arg ); ?>">
							<?php for ( $page_number = 1; $page_number <= $total_pages; ++$page_number ) : ?>
								<option value="<?php echo esc_attr( (string) $page_number ); ?>" <?php selected( $current, $page_number ); ?>><?php echo esc_html( (string) $page_number ); ?></option>
							<?php endfor; ?>
						</select>
						<span><?php echo esc_html( sprintf( /* translators: %d: total page count. */ __( 'of %d', 'laqi-unit-stock-manager' ), $total_pages ) ); ?></span>
						<noscript><button type="submit" class="button"><?php esc_html_e( 'Go', 'laqi-unit-stock-manager' ); ?></button></noscript>
					</form>
					<?php if ( '' !== $next_url ) : ?>
						<a class="button" href="<?php echo esc_url( $next_url ); ?>"><?php esc_html_e( 'Next', 'laqi-unit-stock-manager' ); ?></a>
					<?php endif; ?>
				</nav>
			<?php endif; ?>
		</div>
		<?php
	}
}
