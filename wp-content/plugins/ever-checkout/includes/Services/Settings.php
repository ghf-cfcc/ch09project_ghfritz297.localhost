<?php

namespace EverCheckout\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings.
 *
 * @since   1.0.0
 * @package EverCheckout\Services
 */
class Settings extends \EverCheckout\B8\Services\Settings {

	/**
	 * Format settings set from the chosen currency.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	const CURRENCY_FORMATS = array(
		'currency_position'  => 'position',
		'currency_thousand'  => 'thousand',
		'currency_decimal'   => 'decimal',
		'currency_precision' => 'exponent',
	);

	/**
	 * Save the settings.
	 *
	 * @since 1.0.0
	 * @param array<string, mixed>                $values Values keyed by field name.
	 * @param array<string, array<string, mixed>> $fields Optional. Fields to save against.
	 * @return int Number of values saved.
	 */
	public function save_values( array $values, array $fields = array() ): int {
		$previous = (string) $this->app->options->get( 'currency', 'USD' );
		$saved    = parent::save_values( $values, $fields );

		if ( isset( $values['currency'] ) && (string) $values['currency'] !== $previous ) {
			$this->set_currency_formats( (string) $values['currency'], $previous );
		}

		return $saved;
	}

	/**
	 * Set the four format settings from a currency.
	 *
	 * @since 1.0.0
	 * @param string $code     Currency the store is moving to.
	 * @param string $previous Currency it is moving from, or empty to overwrite regardless.
	 * @return void
	 */
	public function set_currency_formats( string $code, string $previous = '' ): void {
		$locale = ecout()->locale;
		$to     = $locale->get_currency( $code );

		if ( null === $to ) {
			return;
		}

		$from = '' === $previous ? null : $locale->get_currency( $previous );

		foreach ( self::CURRENCY_FORMATS as $setting => $key ) {
			if ( null !== $from && (string) $this->app->options->get( $setting ) !== (string) $from[ $key ] ) {
				continue;
			}

			$this->app->options->update( $setting, $to[ $key ] );
		}
	}

