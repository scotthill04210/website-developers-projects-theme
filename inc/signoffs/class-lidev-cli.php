<?php
/**
 * WP-CLI commands for sign-off mail.
 *
 * @package LIDevTheme
 * @since   1.7.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Send the real phase email and print the headers the mailer used.
 *
 * @since 1.7.0
 */
final class Lidev_CLI {

	/**
	 * Send the phase email for a phase to one address.
	 *
	 * Uses the same message and delivery path as a Team send. The magic link
	 * is issued for an assigned client.
	 *
	 * ## OPTIONS
	 *
	 * <email>
	 * : Where to send the message.
	 *
	 * <phase_id>
	 * : Phase row ID.
	 *
	 * ## EXAMPLES
	 *
	 *     wp lidev send-test-phase-email person@example.com 34
	 *
	 * @since 1.7.0
	 * @param list<string>         $args       Positional arguments.
	 * @param array<string, mixed> $assoc_args Associative arguments.
	 * @return void
	 */
	public function send_test_phase_email( array $args, array $assoc_args ): void {
		unset( $assoc_args );

		$email    = isset( $args[0] ) ? (string) $args[0] : '';
		$phase_id = isset( $args[1] ) ? absint( $args[1] ) : 0;
		$sent     = Lidev_Mail::send_phase_to( $email, $phase_id );
		$mail     = Lidev_Mail::last_delivery();

		WP_CLI::log( 'recipient: ' . sanitize_email( $email ) );
		WP_CLI::log( 'from_name: ' . $mail['from_name'] );
		WP_CLI::log( 'from_email: ' . $mail['from_email'] );
		WP_CLI::log( 'reply_to: ' . ( '' !== $mail['reply_to'] ? $mail['reply_to'] : '(none)' ) );
		WP_CLI::log( 'subject: ' . $mail['subject'] );
		WP_CLI::log( 'headers:' );
		WP_CLI::log( '' !== $mail['headers'] ? $mail['headers'] : '(none)' );
		WP_CLI::log( 'wp_mail: ' . ( $sent ? 'true' : 'false' ) );

		if ( '' !== $mail['error'] ) {
			WP_CLI::warning( 'wp_mail_failed: ' . $mail['error'] );
		} else {
			WP_CLI::log( 'wp_mail_failed: (none)' );
		}

		if ( ! $sent ) {
			WP_CLI::halt( 1 );
		}

		WP_CLI::success( 'Phase email handed to the mailer.' );
	}
}

WP_CLI::add_command( 'lidev send-test-phase-email', array( 'Lidev_CLI', 'send_test_phase_email' ) );
