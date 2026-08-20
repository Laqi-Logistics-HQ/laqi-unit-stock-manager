<?php
/**
 * Shared Pro admin dataset filtering.
 *
 * @package LaqiUnitStockManager
 */

namespace LaqiUnitStockManager\Admin;

defined( 'ABSPATH' ) || exit;

use DateTimeImmutable;

/**
 * Reads, sanitizes, and describes the categorical filters behind one admin table.
 *
 * Quantity values are deliberately not filterable: pools can use different unit
 * families, so comparing displayed quantities across pools is meaningless.
 */
final class DatasetFilters {
	/**
	 * Field definitions keyed by query argument.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $spec;

	/**
	 * Sanitized values keyed by query argument.
	 *
	 * @var array<string, mixed>
	 */
	private $values;

	/**
	 * Human labels for values the spec cannot describe by itself.
	 *
	 * @var array<string, string>
	 */
	private $labels = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, array<string, mixed>> $spec   Field definitions.
	 * @param array<string, mixed>                $values Sanitized values.
	 */
	public function __construct( array $spec, array $values ) {
		$this->spec   = $spec;
		$this->values = $values;
	}

	/**
	 * Read and sanitize every declared field from the query string.
	 *
	 * @param array<string, array<string, mixed>> $spec Field definitions.
	 * @return self
	 */
	public static function read( array $spec ): self {
		$values = array();
		foreach ( $spec as $arg => $field ) {
			$control = (string) ( $field['control'] ?? 'search' );
			$default = $field['default'] ?? ( 'pool' === $control ? 0 : '' );
			if ( 'pool' === $control ) {
				$values[ $arg ] = isset( $_GET[ $arg ] ) ? absint( $_GET[ $arg ] ) : (int) $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter input.
				continue;
			}
			$raw = isset( $_GET[ $arg ] ) ? sanitize_text_field( wp_unslash( $_GET[ $arg ] ) ) : (string) $default; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter input.
			if ( 'select' === $control ) {
				$values[ $arg ] = isset( $field['choices'][ $raw ] ) ? $raw : (string) $default;
				continue;
			}
			$values[ $arg ] = 'date' === $control ? self::calendar_date( $raw ) : substr( trim( $raw ), 0, 191 );
		}

		return new self( $spec, $values );
	}

	/**
	 * Describe the current value of a field the spec cannot label alone.
	 *
	 * @param string $arg   Query argument.
	 * @param string $label Human label for the current value.
	 * @return void
	 */
	public function describe( string $arg, string $label ): void {
		$this->labels[ $arg ] = $label;
	}

	/**
	 * Current sanitized value of one field.
	 *
	 * @param string $arg Query argument.
	 * @return mixed
	 */
	public function value( string $arg ) {
		return $this->values[ $arg ] ?? '';
	}

	/**
	 * Filters to hand a repository, keyed by the repository's own filter names.
	 *
	 * @return array<string, mixed>
	 */
	public function to_query(): array {
		$query = array();
		foreach ( $this->values as $arg => $value ) {
			if ( ! $this->has_value( (string) $arg ) ) {
				continue;
			}
			$query[ (string) ( $this->spec[ $arg ]['filter'] ?? $arg ) ] = $value;
		}

		return $query;
	}

	/**
	 * Non-default values to preserve in pagination links and forms.
	 *
	 * @return array<string, string>
	 */
	public function query_args(): array {
		$args = array();
		foreach ( $this->values as $arg => $value ) {
			if ( $this->differs_from_default( (string) $arg ) ) {
				$args[ $arg ] = (string) $value;
			}
		}

		return $args;
	}

	/** Whether any field narrows the dataset. @return bool */
	public function is_active(): bool {
		return array() !== $this->query_args();
	}

	/**
	 * Localized "Label: value" descriptions of every active filter.
	 *
	 * @return string[]
	 */
	public function active_labels(): array {
		$labels = array();
		foreach ( array_keys( $this->query_args() ) as $arg ) {
			$labels[] = sprintf(
				/* translators: 1: filter name, 2: filter value. */
				__( '%1$s: %2$s', 'laqi-unit-stock-manager' ),
				(string) ( $this->spec[ $arg ]['label'] ?? $arg ),
				$this->described_value( $arg )
			);
		}

		return $labels;
	}

	/**
	 * Render-ready field descriptors for the shared filter template.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function fields(): array {
		$fields = array();
		foreach ( $this->spec as $arg => $field ) {
			$choices = array();
			foreach ( (array) ( $field['choices'] ?? array() ) as $choice_value => $choice_label ) {
				$choices[] = array(
					'value' => (string) $choice_value,
					'label' => (string) $choice_label,
				);
			}
			$fields[] = array(
				'arg'         => (string) $arg,
				'control'     => (string) ( $field['control'] ?? 'search' ),
				'id'          => 'laqi-lusm-filter-' . str_replace( '_', '-', (string) $arg ),
				'label'       => (string) ( $field['label'] ?? $arg ),
				'placeholder' => (string) ( $field['placeholder'] ?? '' ),
				'value'       => (string) $this->values[ $arg ],
				'value_label' => $this->described_value( (string) $arg ),
				'choices'     => $choices,
			);
		}

		return $fields;
	}

	/**
	 * Whether a field carries a value the repository should apply.
	 *
	 * @param string $arg Query argument.
	 * @return bool
	 */
	private function has_value( string $arg ): bool {
		$value = $this->values[ $arg ] ?? '';

		return '' !== (string) $value && 0 !== $value;
	}

	/**
	 * Whether a field was chosen rather than left at its default.
	 *
	 * Values that differ from the default are preserved in links even when they
	 * are empty, so that clearing a defaulted filter survives pagination.
	 *
	 * @param string $arg Query argument.
	 * @return bool
	 */
	private function differs_from_default( string $arg ): bool {
		return (string) ( $this->values[ $arg ] ?? '' ) !== (string) $this->default_for( $arg );
	}

	/**
	 * Declared default for one field.
	 *
	 * @param string $arg Query argument.
	 * @return mixed
	 */
	private function default_for( string $arg ) {
		return $this->spec[ $arg ]['default'] ?? ( 'pool' === ( $this->spec[ $arg ]['control'] ?? '' ) ? 0 : '' );
	}

	/**
	 * Human description of one field's current value.
	 *
	 * @param string $arg Query argument.
	 * @return string
	 */
	private function described_value( string $arg ): string {
		if ( isset( $this->labels[ $arg ] ) ) {
			return $this->labels[ $arg ];
		}
		$value = (string) ( $this->values[ $arg ] ?? '' );

		return (string) ( $this->spec[ $arg ]['choices'][ $value ] ?? $value );
	}

	/**
	 * Accept only an exact calendar date.
	 *
	 * @param string $date Candidate date.
	 * @return string
	 */
	private static function calendar_date( string $date ): string {
		$parsed = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );

		return false !== $parsed && $parsed->format( 'Y-m-d' ) === $date ? $date : '';
	}
}
