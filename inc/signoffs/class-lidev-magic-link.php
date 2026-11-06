<?php
/**
 * Magic links for client sign-in.
 *
 * Only a hash of the token is stored. The raw token exists in the email and the URL.
 *
 * @package LIDevTheme
 * @since   1.3.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Issue, send, and redeem client magic links.
 *
 * @since 1.3.0
 */
final class Lidev_Magic_Link {

	/**
	 * Create a link for a client. Returns the URL that contains the raw token.
	 *
	 * @since 1.3.0
	 * @param int      $user_id  Client user ID.
	 * @param int|null $phase_id Phase to open after sign-in, or null.
	 * @return string|WP_Error
	 */
	public static function issue( int $user_id, ?int $phase_id = null ) {
		$user = get_userdata( $user_id );

		if ( ! $user instanceof WP_User || ! lidev_user_is_only_client( $user ) ) {
			return new WP_Error( 'lidev_magic_user', __( 'Magic links are for client accounts.', 'li-dev-theme' ) );
		}

		try {
			$raw = bin2hex( random_bytes( 32 ) );
		} catch ( Exception $exception ) {
			unset( $exception );

			return new WP_Error( 'lidev_magic_token', __( 'Could not create a sign-in link.', 'li-dev-theme' ) );
		}

		global $wpdb;

		$phase = $phase_id && $phase_id > 0 ? $phase_id : 0;
		$now   = time();
		$saved = $wpdb->insert(
			lidev_table( 'magic' ),
			array(
				'user_id'    => $user_id,
				'token_hash' => hash( 'sha256', $raw ),
				'phase_id'   => $phase,
				'created_at' => current_time( 'mysql' ),
				'expires_at' => wp_date( 'Y-m-d H:i:s', $now + self::lifetime( $user_id ) ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		if ( false === $saved ) {
			return new WP_Error( 'lidev_magic_store', __( 'Could not create a sign-in link.', 'li-dev-theme' ) );
		}

		$phase_row  = $phase > 0 ? Lidev_Phase_Repository::get( $phase ) : null;
		$project_id = is_array( $phase_row ) ? (int) $phase_row['project_id'] : 0;

		lidev_log(
			'magic_link_issued',
			$project_id,
			$phase > 0 ? $phase : null,
			array( 'user_id' => $user_id )
		);

		return self::url( $raw );
	}

	/**
	 * Log the client in when /access/ carries a usable token.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function maybe_redeem(): void {
		if ( 'access' !== Lidev_Routing::screen() ) {
			return;
		}

		$token = isset( $_GET['token'] ) ? (string) wp_unslash( $_GET['token'] ) : '';

		if ( '' === $token ) {
			return;
		}

		$redirect = self::redeem( $token );

		if ( is_wp_error( $redirect ) ) {
			return;
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Keep a client signed in for the same stretch of time as their magic link.
	 *
	 * @since 1.3.0
	 * @param int $length  Default lifetime in seconds.
	 * @param int $user_id User ID.
	 * @param bool $remember Whether the login asked to be remembered.
	 * @return int
	 */
	public static function cookie_expiration( $length, $user_id, $remember ): int {
		unset( $remember );

		if ( ! lidev_user_is_client_only( (int) $user_id ) ) {
			return (int) $length;
		}

		return self::lifetime( (int) $user_id );
	}

	/**
	 * Seconds until a new link expires.
	 *
	 * Filter `lidev_magic_link_lifetime` to shorten this in a test. The value is
	 * clamped to at least one minute and at most 365 days.
	 *
	 * @since 1.3.0
	 * @param int $user_id Client user ID.
	 * @return int
	 */
	public static function lifetime( int $user_id ): int {
		$days    = Lidev_Settings::get()['magic_link_days'];
		$seconds = (int) apply_filters( 'lidev_magic_link_lifetime', $days * DAY_IN_SECONDS, $user_id );

		return max( MINUTE_IN_SECONDS, min( 365 * DAY_IN_SECONDS, $seconds ) );
	}

	/**
	 * True when this IP and this email are still under the hourly cap.
	 *
	 * @since 1.3.0
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function allow_request( string $email ): bool {
		$keys = array(
			'lidev_ml_ip_' . md5( lidev_request_ip() ),
			'lidev_ml_em_' . md5( strtolower( $email ) ),
		);

		foreach ( $keys as $key ) {
			if ( (int) get_transient( $key ) >= 5 ) {
				return false;
			}
		}

		foreach ( $keys as $key ) {
			set_transient( $key, (int) get_transient( $key ) + 1, HOUR_IN_SECONDS );
		}

		return true;
	}

	/**
	 * @since 1.3.0
	 * @param string $token Raw token from the URL.
	 * @return string
	 */
	public static function url( string $token ): string {
		return add_query_arg( 'token', $token, home_url( user_trailingslashit( 'access' ) ) );
	}

	/**
	 * Validate a token, sign the client in, and return where to send them.
	 *
	 * @since 1.3.0
	 * @param string $token Raw token.
	 * @return string|WP_Error Redirect URL, or an error when the token cannot be used.
	 */
	private static function redeem( string $token ) {
		$row = self::find( $token );

		if ( ! $row ) {
			return new WP_Error( 'lidev_magic_invalid', __( 'That link has expired or is not valid.', 'li-dev-theme' ) );
		}

		$expires = date_create( (string) $row['expires_at'], wp_timezone() );

		if ( ! $expires || $expires->getTimestamp() < time() ) {
			return new WP_Error( 'lidev_magic_expired', __( 'That link has expired or is not valid.', 'li-dev-theme' ) );
		}

		$user = get_userdata( (int) $row['user_id'] );

		if ( ! $user instanceof WP_User || ! lidev_user_is_only_client( $user ) ) {
			return new WP_Error( 'lidev_magic_user', __( 'That link has expired or is not valid.', 'li-dev-theme' ) );
		}

		global $wpdb;

		$wpdb->update(
			lidev_table( 'magic' ),
			array(
				'last_used_at' => current_time( 'mysql' ),
			),
			array(
				'id' => (int) $row['id'],
			),
			array( '%s' ),
			array( '%d' )
		);

		wp_clear_auth_cookie();
		wp_set_current_user( (int) $user->ID );
		wp_set_auth_cookie( (int) $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );

		$phase_id   = absint( $row['phase_id'] ?? 0 );
		$phase      = $phase_id ? Lidev_Phase_Repository::get( $phase_id ) : null;
		$project_id = $phase ? (int) $phase['project_id'] : 0;

		lidev_log( 'magic_link_used', $project_id, $phase_id > 0 ? $phase_id : null, array() );

		if ( $phase && in_array( (int) $user->ID, lidev_project_client_ids( $project_id ), true ) ) {
			return Lidev_Routing::phase_url( $project_id, $phase_id );
		}

		return home_url( '/' );
	}

	/**
	 * @since 1.3.0
	 * @param string $token Raw token.
	 * @return array<string, mixed>|null
	 */
	private static function find( string $token ): ?array {
		$token = strtolower( trim( $token ) );

		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return null;
		}

		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . lidev_table( 'magic' ) . ' WHERE token_hash = %s LIMIT 1',
				hash( 'sha256', $token )
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}
}

/**
 * Issue a magic link for the phase email in stage 06.
 *
 * @since 1.3.0
 * @param int      $user_id  Client user ID.
 * @param int|null $phase_id Phase row ID, or null.
 * @return string|WP_Error
 */
function lidev_issue_magic_link( int $user_id, ?int $phase_id = null ) {
	return Lidev_Magic_Link::issue( $user_id, $phase_id );
}
