<?php
/**
 * Renders the receipt.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

use EverCheckout\Blocks\Receipt;

defined( 'ABSPATH' ) || exit;

if ( '' === (string) get_query_var( \EverCheckout\Plugin::QUERY_VARS['receipt'] ) ) {
	return;
}

$ecout_block   = ecout()->make( Receipt::class );
$ecout_order   = $ecout_block->get_order();
$ecout_strings = $ecout_block->get_strings( $attributes );
$ecout_state   = $ecout_block->get_state( $ecout_order );
$ecout_style   = 'fill' === ( $attributes['buttonStyle'] ?? '' ) ? 'is-style-fill' : 'is-style-outline';
$ecout_gateway = $ecout_order ? ecout()->gateways->get_gateway( (string) $ecout_order->gateway ) : null;
?>
<div <?php echo wp_kses_data( get_block_wrapper_attributes( array( 'class' => 'ecout-receipt is-' . $ecout_state ) ) ); ?>>
	<div class="ecout-receipt__header">
		<span class="ecout-receipt__icon" aria-hidden="true">
			<?php if ( 'completed' === $ecout_state ) : ?>
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"></path></svg>
			<?php elseif ( 'pending' === $ecout_state ) : ?>
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
			<?php else : ?>
				<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>
			<?php endif; ?>
		</span>

		<?php if ( 'not-found' === $ecout_state ) : ?>
			<h2 class="ecout-receipt__heading"><?php echo esc_html( $ecout_strings['notFoundHeading'] ); ?></h2>
		<?php elseif ( 'closed' === $ecout_state ) : ?>
			<h2 class="ecout-receipt__heading"><?php echo esc_html( $ecout_strings['closedHeading'] ); ?></h2>
		<?php else : ?>
			<h2 class="ecout-receipt__heading"><?php echo esc_html( $ecout_strings[ 'pending' === $ecout_state ? 'pendingHeading' : 'completedHeading' ] ); ?></h2>
			<p class="ecout-receipt__note">
				<?php
				echo wp_kses(
					str_replace(
						'%s',
						'<strong>' . esc_html( (string) $ecout_order->email ) . '</strong>',
						esc_html( $ecout_strings[ 'pending' === $ecout_state ? 'pendingNote' : 'completedNote' ] )
					),
					array( 'strong' => array() )
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<?php if ( $ecout_order ) : ?>
		<div class="ecout-receipt__card">
			<?php if ( ! empty( $attributes['showSummary'] ) ) : ?>
				<dl class="ecout-receipt__summary">
					<div class="ecout-receipt__fact">
						<dt><?php esc_html_e( 'Order', 'ever-checkout' ); ?></dt>
						<dd>#<?php echo absint( $ecout_order->id ); ?></dd>
					</div>
					<?php if ( 'pending' === $ecout_state && $ecout_gateway ) : ?>
						<div class="ecout-receipt__fact">
							<dt><?php esc_html_e( 'Pay with', 'ever-checkout' ); ?></dt>
							<dd><?php echo esc_html( $ecout_gateway->title ); ?></dd>
						</div>
					<?php else : ?>
						<div class="ecout-receipt__fact">
							<dt><?php esc_html_e( 'Date', 'ever-checkout' ); ?></dt>
							<dd><?php echo esc_html( (string) wp_date( (string) get_option( 'date_format' ), (int) strtotime( (string) $ecout_order->created_at ) ) ); ?></dd>
						</div>
					<?php endif; ?>
					<div class="ecout-receipt__fact">
						<dt><?php esc_html_e( 'Total', 'ever-checkout' ); ?></dt>
						<dd><?php echo esc_html( ecout()->money->format( (int) $ecout_order->total, (string) $ecout_order->currency ) ); ?></dd>
					</div>
				</dl>
			<?php endif; ?>

			<?php if ( 'pending' === $ecout_state ) : ?>
				<div class="ecout-receipt__instructions">
					<?php if ( $ecout_gateway && '' !== $ecout_gateway->description ) : ?>
						<span class="ecout-receipt__file-name"><?php esc_html_e( 'How to pay', 'ever-checkout' ); ?></span>
						<?php echo wp_kses_post( wpautop( $ecout_gateway->description ) ); ?>
					<?php else : ?>
						<p><?php esc_html_e( 'This usually takes a few seconds. Refresh the page, and check your email for the download links.', 'ever-checkout' ); ?></p>
					<?php endif; ?>
				</div>
			<?php else : ?>
				<?php
				$ecout_files = array();

				foreach ( $ecout_order->can_download() ? $ecout_order->get_downloads() : array() as $ecout_entry ) {
					foreach ( $ecout_entry['files'] as $ecout_download ) {
						$ecout_files[] = array( $ecout_entry['product'], $ecout_download );
					}
				}
				?>
				<?php if ( empty( $ecout_files ) ) : ?>
					<p class="ecout-receipt__instructions"><?php esc_html_e( 'There is nothing to download on this order.', 'ever-checkout' ); ?></p>
				<?php else : ?>
					<ul class="ecout-receipt__files">
						<?php foreach ( $ecout_files as list( $ecout_product, $ecout_download ) ) : ?>
							<?php
							$ecout_name    = $ecout_download->name ? $ecout_download->name : ( $ecout_download->file_name ? $ecout_download->file_name : __( 'Your file', 'ever-checkout' ) );
							$ecout_expires = (string) $ecout_order->get_download_expiry( $ecout_product );
							$ecout_limit   = $ecout_product ? $ecout_product->get_download_limit() : (int) ecout()->options->get( 'download_limit', 0 );
							$ecout_meta    = array();

							if ( $ecout_download->file_name && $ecout_download->file_name !== $ecout_name ) {
								$ecout_meta[] = $ecout_download->file_name;
							}

							if ( (int) $ecout_download->file_size > 0 ) {
								$ecout_meta[] = size_format( (int) $ecout_download->file_size, 1 );
							}

							if ( '' !== $ecout_expires ) {
								/* translators: %s: expiry date. */
								$ecout_meta[] = sprintf( __( 'Available until %s', 'ever-checkout' ), wp_date( (string) get_option( 'date_format' ), (int) strtotime( $ecout_expires ) ) );
							} elseif ( $ecout_limit > 0 ) {
								$ecout_left = max( 0, $ecout_limit - $ecout_order->get_download_count( $ecout_download ) );
								/* translators: %d: downloads left. */
								$ecout_meta[] = sprintf( _n( '%d download remaining', '%d downloads remaining', $ecout_left, 'ever-checkout' ), $ecout_left );
							}
							?>
							<li class="ecout-receipt__file">
								<span class="ecout-receipt__file-icon" aria-hidden="true">
									<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7z"></path><path d="M14 2v4a2 2 0 0 0 2 2h4"></path></svg>
								</span>
								<span class="ecout-receipt__file-details">
									<span class="ecout-receipt__file-name"><?php echo esc_html( $ecout_name ); ?></span>
									<?php if ( ! empty( $ecout_meta ) ) : ?>
										<span class="ecout-receipt__file-meta"><?php echo esc_html( implode( ' · ', $ecout_meta ) ); ?></span>
									<?php endif; ?>
								</span>
								<span class="wp-block-button ecout-receipt__action <?php echo esc_attr( $ecout_style ); ?>">
									<a class="wp-block-button__link wp-element-button" href="<?php echo esc_url( $ecout_order->get_download_url( $ecout_download ) ); ?>">
										<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3"></path><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><path d="m7 10 5 5 5-5"></path></svg>
										<span><?php echo esc_html( $ecout_strings['downloadLabel'] ); ?></span>
									</a>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<p class="ecout-receipt__help"><?php echo esc_html( $ecout_strings['helpText'] ); ?></p>
	<?php endif; ?>
</div>