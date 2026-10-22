<?php
/**
 * Renders the checkout dialog.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @var array<string, \EverCheckout\Gateways\Gateway> $gateways   Gateways a customer may pay with.
 * @var string                                        $store_name Store name shown in the header.
 * @var string                                        $store_logo Store logo URL, or empty.
 * @var string                                        $honeypot   Name of the honeypot field.
 */

defined( 'ABSPATH' ) || exit;

$ecout_first = reset( $gateways );
?>
<dialog class="ecout-checkout" id="ecout-checkout" aria-labelledby="ecout-checkout-name">
	<form class="ecout-checkout__form" method="post" novalidate>
		<header class="ecout-checkout__header">
			<span class="ecout-checkout__store">
				<?php if ( '' !== $store_logo ) : ?>
					<img class="ecout-checkout__logo" src="<?php echo esc_url( $store_logo ); ?>" alt="" width="24" height="24">
				<?php else : ?>
					<span class="ecout-checkout__mark" aria-hidden="true"><?php echo esc_html( mb_substr( $store_name, 0, 1 ) ); ?></span>
				<?php endif; ?>
				<span class="ecout-checkout__store-name"><?php echo esc_html( $store_name ); ?></span>
			</span>
			<button type="button" class="ecout-checkout__close" data-ecout-close aria-label="<?php esc_attr_e( 'Close', 'ever-checkout' ); ?>">
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg>
			</button>
		</header>

		<div class="ecout-checkout__body">
			<div class="ecout-checkout__product">
				<span class="ecout-checkout__image">
					<img src="" alt="" width="64" height="64" hidden>
					<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="9" cy="9" r="2"></circle><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"></path></svg>
				</span>
				<span class="ecout-checkout__details">
					<span class="ecout-checkout__name" id="ecout-checkout-name"></span>
					<span class="ecout-checkout__description"></span>
				</span>
				<span class="ecout-checkout__price"></span>
			</div>

			<?php if ( count( $gateways ) > 1 ) : ?>
				<fieldset class="ecout-checkout__gateways">
					<legend class="ecout-checkout__label"><?php esc_html_e( 'Pay with', 'ever-checkout' ); ?></legend>
					<?php foreach ( $gateways as $ecout_id => $ecout_gateway ) : ?>
						<label class="ecout-checkout__gateway">
							<input type="radio" name="gateway" value="<?php echo esc_attr( $ecout_id ); ?>" <?php checked( $ecout_first && $ecout_first->id === $ecout_id ); ?>>
							<span class="ecout-checkout__gateway-title"><?php echo esc_html( $ecout_gateway->title ); ?></span>
							<?php if ( '' !== $ecout_gateway->icon ) : ?>
								<img class="ecout-checkout__gateway-icon" src="<?php echo esc_url( $ecout_gateway->icon ); ?>" alt="" height="20">
							<?php endif; ?>
							<?php if ( '' !== $ecout_gateway->description ) : ?>
								<span class="ecout-checkout__gateway-description"><?php echo esc_html( wp_strip_all_tags( $ecout_gateway->description ) ); ?></span>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
			<?php else : ?>
				<input type="hidden" name="gateway" value="<?php echo esc_attr( $ecout_first ? $ecout_first->id : '' ); ?>">
			<?php endif; ?>

			<div class="ecout-checkout__field">
				<label class="ecout-checkout__label" for="ecout-checkout-buyer"><?php esc_html_e( 'Name', 'ever-checkout' ); ?></label>
				<input class="ecout-checkout__input" type="text" name="name" id="ecout-checkout-buyer" autocomplete="name" required>
			</div>

			<div class="ecout-checkout__field">
				<label class="ecout-checkout__label" for="ecout-checkout-email"><?php esc_html_e( 'Email', 'ever-checkout' ); ?></label>
				<input class="ecout-checkout__input" type="email" name="email" id="ecout-checkout-email" autocomplete="email" inputmode="email" required aria-describedby="ecout-checkout-email-help">
				<span class="ecout-checkout__help" id="ecout-checkout-email-help"><?php esc_html_e( 'Your download link is sent here.', 'ever-checkout' ); ?></span>
			</div>

			<label class="ecout-checkout__extra" aria-hidden="true"><?php esc_html_e( 'Website', 'ever-checkout' ); ?><input type="text" name="<?php echo esc_attr( $honeypot ); ?>" tabindex="-1" autocomplete="off"></label>

			<p class="ecout-checkout__error" role="alert" hidden></p>

			<div class="wp-block-button ecout-checkout__submit">
				<button type="submit" class="wp-block-button__link wp-element-button ecout-checkout__pay"><?php esc_html_e( 'Pay', 'ever-checkout' ); ?></button>
			</div>

			<p class="ecout-checkout__note">
				<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
				<?php esc_html_e( 'Secure checkout. No account needed.', 'ever-checkout' ); ?>
			</p>
		</div>
	</form>
</dialog>