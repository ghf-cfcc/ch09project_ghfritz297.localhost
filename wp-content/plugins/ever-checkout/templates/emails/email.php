<?php
/**
 * Wraps an HTML email.
 *
 * @since   1.0.0
 * @package EverCheckout
 *
 * @var \EverCheckout\Emails\Email $email   Email being sent.
 * @var string                     $heading Heading, with its placeholders filled.
 * @var string                     $body    Body markup, with its placeholders filled.
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title><?php echo esc_html( $heading ); ?></title>
</head>
<body style="margin:0;padding:24px;background:#f6f7f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1e1e1e;">
	<div style="max-width:560px;margin:0 auto;background:#ffffff;padding:32px;border-radius:6px;">
		<?php if ( '' !== $heading ) : ?>
			<h1 style="margin:0 0 16px;font-size:20px;font-weight:600;"><?php echo esc_html( $heading ); ?></h1>
		<?php endif; ?>

		<?php echo wp_kses_post( $body ); ?>

		<p style="margin-top:32px;padding-top:16px;border-top:1px solid #e0e0e0;font-size:13px;color:#757575;">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:#757575;"><?php echo esc_html( (string) get_bloginfo( 'name' ) ); ?></a>
		</p>
	</div>
</body>
</html>