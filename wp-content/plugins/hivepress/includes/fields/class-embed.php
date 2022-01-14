<?php
/**
 * Embed field.
 *
 * @package HivePress\Fields
 */

namespace HivePress\Fields;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Embed field class.
 *
 * @class Embed
 */
class Embed extends URL {

	/**
	 * Class initializer.
	 *
	 * @param array $meta Field meta.
	 */
	public static function init( $meta = [] ) {
		$meta = hp\merge_arrays(
			[
				'label' => esc_html_x( 'Embed', 'field', 'hivepress' ),
			],
			$meta
		);

		parent::init( $meta );
	}

	/**
	 * Class constructor.
	 *
	 * @param array $args Field arguments.
	 */
	public function __construct( $args = [] ) {
		$args = hp\merge_arrays(
			$args,
			[
				'display_type' => 'url',
			]
		);

		parent::__construct( $args );
	}

	/**
	 * Gets field display value.
	 *
	 * @return mixed
	 */
	public function get_display_value() {
		if ( ! is_null( $this->value ) ) {
			$embed = wp_oembed_get( $this->value );

			if ( $embed ) {
				return $embed;
			}
		}
	}

	/**
	 * Sets display template.
	 *
	 * @param string $display_template Display template.
	 */
	protected function set_display_template( $display_template ) {
		Field::set_display_template( $display_template );
	}
}
