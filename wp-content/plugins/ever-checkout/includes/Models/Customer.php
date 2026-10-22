<?php

namespace EverCheckout\Models;

use EverCheckout\B8\Models\Relations\HasMany;
use EverCheckout\B8\Models\Utilities\DateTime;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Customer model.
 *
 * @since   1.0.0
 * @package EverCheckout\Models
 *
 * @property int                  $id         Customer ID.
 * @property int                  $user_id    WordPress user ID, 0 when there is none.
 * @property string               $email      Email address.
 * @property string               $name       Customer name.
 * @property string               $country    Country code.
 * @property DateTime|string|null $created_at Date created.
 * @property DateTime|string|null $updated_at Date updated.
 */
class Customer extends Model {

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'ecout_customers';

	/**
	 * Object cache group for the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $cache_group = 'ecout_customers';

	/**
	 * The table columns of the model.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $columns = array(
		'id',
		'user_id',
		'email',
		'name',
		'country',
	);

	/**
	 * The model's attributes.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected $attributes = array(
		'user_id' => 0,
	);

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = array(
		'user_id' => 'int',
		'email'   => 'email',
		'name'    => 'string',
		'country' => 'upper',
	);

	/**
	 * Validation rules.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $rules = array(
		'email'   => 'required|email|max:100|unique:email',
		'name'    => 'string|max:255',
		'country' => 'string|max:2',
	);

	/**
	 * The attributes that are searchable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $searchable = array(
		'name',
		'email',
	);

	/**
	 * Get the orders placed by this customer.
	 *
	 * @since 1.0.0
	 * @return HasMany Order relation.
	 */
	public function orders(): HasMany {
		return $this->has_many( Order::class, 'customer_id' );
	}

	/**
	 * Get the files served to this customer.
	 *
	 * @since 1.0.0
	 * @return HasMany DownloadLog relation.
	 */
	public function logs(): HasMany {
		return $this->has_many( DownloadLog::class, 'customer_id' );
	}

	/*
	|--------------------------------------------------------------------------
	| CRUD Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Delete the customer, leaving their orders and download logs as records with no owner.
	 *
	 * @since 1.0.0
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		global $wpdb;

		$result = parent::delete();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		foreach ( array( ecout()->order, ecout()->download_log ) as $model ) {
			$wpdb->update( $wpdb->prefix . $model->get_table(), array( 'customer_id' => 0 ), array( 'customer_id' => (int) $this->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk write to the plugin's own table; its model cache is flushed on the next line.
			$model->flush_cache();
		}

		return $result;
	}
}
