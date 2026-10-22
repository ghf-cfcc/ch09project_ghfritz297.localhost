<?php

namespace EverCheckout\Models;

use EverCheckout\B8\Models\Relations\HasMany;
use EverCheckout\B8\Models\Utilities\DateTime;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Product model.
 *
 * @since   1.0.0
 * @package EverCheckout\Models
 *
 * @property int                  $id                   Product ID.
 * @property string               $name                 Product name.
 * @property string               $type                 Product type.
 * @property string               $status               Product status.
 * @property string               $description          Product description.
 * @property int                  $image_id             Media library attachment ID.
 * @property int                  $price                Price in minor units.
 * @property int                  $download_limit       Download limit, 0 for the store default.
 * @property int                  $download_expiry_days Download expiry in days, 0 for the store default.
 * @property DateTime|string|null $created_at           Date created.
 * @property DateTime|string|null $updated_at           Date updated.
 */
class Product extends Model {

	/**
	 * The table associated with the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $table = 'ecout_products';

	/**
	 * Object cache group for the model.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected $cache_group = 'ecout_products';

	/**
	 * The table columns of the model.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $columns = array(
		'id',
		'name',
		'type',
		'status',
		'description',
		'image_id',
		'price',
		'download_limit',
		'download_expiry_days',
	);

	/**
	 * The model's attributes.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected $attributes = array(
		'type'                 => 'simple',
		'status'               => 'active',
		'image_id'             => 0,
		'price'                => 0,
		'download_limit'       => 0,
		'download_expiry_days' => 0,
	);

	/**
	 * The attributes that should be cast.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $casts = array(
		'name'                 => 'string',
		'type'                 => 'string',
		'status'               => 'string',
		'description'          => 'html',
		'image_id'             => 'int',
		'price'                => 'int',
		'download_limit'       => 'int',
		'download_expiry_days' => 'int',
	);

	/**
	 * Validation rules.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected $rules = array(
		'name'  => 'required|string|max:255',
		'price' => 'required|int|min:0',
	);

	/**
	 * The attributes that are searchable.
	 *
	 * @since 1.0.0
	 * @var array<int, string>
	 */
	protected $searchable = array(
		'name',
	);

	/**
	 * Get the product statuses.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Status labels keyed by status.
	 */
	public static function get_statuses(): array {
		return ecout()->apply_filters(
			'product_statuses',
			array(
				'active' => __( 'Active', 'ever-checkout' ),
				'draft'  => __( 'Draft', 'ever-checkout' ),
			)
		);
	}

	/**
	 * Get the product types.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Type labels keyed by type.
	 */
	public static function get_types(): array {
		return ecout()->apply_filters(
			'product_types',
			array(
				'simple' => __( 'Simple', 'ever-checkout' ),
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

	/**
	 * Get the files attached to this product.
	 *
	 * @since 1.0.0
	 * @return HasMany Download relation.
	 */
	public function downloads(): HasMany {
		return $this->has_many( Download::class, 'product_id' );
	}

	/**
	 * Delete the product with its downloads.
	 *
	 * @since 1.0.0
	 * @return true|WP_Error True on success, WP_Error on failure.
	 */
	public function delete() {
		$result = parent::delete();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$downloads = ecout()->download->query()->where( 'product_id', '=', $this->id )->limit( 0 )->get();

		foreach ( (array) $downloads as $download ) {
			$download->delete();
		}

		return $result;
	}

	/**
	 * Get how many times each file may be downloaded per order.
	 *
	 * @since 1.0.0
	 * @return int Times, 0 for unlimited.
	 */
	public function get_download_limit(): int {
		return (int) $this->download_limit > 0 ? (int) $this->download_limit : (int) ecout()->options->get( 'download_limit', 0 );
	}

	/**
	 * Get how many days after purchase the files stay downloadable.
	 *
	 * @since 1.0.0
	 * @return int Days, 0 for no expiry.
	 */
	public function get_download_expiry_days(): int {
		return (int) $this->download_expiry_days > 0 ? (int) $this->download_expiry_days : (int) ecout()->options->get( 'download_expiry_days', 0 );
	}
}
