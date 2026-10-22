<?php

namespace EverCheckout\Models;

use EverCheckout\B8\Models\Relations\BelongsTo;
use EverCheckout\B8\Models\Relations\HasMany;
use EverCheckout\B8\Models\Utilities\DateTime;
use EverCheckout\Plugin;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Order model.
 *
 * @since   1.0.0
 * @package EverCheckout\Models
 *
 * @property int                  $id             Order ID, and the order number.
 * @property string               $order_key      Unguessable public handle for the receipt link.
 * @property int                  $parent_id      Parent order ID, set on a refund.
 * @property string               $type           Order type.
 * @property string               $status         Order status.
 * @property int                  $customer_id    Customer ID, 0 until the payment completes.
 * @property string               $email          Customer email, empty until the payment completes.
 * @property string               $name           Buyer's name as typed at checkout.
 * @property string               $currency       Currency code.
 * @property string               $mode           Whether live or test keys took the payment.
 * @property int                  $subtotal       Subtotal in minor units.
 * @property int                  $discount       Discount in minor units.
 * @property int                  $total          Total in minor units.
 * @property string               $discount_code  Promotion code applied at checkout.
 * @property string               $ip_address     Customer IP address.
 * @property string               $gateway        Gateway that took the payment, empty on a free order.
 * @property string               $transaction_id The gateway's own id for the payment, or the refund on a refund row.
 * @property string               $payment_method What the buyer paid with, as the gateway reports it.
 * @property DateTime|string|null $created_at     Date created.
 * @property DateTime|string|null $updated_at     Date updated.
 * @property DateTime|string|null $paid_at        Date the gateway confirmed the money, null until then.
 * @property DateTime|string|null $completed_at   Date completed, written once.
 */
class Order extends Model {

	/**
	 * The only status changes the machine allows.
	 *
	 * @since 1.0.0
	 * @var array<string, array<int, string>>
	 */
	const TRANSITIONS = array(
		'pending'            => array( 'completed', 'failed', 'cancelled' ),
		'completed'          => array( 'partially_refunded', 'refunded' ),
		'partially_refunded' => array( 'refunded' ),
		'refunded'           => array(),
		'failed'             => array(),
		'cancelled'          => array( 'completed' ),
	);

	/**
	 * Statuses that count as money taken.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	const PAID_STATUSES = array( 'completed', 'partially_refunded', 'refunded' );

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'ecout_orders';

	/**
	 * Object cache group for the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $cache_group = 'ecout_orders';

	/**
	 * Attributes that fire transition hooks when they change.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $transitionable = array( 'status' );

	/**
	 * The table columns of the model.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $columns = array(
		'id',
		'order_key',
		'parent_id',
		'type',
		'status',
		'customer_id',
		'email',
		'name',
		'currency',
		'mode',
		'subtotal',
		'discount',
		'total',
		'discount_code',
		'ip_address',
		'gateway',
		'transaction_id',
		'payment_method',
		'paid_at',
		'completed_at',
	);

	/**
	 * The model's attributes.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected $attributes = array(
		'type'        => 'sale',
		'status'      => 'pending',
		'mode'        => 'live',
		'parent_id'   => 0,
		'customer_id' => 0,
		'subtotal'    => 0,
		'discount'    => 0,
		'total'       => 0,
	);

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = array(
		'order_key'      => 'string',
		'parent_id'      => 'int',
		'type'           => 'string',
		'status'         => 'string',
		'customer_id'    => 'int',
		'email'          => 'email',
		'name'           => 'string',
		'currency'       => 'upper',
		'mode'           => 'string',
		'subtotal'       => 'int',
		'discount'       => 'int',
		'total'          => 'int',
		'discount_code'  => 'string',
		'ip_address'     => 'string',
		'gateway'        => 'string',
		'transaction_id' => 'string',
		'payment_method' => 'string',
		'paid_at'        => 'datetime',
		'completed_at'   => 'datetime',
	);

	/**
	 * Validation rules.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $rules = array(
		'currency' => 'required|string|max:3',
	);

	/**
	 * The attributes that are searchable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $searchable = array(
		'id',
		'email',
		'order_key',
		'transaction_id',
	);

	/**
	 * Get the order statuses.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Status labels keyed by status.
	 */
	public static function get_statuses(): array {
		return ecout()->apply_filters(
			'order_statuses',
			array(
				'pending'            => __( 'Pending', 'ever-checkout' ),
				'completed'          => __( 'Completed', 'ever-checkout' ),
				'partially_refunded' => __( 'Partially refunded', 'ever-checkout' ),
				'refunded'           => __( 'Refunded', 'ever-checkout' ),
				'failed'             => __( 'Failed', 'ever-checkout' ),
				'cancelled'          => __( 'Cancelled', 'ever-checkout' ),
			)
		);
	}

