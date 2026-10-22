<?php

namespace EverCheckout\Models;

use EverCheckout\B8\Models\Relations\BelongsTo;
use EverCheckout\B8\Models\Utilities\DateTime;

defined( 'ABSPATH' ) || exit;

/**
 * Order item model.
 *
 * One thing sold on an order, with its name and price snapshotted at purchase so
 * editing or deleting the product never rewrites what the order says was sold.
 *
 * @since   1.0.0
 * @package EverCheckout\Models
 *
 * @property int                  $id           Item ID.
 * @property int                  $order_id     Order ID.
 * @property int                  $product_id   Product ID.
 * @property string               $product_name Product name at purchase.
 * @property int                  $price        Unit price at purchase, minor units.
 * @property int                  $quantity     Quantity bought.
 * @property int                  $subtotal     Price times quantity, minor units.
 * @property int                  $total        Subtotal after item-level adjustments, minor units.
 * @property int                  $position     Sort order within the order.
 * @property DateTime|string|null $created_at   Date created.
 * @property DateTime|string|null $updated_at   Date updated.
 */
class OrderItem extends Model {

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'ecout_order_items';

	/**
	 * Object cache group for the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $cache_group = 'ecout_order_items';

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
		'product_name',
		'price',
		'quantity',
		'subtotal',
		'total',
		'position',
	);

	/**
	 * The model's attributes.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected $attributes = array(
		'order_id'   => 0,
		'product_id' => 0,
		'price'      => 0,
		'quantity'   => 1,
		'subtotal'   => 0,
		'total'      => 0,
		'position'   => 0,
	);

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = array(
		'order_id'     => 'int',
		'product_id'   => 'int',
		'product_name' => 'string',
		'price'        => 'int',
		'quantity'     => 'int',
		'subtotal'     => 'int',
		'total'        => 'int',
		'position'     => 'int',
	);

	/**
	 * Validation rules.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $rules = array(
		'order_id'     => 'required|int|min:1',
		'product_id'   => 'required|int|min:1',
		'product_name' => 'required|string|max:255',
		'quantity'     => 'int|min:1',
	);

	/**
	 * The attributes that are searchable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $searchable = array(
		'product_name',
	);

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
	 * Save the item, deriving the amounts from the unit price.
	 *
	 * @since 1.0.0
	 * @return \WP_Error|static WP_Error on failure, or the object on success.
	 */
	public function save() {
		$this->subtotal = (int) $this->price * max( 1, (int) $this->quantity );

		if ( empty( $this->total ) ) {
			$this->total = $this->subtotal;
		}

		return parent::save();
	}
}
