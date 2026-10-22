<?php
/**
 * Renders the buy button.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

use EverCheckout\Blocks\BuyButton;

defined( 'ABSPATH' ) || exit;

$ecout_product = ecout()->product->find( absint( $attributes['productId'] ?? 0 ) );

if ( ! $ecout_product || 'active' !== $ecout_product->status ) {
	return;
}

$ecout_label = trim( (string) ( $attributes['label'] ?? '' ) );
$ecout_label = '' !== $ecout_label ? $ecout_label : __( 'Buy now', 'ever-checkout' );

if ( ! empty( $attributes['showPrice'] ) ) {
	$ecout_label .= ' - ' . ecout()->money->format( (int) $ecout_product->price );
}

$ecout_width   = absint( $attributes['width'] ?? 0 );
$ecout_classes = array( 'wp-block-button' );

if ( $ecout_width > 0 ) {
	$ecout_classes[] = 'has-custom-width';
	$ecout_classes[] = 'wp-block-button__width-' . $ecout_width;
}

$ecout_button = ecout()->make( BuyButton::class )->get_button_attributes( $attributes );
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => implode( ' ', $ecout_classes ) ) ) ); ?>>
	<button type="button" class="<?php echo esc_attr( $ecout_button['class'] ); ?>"<?php echo isset( $ecout_button['style'] ) ? ' style="' . esc_attr( $ecout_button['style'] ) . '"' : ''; ?> data-ecout-product="<?php echo esc_attr( (string) $ecout_product->id ); ?>"><?php echo esc_html( $ecout_label ); ?></button>
</div>