	/**
	 * Get the order types.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Type labels keyed by type.
	 */
	public static function get_types(): array {
		return ecout()->apply_filters(
			'order_types',
			array(
				'sale'   => __( 'Sale', 'ever-checkout' ),
				'refund' => __( 'Refund', 'ever-checkout' ),
			)
		);
	}

	/**
	 * Bootstrap the model.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function bootstrap(): void {
		parent::bootstrap();

		$this->set_rules(
			array(
				'status' => 'required|in:' . implode( ',', array_keys( static::get_statuses() ) ),
				'type'   => 'required|in:' . implode( ',', array_keys( static::get_types() ) ),
			)
		);
	}

	/*
	|--------------------------------------------------------------------------
	| Accessors & Mutators
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get the names of the things sold, joined for a subject line or a receipt.
	 *
	 * @since 1.0.0
	 * @return string Product names separated by commas.
	 */
	public function get_product_names(): string {
		$names = array();

		foreach ( (array) $this->get_relation_value( 'items' ) as $item ) {
			$names[] = (string) $item->get( 'product_name' );
		}

		return implode( ', ', $names );
	}

	/**
	 * Whether the files bought on this order may be downloaded.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function can_download(): bool {
		return in_array( (string) $this->status, array( 'completed', 'partially_refunded' ), true );
	}

	/*
	|--------------------------------------------------------------------------
	| Relationships
	|--------------------------------------------------------------------------
	*/

	/**
	 * Get the things sold on this order.
	 *
	 * @since 1.0.0
	 * @return HasMany OrderItem relation.
	 */
	public function items(): HasMany {
		return $this->has_many( OrderItem::class, 'order_id' );
	}

	/**
	 * Get the customer.
	 *
	 * @since 1.0.0
	 * @return BelongsTo Customer relation.
	 */
	public function customer(): BelongsTo {
		return $this->belongs_to( Customer::class, 'customer_id' );
	}

	/**
	 * Get the files served to this order.
	 *
	 * @since 1.0.0
	 * @return HasMany DownloadLog relation.
	 */
	public function logs(): HasMany {
		return $this->has_many( DownloadLog::class, 'order_id' );
	}

	/**
	 * Get the sale a refund points at.
	 *
	 * @since 1.0.0
	 * @return BelongsTo Order relation.
	 */
	public function parent(): BelongsTo {
		return $this->belongs_to( self::class, 'parent_id' );
	}

	/**
	 * Get the refunds made against this order.
	 *
	 * @since 1.0.0
	 * @return HasMany Order relation.
	 */
	public function refunds(): HasMany {
		return $this->has_many( self::class, 'parent_id' );
	}

	/*
	|--------------------------------------------------------------------------
	| CRUD Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Save the order.
	 *
	 * @since 1.0.0
	 * @return \WP_Error|static WP_Error on failure, or the object on success.
	 */
	public function save() {
		if ( empty( $this->order_key ) ) {
			$this->order_key = static::generate_key();
		}

		if ( 'completed' === $this->status && empty( $this->completed_at ) ) {
			$this->completed_at = current_time( 'mysql', true );
		}

		return parent::save();
	}

	/**
	 * Validate the order, refusing a status change the machine does not allow.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>|null $attributes Attributes to validate, or null for the model's own.
	 * @return WP_Error|null Validation error, or null when valid.
	 */
	public function validate( $attributes = null ): ?WP_Error {
		$error = parent::validate( $attributes );

		if ( $error || ! $this->exists() || ! $this->is_dirty( 'status' ) ) {
			return $error;
		}

		$from = (string) $this->get_original( 'status' );
		$to   = (string) $this->status;

		if ( in_array( $to, self::TRANSITIONS[ $from ] ?? array(), true ) ) {
			return null;
		}

		$message = sprintf(
			/* translators: 1: order id, 2: status now, 3: status wanted. */
			__( 'Order %1$d cannot move from %2$s to %3$s.', 'ever-checkout' ),
			$this->id,
			$from,
			$to
		);

		ecout()->logger->warning( $message );

		return new WP_Error( 'ecout_illegal_transition', $message, array( 'status' => 400 ) );
	}

