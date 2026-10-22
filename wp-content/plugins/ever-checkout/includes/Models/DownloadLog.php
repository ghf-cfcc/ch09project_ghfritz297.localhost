<?php

namespace EverCheckout\Models;

use EverCheckout\B8\Models\Relations\BelongsTo;
use EverCheckout\B8\Models\Utilities\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Download log model.
 *
 * One file served to one order. The download limit is a count over these rows.
 *
 * @since   1.0.0
 * @package EverCheckout\Models
 *
 * @property int                  $id          Log ID.
 * @property int                  $order_id    Order ID.
 * @property int                  $product_id  Product ID.
 * @property int                  $download_id Download ID.
 * @property int                  $customer_id Customer ID.
 * @property string               $ip_address  Address the file was served to.
 * @property string               $user_agent  Browser the file was served to.
 * @property DateTime|string|null $created_at  Date served.
 */
class DownloadLog extends Model {

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'ecout_download_logs';

	/**
	 * Object cache group for the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $cache_group = 'ecout_download_logs';

	/**
	 * The table columns of the model.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $columns = array(
		'id',
		'order_id',
		'product_id',
		'download_id',
		'customer_id',
		'ip_address',
		'user_agent',
		'created_at',
	);

	/**
	 * The model's attributes.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected $attributes = array(
		'order_id'    => 0,
		'product_id'  => 0,
		'download_id' => 0,
		'customer_id' => 0,
		'ip_address'  => '',
		'user_agent'  => '',
	);

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = array(
		'order_id'    => 'int',
		'product_id'  => 'int',
		'download_id' => 'int',
		'customer_id' => 'int',
		'ip_address'  => 'string',
		'user_agent'  => 'string',
		'created_at'  => 'datetime',
	);

	/**
	 * Validation rules.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $rules = array(
		'order_id'    => 'required|int|min:1',
		'download_id' => 'required|int|min:1',
	);

	/**
	 * Whether the model is timestamped; a log row has no `updated_at` to keep.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	protected $timestamps = false;

	/**
	 * Get the order.
	 *
	 * @since 1.0.0
	 * @return BelongsTo Order relation.
	 */
	public function order(): BelongsTo {
		return $this->belongs_to( Order::class, 'order_id' );
	}

	/**
	 * Get the product, when it still exists.
	 *
	 * @since 1.0.0
	 * @return BelongsTo Product relation.
	 */
	public function product(): BelongsTo {
		return $this->belongs_to( Product::class, 'product_id' );
	}

	/**
	 * Get the file, when it still exists.
	 *
	 * @since 1.0.0
	 * @return BelongsTo Download relation.
	 */
	public function download(): BelongsTo {
		return $this->belongs_to( Download::class, 'download_id' );
	}

	/**
	 * Get the customer, when it still exists.
	 *
	 * @since 1.0.0
	 * @return BelongsTo Customer relation.
	 */
	public function customer(): BelongsTo {
		return $this->belongs_to( Customer::class, 'customer_id' );
	}

	/**
	 * Save the log, stamping the time served.
	 *
	 * @since 1.0.0
	 * @return \WP_Error|static WP_Error on failure, or the object on success.
	 */
	public function save() {
		if ( ! $this->exists() && empty( $this->created_at ) ) {
			$this->created_at = current_time( 'mysql', true );
		}

		return parent::save();
	}
}
