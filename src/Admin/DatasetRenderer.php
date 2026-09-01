<?php
/**
 * Shared admin dataset chrome.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the filter bar, empty state, and page links around an admin table.
 *
 * Free and registered Pro tabs share this so every growing table behaves the
 * same way: twenty-five rows, filters that survive paging, and an empty state
 * that says which filters are hiding the rows.
 */
final class DatasetRenderer {

	/**
	 * Shared page control.
	 *
	 * @var PaginationRenderer
	 */
	private $pagination;

	/**
	 * Constructor.
	 *
	 * @param PaginationRenderer $pagination Shared page control.
	 */
	public function __construct( PaginationRenderer $pagination ) {
		$this->pagination = $pagination;
	}

	/**
	 * Render a dataset's filter form.
	 *
	 * @param DatasetView   $view    Dataset URL state.
	 * @param string        $legend  Localized fieldset legend.
	 * @param callable|null $actions Optional extra controls, rendered beside
	 *                               Filter so an add-on's action sits with the
	 *                               button it relates to rather than adrift
	 *                               above the table.
	 * @return void
	 */
	public function filters( DatasetView $view, string $legend, ?callable $actions = null ): void {
		$fields = $view->filters()->fields();
		$hidden = $view->link_args();
		foreach ( $fields as $field ) {
			unset( $hidden[ $field['arg'] ] );
		}
		?>
		<form class="laqi-lusm-dataset-filters" method="get" action="<?php echo esc_url( $view->form_action() ); ?>">
			<?php foreach ( $hidden as $name => $value ) : ?>
				<input type="hidden" name="<?php echo esc_attr( (string) $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" />
			<?php endforeach; ?>
			<fieldset>
				<legend class="screen-reader-text"><?php echo esc_html( $legend ); ?></legend>
				<?php foreach ( $fields as $field ) : ?>
					<div class="laqi-lusm-dataset-filter<?php echo 'search' === $field['control'] ? ' laqi-lusm-dataset-filter--search' : ''; ?>">
						<label for="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
						<?php $this->control( $field ); ?>
					</div>
				<?php endforeach; ?>
				<div class="laqi-lusm-dataset-filter-actions">
					<?php submit_button( __( 'Filter', 'laqi-unit-stock-manager' ), 'secondary', '', false ); ?>
					<?php if ( $view->filters()->is_active() ) : ?>
						<a class="button-link" href="<?php echo esc_url( $view->reset_url() ); ?>"><?php esc_html_e( 'Clear filters', 'laqi-unit-stock-manager' ); ?></a>
					<?php endif; ?>
					<?php
					if ( null !== $actions ) {
						$actions(); }
					?>
				</div>
			</fieldset>
		</form>
		<?php
	}

	/**
	 * Render a dataset's empty or no-match state.
	 *
	 * @param DatasetView $view    Dataset URL state.
	 * @param string      $message Message for an unfiltered empty dataset.
	 * @return void
	 */
	public function empty_state( DatasetView $view, string $message ): void {
		$labels = $view->filters()->active_labels();
		?>
		<div class="laqi-lusm-dataset-empty">
			<?php if ( array() === $labels ) : ?>
				<p><?php echo esc_html( $message ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Nothing matches the filters you have applied.', 'laqi-unit-stock-manager' ); ?></p>
				<ul class="laqi-lusm-dataset-active-filters">
					<?php foreach ( $labels as $label ) : ?>
						<li><?php echo esc_html( $label ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p><a class="button button-secondary" href="<?php echo esc_url( $view->reset_url() ); ?>"><?php esc_html_e( 'Clear filters', 'laqi-unit-stock-manager' ); ?></a></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render a dataset's result summary and page links.
	 *
	 * @param DatasetView $view       Dataset URL state.
	 * @param string      $summary    Localized result summary.
	 * @param string      $aria_label Localized navigation label.
	 * @return void
	 */
	public function pagination( DatasetView $view, string $summary, string $aria_label ): void {
		$link_args = $view->link_args();
		unset( $link_args['post_type'] );
		$this->pagination->render( $summary, $aria_label, $view->page_arg(), $link_args, $view->page()->number(), $view->page()->total_pages() );
	}

	/**
	 * Render one filter control.
	 *
	 * @param array<string, mixed> $field Render-ready field descriptor.
	 * @return void
	 */
	private function control( array $field ): void {
		if ( 'select' === $field['control'] ) {
			?>
			<select id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['arg'] ); ?>">
				<?php foreach ( $field['choices'] as $choice ) : ?>
					<option value="<?php echo esc_attr( $choice['value'] ); ?>" <?php selected( $field['value'], $choice['value'] ); ?>><?php echo esc_html( $choice['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php
			return;
		}
		if ( 'pool' === $field['control'] ) {
			?>
			<select id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['arg'] ); ?>" class="laqi-lusm-pool-search" data-placeholder="<?php esc_attr_e( 'Search inventory pools', 'laqi-unit-stock-manager' ); ?>">
				<option value=""><?php esc_html_e( 'All inventory pools', 'laqi-unit-stock-manager' ); ?></option>
				<?php if ( '' !== $field['value'] && '0' !== $field['value'] ) : ?>
					<option value="<?php echo esc_attr( $field['value'] ); ?>" selected><?php echo esc_html( $field['value_label'] ); ?></option>
				<?php endif; ?>
			</select>
			<?php
			return;
		}
		if ( 'date' === $field['control'] ) {
			?>
			<input type="date" id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['arg'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" />
			<?php
			return;
		}
		?>
		<input type="search" id="<?php echo esc_attr( $field['id'] ); ?>" name="<?php echo esc_attr( $field['arg'] ); ?>" value="<?php echo esc_attr( $field['value'] ); ?>" maxlength="191" placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>" />
		<?php
	}
}
