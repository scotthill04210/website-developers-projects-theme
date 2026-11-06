<?php
/**
 * Front-page sign-in handlers.
 *
 * @package LIDevTheme
 * @since   1.3.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Team password sign-in and the client "email me a link" form.
 *
 * @since 1.3.0
 */
final class Lidev_Front_Auth {

	/**
	 * Sign a Team member in with a username or email and a password.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function team_login(): void {
		check_admin_referer( 'lidev_team_login', 'lidev_nonce' );

		$login = isset( $_POST['lidev_login'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lidev_login'] ) ) : '';
		$pass  = isset( $_POST['lidev_password'] ) ? (string) wp_unslash( $_POST['lidev_password'] ) : '';

		if ( is_email( $login ) ) {
			$by_email = get_user_by( 'email', $login );

			if ( $by_email instanceof WP_User ) {
				$login = $by_email->user_login;
			}
		}

		$user = wp_signon(
			array(
				'user_login'    => $login,
				'user_password' => $pass,
				'remember'      => ! empty( $_POST['lidev_remember'] ),
			),
			is_ssl()
		);

		if ( is_wp_error( $user ) ) {
			$captcha = array( 'captcha_error', 'invalid_captcha' );
			$code    = array_intersect( $captcha, $user->get_error_codes() ) ? 'captcha_failed' : 'login_failed';
			self::back( home_url( '/' ), $code );
		}

		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Email a fresh magic link. The response does not say whether the account exists.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function request_magic_link(): void {
		check_admin_referer( 'lidev_request_magic_link', 'lidev_nonce' );

		$email = isset( $_POST['lidev_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['lidev_email'] ) ) : '';
		$back  = wp_get_referer();

		// A magic-link URL must not be echoed back into the next address.
		if ( ! is_string( $back ) || '' === $back || str_contains( $back, 'token=' ) ) {
			$back = home_url( '/' );
		}

		if ( ! is_email( $email ) ) {
			self::back( $back, 'email_invalid' );
		}

		if ( ! Lidev_Magic_Link::allow_request( $email ) ) {
			self::back( $back, 'magic_limited' );
		}

		$user = get_user_by( 'email', $email );

		if ( $user instanceof WP_User && lidev_user_is_only_client( $user ) ) {
			$url = lidev_issue_magic_link( (int) $user->ID );

			if ( is_string( $url ) ) {
				self::mail( $user, $url );
			}
		}

		self::back( $back, 'magic_sent' );
	}

	/**
	 * Email a password reset to a Development Team account. The reply does not say whether the account exists.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public static function lost_password(): void {
		check_admin_referer( 'lidev_lost_password', 'lidev_nonce' );

		$login = isset( $_POST['lidev_login'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lidev_login'] ) ) : '';

		if ( '' === $login ) {
			self::back( home_url( '/' ), 'reset_missing' );
		}

		if ( ! Lidev_Magic_Link::allow_request( $login ) ) {
			self::back( home_url( '/' ), 'magic_limited' );
		}

		$user = is_email( $login ) ? get_user_by( 'email', $login ) : get_user_by( 'login', $login );

		if ( $user instanceof WP_User && self::uses_password( $user ) ) {
			retrieve_password( $user->user_login );
		}

		self::back( home_url( '/' ), 'reset_sent' );
	}

	/**
	 * Clients sign in with a link. Password reset is for Team and administrators.
	 *
	 * @since 1.8.0
	 * @param WP_User $user Account.
	 * @return bool
	 */
	private static function uses_password( WP_User $user ): bool {
		if ( lidev_user_is_only_client( $user ) || lidev_user_is_client_only( $user ) ) {
			return false;
		}

		return user_can( $user, 'lidev_view_all_projects' ) || user_can( $user, 'manage_options' );
	}

	/**
	 * @since 1.3.0
	 * @param WP_User $user Client.
	 * @param string  $url  Magic link.
	 * @return void
	 */
	private static function mail( WP_User $user, string $url ): void {
		$settings = Lidev_Settings::get();
		$days     = (string) $settings['magic_link_days'];
		$subject  = sprintf(
			/* translators: %s: site name. */
			__( 'Your sign-in link for %s', 'li-dev-theme' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$name = '' !== (string) $user->display_name ? (string) $user->display_name : '';
		$note = sprintf(
			/* translators: %s: number of days the link works. */
			__( 'This link works for %s days.', 'li-dev-theme' ),
			$days
		);

		Lidev_Mail::send_branded(
			(string) $user->user_email,
			$subject,
			__( 'Sign in', 'li-dev-theme' ),
			'' !== $name
				? sprintf(
					/* translators: %s: client name. */
					__( 'Hello %s. Use the button to open your projects.', 'li-dev-theme' ),
					$name
				)
				: __( 'Use the button to open your projects.', 'li-dev-theme' ),
			'',
			$url,
			__( 'Sign in', 'li-dev-theme' ),
			$note,
			'sign_in_link'
		);
	}

	/**
	 * @since 1.3.0
	 * @param string $url  Page to return to.
	 * @param string $code Notice code.
	 * @return void
	 */
	private static function back( string $url, string $code ): void {
		wp_safe_redirect( add_query_arg( 'lidev_notice', sanitize_key( $code ), $url ) );
		exit;
	}
}
