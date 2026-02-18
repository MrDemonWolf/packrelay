<?php
/**
 * Email notification handler.
 *
 * @package    PackRelay
 * @copyright  2026 MrDemonWolf, Inc.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PackRelay_Notification
 *
 * Sends email notifications for new form submissions via wp_mail().
 */
class PackRelay_Notification {

	/**
	 * Send a notification email for a new entry.
	 *
	 * @param int    $entry_id   The created entry ID.
	 * @param int    $form_id    The WPForms form ID.
	 * @param array  $fields     Submitted field data (field_id => value).
	 * @param string $form_title The form title.
	 * @return bool Whether the email was sent successfully.
	 */
	public function send( $entry_id, $form_id, $fields, $form_title = '' ) {
		$settings = PackRelay_Settings::get_settings();
		$to       = $settings['notification_email'];

		if ( empty( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		if ( empty( $to ) ) {
			return false;
		}

		if ( empty( $form_title ) ) {
			$form_title = sprintf( __( 'Form #%d', 'packrelay' ), $form_id );
		}

		/* translators: %s: form title */
		$subject = sprintf( __( '[PackRelay] New submission: %s', 'packrelay' ), $form_title );

		$body = $this->build_email_body( $fields, $form_title, $entry_id );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		// Add Reply-To if an email field is present.
		$reply_to = $this->find_email_field( $fields );
		if ( $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		$mail_args = array(
			'to'      => $to,
			'subject' => $subject,
			'body'    => $body,
			'headers' => $headers,
		);

		/**
		 * Filter the email notification arguments before sending.
		 *
		 * @param array $mail_args The email arguments.
		 * @param int   $entry_id  The entry ID.
		 * @param int   $form_id   The form ID.
		 */
		$mail_args = apply_filters( 'packrelay_notification_args', $mail_args, $entry_id, $form_id );

		return wp_mail(
			$mail_args['to'],
			$mail_args['subject'],
			$mail_args['body'],
			$mail_args['headers']
		);
	}

	/**
	 * Build the HTML email body.
	 *
	 * @param array  $fields     Field data.
	 * @param string $form_title Form title.
	 * @param int    $entry_id   Entry ID.
	 * @return string
	 */
	private function build_email_body( $fields, $form_title, $entry_id ) {
		$html  = '<h2>' . esc_html( $form_title ) . '</h2>';
		$html .= '<p>' . esc_html(
			sprintf(
				/* translators: %d: entry ID */
				__( 'Entry #%d', 'packrelay' ),
				$entry_id
			)
		) . '</p>';
		$html .= '<table style="border-collapse: collapse; width: 100%;">';

		foreach ( $fields as $field_id => $value ) {
			$html .= '<tr>';
			$html .= '<td style="padding: 8px; border: 1px solid #ddd; font-weight: bold;">';
			$html .= esc_html( sprintf( __( 'Field %s', 'packrelay' ), $field_id ) );
			$html .= '</td>';
			$html .= '<td style="padding: 8px; border: 1px solid #ddd;">';
			$html .= esc_html( $value );
			$html .= '</td>';
			$html .= '</tr>';
		}

		$html .= '</table>';

		return $html;
	}

	/**
	 * Find an email value in the submitted fields.
	 *
	 * @param array $fields Field data.
	 * @return string|false Email address or false.
	 */
	private function find_email_field( $fields ) {
		foreach ( $fields as $value ) {
			if ( is_email( $value ) ) {
				return sanitize_email( $value );
			}
		}

		return false;
	}
}
