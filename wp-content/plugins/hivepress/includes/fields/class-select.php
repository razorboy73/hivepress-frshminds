<?php
/**
 * Select field.
 *
 * @package HivePress\Fields
 */

namespace HivePress\Fields;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Select field class.
 *
 * @class Select
 */
class Select extends Field {

	/**
	 * Field placeholder.
	 *
	 * @var string
	 */
	protected $placeholder;

	/**
	 * Field options.
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * Multiple flag.
	 *
	 * @var bool
	 */
	protected $multiple = false;

	/**
	 * Maximum values.
	 *
	 * @var int
	 */
	protected $max_values;

	/**
	 * Field filter operator.
	 *
	 * @var mixed
	 */
	protected $filter_operator;

	/**
	 * Field options source.
	 *
	 * @var string
	 */
	protected $source;

	/**
	 * Class initializer.
	 *
	 * @param array $meta Field meta.
	 */
	public static function init( $meta = [] ) {
		$meta = hp\merge_arrays(
			[
				'label'      => esc_html_x( 'Select', 'field', 'hivepress' ),
				'filterable' => true,

				'settings'   => [
					'placeholder'     => [
						'label'      => esc_html__( 'Placeholder', 'hivepress' ),
						'type'       => 'text',
						'max_length' => 256,
						'_order'     => 100,
					],

					'multiple'        => [
						'label'   => esc_html_x( 'Multiple', 'selection', 'hivepress' ),
						'caption' => esc_html__( 'Allow multiple selection', 'hivepress' ),
						'type'    => 'checkbox',
						'_order'  => 105,
					],

					// @todo remove prefix from parent.
					'max_values'      => [
						'label'     => esc_html__( 'Maximum Selection', 'hivepress' ),
						'type'      => 'number',
						'min_value' => 1,
						'_context'  => 'edit',
						'_parent'   => 'edit_field_multiple',
						'_order'    => 110,
					],

					'options'         => [
						'label'    => esc_html__( 'Options', 'hivepress' ),
						'type'     => 'select',
						'options'  => [],
						'multiple' => true,
						'_context' => 'edit',
						'_order'   => 120,
					],

					// @todo remove prefix from parent.
					'filter_operator' => [
						'label'    => esc_html__( 'Options', 'hivepress' ),
						'caption'  => esc_html__( 'Search any of the selected options', 'hivepress' ),
						'type'     => 'checkbox',
						'_context' => 'search',
						'_parent'  => 'search_field_multiple',
						'_order'   => 130,
					],
				],
			],
			$meta
		);

		parent::init( $meta );
	}

	/**
	 * Bootstraps field properties.
	 */
	protected function boot() {
		$attributes = [];

		// Normalize options.
		if ( ! is_array( $this->options ) ) {
			$this->options = [];
		}

		// Set placeholder.
		if ( ! $this->multiple ) {
			if ( is_null( $this->placeholder ) ) {
				$this->placeholder = '&mdash;';
			}

			if ( ! isset( $this->options[''] ) ) {
				$this->options = [ '' => $this->placeholder ] + $this->options;
			}
		} elseif ( ! is_null( $this->placeholder ) ) {
			$attributes['data-placeholder'] = $this->placeholder;
		}

		// Set disabled flag.
		if ( $this->disabled ) {
			$attributes['disabled'] = true;
		}

		// Set required flag.
		if ( $this->required ) {
			$attributes['required'] = true;
		}

		// Set source.
		if ( $this->source ) {
			$attributes['data-source'] = esc_url( $this->source );
		}

		// Set component.
		if ( 'hidden' !== $this->display_type ) {
			$attributes['data-component'] = 'select';
		}

		$this->attributes = hp\merge_arrays( $this->attributes, $attributes );

		parent::boot();
	}

	/**
	 * Sets field value.
	 *
	 * @param mixed $value Field value.
	 * @return object
	 */
	final public function set_value( $value ) {
		parent::set_value( $value );

		if ( ! is_null( $this->value ) && $this->source ) {

			// Set field options.
			$this->options = apply_filters( 'hivepress/v1/fields/field/options', $this->options, $this );
		}

		return $this;
	}