	/**
	 * Delete the order with its items, download logs and refunds.
	 *
	 * @since 1.0.0
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		global $wpdb;

		$refunds = (array) $this->refunds()->get();
		$result  = parent::delete();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		foreach ( array( ecout()->order_item, ecout()->download_log ) as $model ) {
			$wpdb->delete( $wpdb->prefix . $model->get_table(), array( 'order_id' => (int) $this->id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk write to the plugin's own table; its model cache is flushed on the next line.
			$model->flush_cache();
		}

		foreach ( $refunds as $refund ) {
			$refund->delete();
		}

		return $result;
	}

	/*
	|--------------------------------------------------------------------------
	| Helper Methods
	|--------------------------------------------------------------------------
	*/

	/**
	 * Generate an unguessable key.
	 *
	 * @since 1.0.0
	 * @return string 32 hex characters from a CSPRNG.
	 */
	public static function generate_key(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * Get the files each item entitles the buyer to, read live from the products.
	 *
	 * @since 1.0.0
	 * @return array<int, array{item: OrderItem, product: Product|null, files: Download[]}> One entry per item.
	 */
	public function get_downloads(): array {
		$items = (array) ecout()->order_item->query()
			->with( 'product' )
			->where( 'order_id', '=', (int) $this->id )
			->order_by( 'position', 'ASC' )
			->limit( 0 )
			->get();
		$ids   = array_filter( array_map( static fn( $item ) => (int) $item->get( 'product_id' ), $items ) );
		$files = array();

		if ( ! empty( $ids ) ) {
			$found = ecout()->download->query()
				->with( 'product' )
				->where( 'product_id', 'IN', array_values( array_unique( $ids ) ) )
				->where( 'enabled', '=', 1 )
				->order_by( 'position', 'ASC' )
				->limit( 0 )
				->get();

			foreach ( (array) $found as $file ) {
				$files[ (int) $file->get( 'product_id' ) ][] = $file;
			}
		}

		$downloads = array();

		foreach ( $items as $item ) {
			/**
			 * Filters the files an order item entitles the buyer to.
			 *
			 * @since 1.0.0
			 *
			 * @param Download[] $files Enabled downloads of the item's product.
			 * @param OrderItem  $item  Item that was bought.
			 * @param Order      $order Order the item belongs to.
			 */
			$entitled = (array) ecout()->apply_filters( 'order_item_downloads', $files[ (int) $item->get( 'product_id' ) ] ?? array(), $item, $this );

			$downloads[] = array(
				'item'    => $item,
				'product' => $item->get_relation_value( 'product' ),
				'files'   => $entitled,
			);
		}

		return $downloads;
	}

	/**
	 * Get the item that entitles the buyer to a file.
	 *
	 * @since 1.0.0
	 * @param Download $download File being asked for.
	 * @return OrderItem|null Item, or null when nothing on the order covers the file.
	 */
	public function get_download_item( Download $download ): ?OrderItem {
		foreach ( $this->get_downloads() as $entry ) {
			foreach ( $entry['files'] as $file ) {
				if ( (int) $file->id === (int) $download->id ) {
					return $entry['item'];
				}
			}
		}

		return null;
	}

	/**
	 * Get the download URL for a file.
	 *
	 * The order key is the credential and the file id follows it in the same value;
	 * entitlement is checked when the file is served, not here.
	 *
	 * @since 1.0.0
	 * @param Download $download File the link serves.
	 * @return string Download URL.
	 */
	public function get_download_url( Download $download ): string {
		$url = add_query_arg( Plugin::QUERY_VARS['download'], rawurlencode( $this->order_key . (int) $download->id ), home_url( '/' ) );

		return (string) ecout()->apply_filters( 'download_url', $url, $this, $download );
	}

	/**
	 * Get when the files of a product stop being downloadable.
	 *
	 * @since 1.0.0
	 * @param Product|null $product Product bought, or null when it no longer exists.
	 * @return string|null MySQL datetime in UTC, or null for never.
	 */
	public function get_download_expiry( ?Product $product ): ?string {
		$days = $product ? $product->get_download_expiry_days() : (int) ecout()->options->get( 'download_expiry_days', 0 );
		$from = (string) $this->completed_at;

		if ( $days <= 0 || '' === $from ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', (int) strtotime( $from ) + $days * DAY_IN_SECONDS );
	}

	/**
	 * Get how many times a file has been served to this order.
	 *
	 * @since 1.0.0
	 * @param Download $download File to count.
	 * @return int Times served.
	 */
	public function get_download_count( Download $download ): int {
		return (int) ecout()->download_log->count(
			array(
				'order_id'    => (int) $this->id,
				'download_id' => (int) $download->id,
			)
		);
	}
}
