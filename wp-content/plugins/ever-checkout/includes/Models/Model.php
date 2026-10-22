<?php

namespace EverCheckout\Models;

defined( 'ABSPATH' ) || exit;

/**
 * Base model class.
 *
 * @since   1.0.0
 * @package EverCheckout\Models
 *
 * @property int $id Primary key.
 */
abstract class Model extends \EverCheckout\B8\Models\Model {

	/**
	 * Hook prefix for the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $hook_prefix = 'ecout';

	/**
	 * Indicates if the model should be timestamped.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	protected $timestamps = true;
}
