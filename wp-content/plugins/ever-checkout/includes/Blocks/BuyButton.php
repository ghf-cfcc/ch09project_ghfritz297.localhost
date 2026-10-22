<?php

namespace EverCheckout\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Buy Button block.
 *
 * @since   1.0.0
 * @package EverCheckout\Blocks
 */
class BuyButton extends Block {

	/**
	 * Block name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $name = 'ever-checkout/buy-button';

	/**
	 * Get the hover rule an instance sets.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<int, array<string, mixed>> Rules.
	 */
	protected function rules( array $attributes ): array {
		$hover        = $attributes['style']['everCheckout']['hover'] ?? array();
		$declarations = array_filter(
			array(
				'background' => $this->css_value( $hover['background'] ?? '' ),
				'color'      => $this->css_value( $hover['text'] ?? '' ),
			)
		);

		return array(
			array(
				'selector'     => ' .wp-block-button__link:hover',
				'declarations' => $declarations,
			),
		);
	}

	/**
	 * Get the class and style attributes the button element carries.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<string, string> `class`, and `style` when the block sets any.
	 */
	public function get_button_attributes( array $attributes ): array {
		$style   = isset( $attributes['style'] ) && is_array( $attributes['style'] ) ? $attributes['style'] : array();
		$classes = array( 'wp-block-button__link', 'wp-element-button' );

		$presets = array(
			'backgroundColor' => array( 'has-background', 'has-%s-background-color' ),
			'textColor'       => array( 'has-text-color', 'has-%s-color' ),
			'gradient'        => array( 'has-background', 'has-%s-gradient-background' ),
			'borderColor'     => array( 'has-border-color', 'has-%s-border-color' ),
		);

		foreach ( $presets as $attribute => $pair ) {
			if ( ! empty( $attributes[ $attribute ] ) ) {
				$classes[] = $pair[0];
				$classes[] = sprintf( $pair[1], sanitize_html_class( (string) $attributes[ $attribute ] ) );
			}
		}

		if ( ! empty( $style['color']['background'] ) || ! empty( $style['color']['gradient'] ) ) {
			$classes[] = 'has-background';
		}

		if ( ! empty( $style['color']['text'] ) ) {
			$classes[] = 'has-text-color';
		}

		if ( ! empty( $style['border']['color'] ) ) {
			$classes[] = 'has-border-color';
		}

		$styles = wp_style_engine_get_styles(
			array(
				'color'   => $style['color'] ?? array(),
				'border'  => $style['border'] ?? array(),
				'spacing' => array( 'padding' => $style['spacing']['padding'] ?? array() ),
				'shadow'  => $style['shadow'] ?? '',
			)
		);

		$attributes = array( 'class' => implode( ' ', array_unique( $classes ) ) );

		if ( ! empty( $styles['css'] ) ) {
			$attributes['style'] = $styles['css'];
		}

		return $attributes;
	}
}
