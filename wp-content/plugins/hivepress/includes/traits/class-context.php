<?php
/**
 * Context.
 *
 * @package HivePress\Traits
 */

namespace HivePress\Traits;

use HivePress\Helpers as hp;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Context trait.
 *
 * @trait Context
 */
trait Context {

	/**
	 * Context values.
	 *
	 * @var array
	 */
	protected $context = [];

	/**
	 * Gets context values.
	 *
	 * @param string $name Context name.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	final public function get_context( $name = null, $default = null ) {
		return $name ? hp\get_array_value( $this->context, $name, $default ) : $this->context;
	}
}
