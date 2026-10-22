<?php

namespace EverCheckout\Emails;

defined( 'ABSPATH' ) || exit;

/**
 * Base email class.
 *
 * @since   1.0.0
 * @package EverCheckout\Emails
 */
abstract class Email {

	/**
	 * Email id.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $id = '';

	/**
	 * Title shown to the seller.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $title = '';

	/**
	 * Description shown to the seller.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $description = '';

	/**
	 * Who receives it: `customer` or `admin`.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public string $recipient_type = 'customer';

	/**
	 * Whether the email is enabled.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	public bool $enabled = true;

	/**
	 * Content type of the body.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $content_type = 'text/html';

	/**
	 * Subject used until the seller sets one.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $default_subject = '';

	/**
	 * Heading used until the seller sets one.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $default_heading = '';

	/**
	 * Body used until the seller sets one.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $default_body = '';

	/**
	 * Object the email is about.
	 *
	 * @since 1.0.0
	 * @var object|null
	 */
	protected $object = null;

	/**
	 * Recipient address, or addresses separated by commas.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $recipient = '';

	/**
	 * Placeholder values keyed by tag.
	 *
	 * @since 1.0.0
	 * @var array<string, string>
	 */
	protected array $placeholders = array();

	/**
	 * Markup of the message being sent.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	protected string $sending = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->enabled = 'yes' === $this->get_option( 'enabled', 'yes' );
	}

	/**
	 * Send the email for an event.
	 *
	 * @since 1.0.0
	 * @param mixed ...$args Arguments the triggering hook passed.
	 * @return void
	 */
	abstract public function trigger( ...$args ): void;

	/**
	 * Get the placeholders the seller may use, keyed by tag.
	 *
	 * @since 1.0.0
	 * @return array<string, string> Descriptions keyed by tag.
	 */
	public function get_placeholders(): array {
		return array(
			'{site_name}' => __( 'Site name', 'ever-checkout' ),
			'{site_url}'  => __( 'Site URL', 'ever-checkout' ),
		);
	}

	/**
	 * Fill the placeholder values for the object being sent about.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	protected function populate_placeholders(): void {
		$this->placeholders = array(
			'{site_name}' => esc_html( (string) get_bloginfo( 'name' ) ),
			'{site_url}'  => esc_url( home_url( '/' ) ),
		);
	}

	/**
	 * Get the recipient.
	 *
	 * @since 1.0.0
	 * @return string Recipient address, or addresses separated by commas.
	 */
	public function get_recipient(): string {
		return (string) ecout()->apply_filters( 'email_recipient', $this->recipient, $this );
	}

	/**
	 * Get the subject with its placeholders filled.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_subject(): string {
		return wp_specialchars_decode( $this->format_string( (string) $this->get_option( 'subject', $this->default_subject ) ), ENT_QUOTES );
	}

	/**
	 * Get the heading with its placeholders filled.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_heading(): string {
		return $this->format_string( (string) $this->get_option( 'heading', $this->default_heading ) );
	}

	/**
	 * Get the body with its placeholders filled.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_body(): string {
		return $this->format_string( (string) $this->get_option( 'body', $this->default_body ) );
	}

	/**
	 * Get the whole message.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_content(): string {
		if ( 'text/html' !== $this->content_type ) {
			return $this->get_body();
		}

		ob_start();

		ecout()->template->render(
			'emails.email',
			array(
				'email'   => $this,
				'heading' => $this->get_heading(),
				'body'    => $this->get_body(),
			)
		);

		return (string) ob_get_clean();
	}

	/**
	 * Replace the placeholders in a string.
	 *
	 * @since 1.0.0
	 * @param string $text Text holding placeholders.
	 * @return string
	 */
	public function format_string( string $text ): string {
		return strtr( $text, $this->placeholders );
	}

	/**
	 * Send the message.
	 *
	 * A failed send is logged and never stops the event that triggered it.
	 *
	 * @since 1.0.0
	 * @return bool Whether the mailer accepted the message.
	 */
	public function send(): bool {
		$recipient = $this->get_recipient();

		if ( '' === $recipient || ! ecout()->apply_filters( 'email_enabled', $this->enabled, $this ) ) {
			return false;
		}

		$subject = $this->get_subject();
		$content = $this->get_content();
		$headers = array(
			sprintf(
				'From: %s <%s>',
				(string) ecout()->options->get( 'from_name', get_bloginfo( 'name' ) ),
				sanitize_email( (string) ecout()->options->get( 'from_email', get_option( 'admin_email' ) ) )
			),
			"Content-Type: {$this->content_type}; charset=UTF-8",
		);

		if ( 'text/html' === $this->content_type ) {
			$this->sending = $content;
			add_action( 'phpmailer_init', array( $this, 'add_text_part' ) );
		}

		$sent = wp_mail( $recipient, $subject, $content, (array) ecout()->apply_filters( 'email_headers', $headers, $this ) );

		remove_action( 'phpmailer_init', array( $this, 'add_text_part' ) );
		$this->sending = '';

		if ( ! $sent ) {
			ecout()->logger->error( sprintf( 'Email %s failed to send.', $this->id ) );
		}

		return $sent;
	}

	/**
	 * Give an HTML message its plain-text part.
	 *
	 * A receipt with no text part scores badly with spam filters.
	 *
	 * @since 1.0.0
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer Mailer about to send.
	 * @return void
	 */
	public function add_text_part( $phpmailer ): void {
		if ( '' !== $this->sending ) {
			$phpmailer->msgHTML( $this->sending );
		}
	}

	/**
	 * Get the settings fields the seller can edit.
	 *
	 * @since 1.0.0
	 * @return array<string, array<string, mixed>> Fields keyed by setting key.
	 */
	public function get_settings_fields(): array {
		return array(
			'enabled' => array(
				'type'     => 'switch',
				'label'    => __( 'Enable this email', 'ever-checkout' ),
				'default'  => 'yes',
				'sanitize' => 'yes_no',
			),
			'subject' => array(
				'type'     => 'text',
				'label'    => __( 'Subject', 'ever-checkout' ),
				'default'  => $this->default_subject,
				'sanitize' => 'string',
			),
			'heading' => array(
				'type'     => 'text',
				'label'    => __( 'Heading', 'ever-checkout' ),
				'default'  => $this->default_heading,
				'sanitize' => 'string',
			),
			'body'    => array(
				'type'        => 'editor',
				'label'       => __( 'Body', 'ever-checkout' ),
				'help'        => sprintf(
					/* translators: %s: comma-separated placeholder tags. */
					__( 'Placeholders: %s', 'ever-checkout' ),
					implode( ', ', array_keys( $this->get_placeholders() ) )
				),
				'default'     => $this->default_body,
				'sanitize'    => 'html',
				'orientation' => 'vertical',
			),
		);
	}

	/**
	 * Get a setting.
	 *
	 * @since 1.0.0
	 * @param string $key      Setting key.
	 * @param mixed  $fallback Value when the setting is not stored.
	 * @return mixed
	 */
	public function get_option( string $key, $fallback = '' ) {
		return ecout()->options->get( "email_{$this->id}_{$key}", $fallback );
	}

	/**
	 * Update a setting.
	 *
	 * @since 1.0.0
	 * @param string $key   Setting key.
	 * @param mixed  $value Value to store.
	 * @return bool
	 */
	public function update_option( string $key, $value ): bool {
		return (bool) ecout()->options->update( "email_{$this->id}_{$key}", $value );
	}
}
