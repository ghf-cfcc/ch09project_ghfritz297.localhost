<?php

namespace EverCheckout\Blocks;

use EverCheckout\B8\Component;
use EverCheckout\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the blocks.
 *
 * @since   1.0.0
 * @package EverCheckout\Blocks
 *
 * @extends Component<Plugin>
 */
class Blocks extends Component {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {
		$this->app->make( BuyButton::class );
		$this->app->make( Receipt::class );

		add_action( 'init', array( $this, 'register_blocks' ) );
		add_filter( 'block_categories_all', array( $this, 'register_categories' ) );
		add_shortcode( 'ecout_buy_button', array( $this, 'buy_button_shortcode' ) );
	}

	/**
	 * Register the blocks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register_blocks(): void {
		$build    = $this->app->plugin_path( $this->app->build_dir . '/' );
		$path     = $build . 'blocks/';
		$manifest = $build . 'blocks-manifest.php';

		if ( ! is_readable( $manifest ) ) {
			return;
		}

		// The collection API is WordPress 6.7; the plugin still supports 6.5.
		if ( function_exists( 'wp_register_block_metadata_collection' ) ) {
			wp_register_block_metadata_collection( $path, $manifest );
		}

		foreach ( array_keys( (array) require $manifest ) as $slug ) {
			register_block_type( $path . $slug );
		}
	}

	/**
	 * Register the block category.
	 *
	 * @since 1.0.0
	 * @param array<int, array<string, mixed>> $categories Block categories.
	 * @return array<int, array<string, mixed>> Block categories.
	 */
	public function register_categories( array $categories ): array {
		return array_merge(
			array(
				array(
					'slug'  => 'ever-checkout',
					'title' => __( 'Ever Checkout', 'ever-checkout' ),
				),
			),
			$categories
		);
	}

	/**
	 * Render the Buy Button block through its shortcode, for page builders that do not render blocks.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>|string $atts Shortcode attributes, or an empty string with none.
	 * @return string Button markup.
	 */
	public function buy_button_shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'label' => '',
				'price' => 'yes',
			),
			(array) $atts,
			'ecout_buy_button'
		);

		return do_blocks(
			serialize_block(
				array(
					'blockName'    => $this->app->make( BuyButton::class )->name,
					'attrs'        => array(
						'productId' => absint( $atts['id'] ),
						'label'     => sanitize_text_field( (string) $atts['label'] ),
						'showPrice' => 'no' !== $atts['price'],
					),
					'innerBlocks'  => array(),
					'innerHTML'    => '',
					'innerContent' => array(),
				)
			)
		);
	}
}
