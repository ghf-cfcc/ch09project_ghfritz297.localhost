<?php

namespace EverCheckout\Blocks;

defined( 'ABSPATH' ) || exit;

/**
 * Base block class.
 *
 * @since   1.0.0
 * @package EverCheckout\Blocks
 */
abstract class Block {

	/**
	 * Style engine store the blocks' instance rules are registered with.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const STYLE_CONTEXT = 'ever-checkout';

	/**
	 * Block name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $name = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_filter( 'render_block_' . $this->name, array( $this, 'render' ), 10, 2 );

		$this->register();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register(): void {}

	/**
	 * Run the render pipeline for one instance of the block.
	 *
	 * @since 1.0.0
	 * @param string               $content Rendered block content.
	 * @param array<string, mixed> $block   Parsed block.
	 * @return string Rendered block content.
	 */
	public function render( string $content, array $block ): string {
		$attributes = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$content    = $this->content( $content, $attributes, $block );

		return $this->style( $content, $attributes );
	}

	/**
	 * Alter the rendered content.
	 *
	 * @since 1.0.0
	 * @param string               $content    Rendered block content.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param array<string, mixed> $block      Parsed block.
	 * @return string Rendered block content.
	 */
	protected function content( string $content, array $attributes, array $block ): string {
		return $content;
	}

	/**
	 * Get the CSS rules an instance needs beyond what the block supports write.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return array<int, array<string, mixed>> Rules of `declarations` and a `selector` appended to the instance class.
	 */
	protected function rules( array $attributes ): array {
		return array();
	}

	/**
	 * Register an instance's rules and stamp its class on the wrapper.
	 *
	 * @since 1.0.0
	 * @param string               $content    Rendered block content.
	 * @param array<string, mixed> $attributes Block attributes.
	 * @return string Rendered block content.
	 */
	protected function style( string $content, array $attributes ): string {
		$rules = array_values( array_filter( $this->rules( $attributes ), static fn( $rule ) => ! empty( $rule['declarations'] ) ) );

		if ( empty( $rules ) || '' === trim( $content ) ) {
			return $content;
		}

		$processor = new \WP_HTML_Tag_Processor( $content );

		if ( ! $processor->next_tag() ) {
			return $content;
		}

		$classname = 'ecout-' . substr( md5( (string) wp_json_encode( array( $this->name, $rules ) ) ), 0, 8 );
		$css_rules = array();

		foreach ( $rules as $rule ) {
			$css_rules[] = array(
				'selector'     => '.' . $classname . (string) ( $rule['selector'] ?? '' ),
				'declarations' => (array) $rule['declarations'],
				'rules_group'  => '',
			);
		}

		wp_style_engine_get_stylesheet_from_css_rules(
			$css_rules,
			array(
				'context'  => self::STYLE_CONTEXT,
				'prettify' => false,
			)
		);

		$processor->add_class( $classname );

		return $processor->get_updated_html();
	}

	/**
	 * Get a CSS value from a stored style value, resolving a preset reference.
	 *
	 * @since 1.0.0
	 * @param mixed $value Stored value, such as `#fff` or `var:preset|color|contrast`.
	 * @return string CSS value, or an empty string when there is none.
	 */
	protected function css_value( $value ): string {
		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		if ( str_starts_with( $value, 'var:' ) ) {
			return 'var(--wp--' . str_replace( '|', '--', substr( $value, 4 ) ) . ')';
		}

		return $value;
	}

	/**
	 * Read a theme.json setting for this block.
	 *
	 * @since 1.0.0
	 * @param array<int, string> $path Setting path, such as `array( 'spacing', 'units' )`.
	 * @return mixed Setting value, or null when it is not set.
	 */
	protected function setting( array $path ) {
		return wp_get_global_settings( $path, array( 'block_name' => $this->name ) );
	}
}