	/**
	 * Define the plugin settings.
	 *
	 * @since 1.0.0
	 * @return array<string, mixed> Settings definition keyed by group.
	 */
	protected function define_settings(): array {
		$usd = ecout()->locale->get_defaults( 'USD' );

		return array(
			'store'     => array(
				'title'    => __( 'Store', 'ever-checkout' ),
				'priority' => 10,
				'fields'   => array(
					'identity'           => array(
						'type'  => 'section',
						'title' => __( 'Store', 'ever-checkout' ),
					),
					'store_name'         => array(
						'type'     => 'text',
						'label'    => __( 'Store name', 'ever-checkout' ),
						'help'     => __( 'Shown at the top of the checkout.', 'ever-checkout' ),
						'default'  => get_bloginfo( 'name' ),
						'sanitize' => 'string',
						'section'  => 'identity',
					),
					'store_logo'         => array(
						'type'     => 'media',
						'label'    => __( 'Store logo', 'ever-checkout' ),
						'help'     => __( 'A square image works best.', 'ever-checkout' ),
						'default'  => 0,
						'sanitize' => 'integer',
						'section'  => 'identity',
						'props'    => array(
							'layout' => 'card',
							'size'   => 'small',
						),
					),
					'currency_options'   => array(
						'type'  => 'section',
						'title' => __( 'Currency options', 'ever-checkout' ),
					),
					'currency'           => array(
						'type'     => 'select',
						'label'    => __( 'Currency', 'ever-checkout' ),
						'help'     => __( 'Changing this refills the format below, unless you have edited it.', 'ever-checkout' ),
						'default'  => 'USD',
						'options'  => ecout()->locale->get_currency_options(),
						'sanitize' => 'enum:' . implode( ',', array_keys( ecout()->locale->currencies() ) ),
						'width'    => '1/2',
						'section'  => 'currency_options',
					),
					'currency_position'  => array(
						'type'     => 'select',
						'label'    => __( 'Currency position', 'ever-checkout' ),
						'default'  => $usd['position'],
						'options'  => array(
							'before' => __( 'Before the amount', 'ever-checkout' ),
							'after'  => __( 'After the amount', 'ever-checkout' ),
						),
						'sanitize' => 'enum:before,after',
						'width'    => '1/2',
						'section'  => 'currency_options',
					),
					'currency_thousand'  => array(
						'type'     => 'text',
						'label'    => __( 'Thousand separator', 'ever-checkout' ),
						'default'  => $usd['thousand'],
						'sanitize' => 'string',
						'width'    => '1/3',
						'section'  => 'currency_options',
					),
					'currency_decimal'   => array(
						'type'     => 'text',
						'label'    => __( 'Decimal separator', 'ever-checkout' ),
						'default'  => $usd['decimal'],
						'sanitize' => 'string',
						'width'    => '1/3',
						'section'  => 'currency_options',
					),
					'currency_precision' => array(
						'type'     => 'number',
						'label'    => __( 'Number of decimals', 'ever-checkout' ),
						'help'     => __( 'Display only. It never changes what a customer is charged.', 'ever-checkout' ),
						'default'  => $usd['exponent'],
						'min'      => 0,
						'max'      => 4,
						'sanitize' => static fn( $value ) => min( 4, absint( $value ) ),
						'width'    => '1/3',
						'section'  => 'currency_options',
					),
					'pages'              => array(
						'type'  => 'section',
						'title' => __( 'Pages', 'ever-checkout' ),
					),
					'receipt_page'       => array(
						'type'     => 'select',
						'label'    => __( 'Receipt page', 'ever-checkout' ),
						'help'     => __( 'The page holding the Receipt block. Customers are returned here after paying.', 'ever-checkout' ),
						'default'  => '',
						'sanitize' => 'integer',
						'section'  => 'pages',
						'props'    => array(
							'async'          => '/wp/v2/pages?status=publish',
							'getOptionLabel' => '{title.rendered}',
							'getOptionValue' => 'id',
							'allowClear'     => true,
						),
					),
				),
			),
			'downloads' => array(
				'title'    => __( 'Downloads', 'ever-checkout' ),
				'priority' => 20,
				'fields'   => array(
					'delivery'             => array(
						'type'  => 'section',
						'title' => __( 'Downloadable products', 'ever-checkout' ),
					),
					'download_method'      => array(
						'type'     => 'select',
						'label'    => __( 'File download method', 'ever-checkout' ),
						'default'  => 'force',
						'options'  => array(
							'force'     => __( 'Force downloads', 'ever-checkout' ),
							'xsendfile' => __( 'X-Accel-Redirect/X-Sendfile', 'ever-checkout' ),
						),
						'sanitize' => 'enum:force,xsendfile',
						'section'  => 'delivery',
					),
					'download_limit'       => array(
						'type'     => 'number',
						'label'    => __( 'Download limit', 'ever-checkout' ),
						'help'     => __( 'Number of times a file can be downloaded. Zero for unlimited re-downloads.', 'ever-checkout' ),
						'default'  => 0,
						'min'      => 0,
						'sanitize' => static fn( $value ) => absint( $value ),
						'width'    => '1/2',
						'section'  => 'delivery',
					),
					'download_expiry_days' => array(
						'type'     => 'number',
						'label'    => __( 'Download expiry', 'ever-checkout' ),
						'help'     => __( 'Number of days before a download link expires. Zero to never expire.', 'ever-checkout' ),
						'default'  => 0,
						'min'      => 0,
						'sanitize' => static fn( $value ) => absint( $value ),
						'width'    => '1/2',
						'section'  => 'delivery',
					),
				),
			),
			'emails'    => array(
				'title'    => __( 'Emails', 'ever-checkout' ),
				'priority' => 30,
				'fields'   => array(
					'sender'     => array(
						'type'  => 'section',
						'title' => __( 'Email sender options', 'ever-checkout' ),
					),
					'from_name'  => array(
						'type'     => 'text',
						'label'    => __( '"From" name', 'ever-checkout' ),
						'default'  => get_bloginfo( 'name' ),
						'sanitize' => 'string',
						'width'    => '1/2',
						'section'  => 'sender',
					),
					'from_email' => array(
						'type'     => 'email',
						'label'    => __( '"From" address', 'ever-checkout' ),
						'default'  => get_option( 'admin_email' ),
						'sanitize' => 'email',
						'width'    => '1/2',
						'section'  => 'sender',
					),
				),
			),
			'advanced'  => array(
				'title'    => __( 'Advanced', 'ever-checkout' ),
				'priority' => 40,
				'fields'   => array(
					'checkouts'                => array(
						'type'  => 'section',
						'title' => __( 'Abandoned checkouts', 'ever-checkout' ),
					),
					'abandoned_after_hours'    => array(
						'type'     => 'number',
						'label'    => __( 'Hold unpaid orders for', 'ever-checkout' ),
						'help'     => __( 'Hold unpaid orders for x hours. When this limit is reached, the pending order will be cancelled. Nothing is deleted.', 'ever-checkout' ),
						'default'  => 24,
						'min'      => 1,
						'sanitize' => static fn( $value ) => max( 1, absint( $value ) ),
						'section'  => 'checkouts',
					),
					'data'                     => array(
						'type'  => 'section',
						'title' => __( 'Data', 'ever-checkout' ),
					),
					'delete_data_on_uninstall' => array(
						'type'     => 'switch',
						'label'    => __( 'Remove data on uninstall', 'ever-checkout' ),
						'help'     => __( 'Remove all data when the plugin is deleted. Deactivating the plugin leaves everything in place.', 'ever-checkout' ),
						'default'  => 'no',
						'sanitize' => 'yes_no',
						'section'  => 'data',
					),
				),
			),
		);
	}
}