	/**
	 * Gets field display value.
	 *
	 * @return mixed
	 */
	public function get_display_value() {
		if ( ! is_null( $this->value ) ) {
			$labels = [];

			foreach ( (array) $this->value as $value ) {
				$label = hp\get_array_value( $this->options, $value );

				if ( is_array( $label ) ) {
					$label = hp\get_array_value( $label, 'label' );
				}

				if ( strlen( $label ) ) {
					$labels[] = $label;
				}
			}

			if ( $labels ) {
				return implode( ', ', $labels );
			}
		}
	}

	/**
	 * Adds field filter.
	 */
	protected function add_filter() {
		parent::add_filter();

		if ( $this->multiple ) {
			if ( $this->filter_operator ) {
				$this->filter['operator'] = 'IN';
			} else {
				$this->filter['operator'] = 'AND';
			}
		} else {
			$this->filter['operator'] = 'IN';
		}
	}

	/**
	 * Normalizes field value.
	 */
	protected function normalize() {
		parent::normalize();

		if ( $this->multiple && ! is_null( $this->value ) ) {
			if ( [] !== $this->value ) {
				$this->value = (array) $this->value;
			} else {
				$this->value = null;
			}
		} elseif ( ! $this->multiple && is_array( $this->value ) ) {
			if ( $this->value ) {
				$this->value = hp\get_first_array_value( $this->value );
			} else {
				$this->value = null;
			}
		}
	}

	/**
	 * Sanitizes field value.
	 */
	protected function sanitize() {
		if ( $this->multiple ) {
			$this->value = array_map(
				function( $value ) {
					return is_numeric( $value ) ? absint( $value ) : sanitize_text_field( $value );
				},
				$this->value
			);
		} else {
			$this->value = is_numeric( $this->value ) ? absint( $this->value ) : sanitize_text_field( $this->value );
		}
	}

	/**
	 * Validates field value.
	 *
	 * @return bool
	 */
	public function validate() {
		if ( parent::validate() && ! is_null( $this->value ) ) {
			if ( count( array_intersect( (array) $this->value, array_keys( $this->options ) ) ) !== count( (array) $this->value ) ) {
				$this->add_errors( sprintf( hivepress()->translator->get_string( 'field_contains_invalid_value' ), $this->label ) );
			}

			if ( $this->multiple && $this->max_values && count( (array) $this->value ) > $this->max_values ) {
				$this->add_errors( sprintf( hivepress()->translator->get_string( 'field_contains_too_many_values' ), $this->label ) );
			}
		}

		return empty( $this->errors );
	}

	/**
	 * Displays field HTML.
	 *
	 * @return string
	 */
	public function display() {
		if ( ! $this->multiple && ! is_null( $this->value ) ) {
			$this->context['parent_value'] = null;

			$value = hp\get_array_value( $this->options, $this->value );

			if ( is_array( $value ) && isset( $value['parent'] ) ) {
				$this->context['parent_value'] = hp\get_array_value( hp\get_array_value( $this->options, $value['parent'] ), 'label' );
			}
		}

		return parent::display();
	}

	/**
	 * Renders field HTML.
	 *
	 * @return string
	 */
	public function render() {
		$output = '<select name="' . esc_attr( $this->name ) . ( $this->multiple ? '[]" multiple' : '"' ) . ' ' . hp\html_attributes( $this->attributes ) . '>';

		// Render options.
		$output .= $this->render_options();

		$output .= '</select>';

		return $output;
	}

	/**
	 * Renders field options.
	 *
	 * @param mixed $current Current value.
	 * @param int   $level Nesting level.
	 * @return string
	 */
	protected function render_options( $current = null, $level = 0 ) {
		$output = '';

		// Filter options.
		$options = array_filter(
			$this->options,
			function( $option ) use ( $current ) {
				$parent = hp\get_array_value( $option, 'parent' );

				return ( is_null( $current ) && is_null( $parent ) ) || ( ! is_null( $current ) && $parent === $current );
			}
		);

		// Render options.
		foreach ( $options as $value => $label ) {

			// Get label.
			if ( is_array( $label ) ) {
				$label = hp\get_array_value( $label, 'label' );
			}

			// Render option.
			$output .= '<option value="' . esc_attr( $value ) . '" data-level=' . esc_attr( $level ) . ' ' . ( in_array( $value, (array) $this->value, true ) ? 'selected' : '' ) . '>' . esc_html( $label ) . '</option>';

			// Render child options.
			$output .= $this->render_options( $value, $level + 1 );
		}

		return $output;
	}
}
