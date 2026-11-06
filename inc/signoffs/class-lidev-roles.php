<?php
/**
 * Roles, capabilities, and the client admin lock.
 *
 * Permission checks use capabilities, not role names.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sign-off roles.
 *
 * @since 1.1.0
 */
final class Lidev_Roles {

	/**
	 * Capabilities granted to Team and to administrators.
	 *
	 * @since 1.1.0
	 * @return list<string>
	 */
	public static function staff_caps(): array {
		return array(
			'lidev_manage_projects',
			'lidev_view_all_projects',
			'lidev_send_phases',
			'lidev_view_activity',
			'lidev_create_clients',
		);
	}

	/**
	 * Create or update the Team and Client roles, and grant staff caps to administrators.
	 *
	 * Safe to run more than once. The Client role is reset to its allow-list so it
	 * cannot accumulate extra capabilities.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	public static function sync(): void {
		$team_caps = array( 'read' => true );

		foreach ( self::staff_caps() as $cap ) {
			$team_caps[ $cap ] = true;
		}

		self::sync_role(
			'lidev_team',
			__( 'Local Image Team', 'li-dev-theme' ),
			$team_caps
		);

		self::sync_role(
			'lidev_client',
			__( 'Client', 'li-dev-theme' ),
			array(
				'read'                    => true,
				'lidev_view_own_projects' => true,
			)
		);

		$admin = get_role( 'administrator' );

		if ( $admin ) {
			foreach ( self::staff_caps() as $cap ) {
				$admin->add_cap( $cap );
			}

			$admin->add_cap( 'lidev_manage_settings' );
			$admin->add_cap( 'lidev_view_own_projects' );
		}

		self::refresh_current_user();
	}

	/**
	 * Send Team and Client accounts away from wp-admin screens.
	 *
	 * admin-ajax.php and admin-post.php stay available so front-end forms can post.
	 * Administrators are the only accounts that keep wp-admin.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function block_client_admin(): void {
		if ( wp_doing_cron() ) {
			return;
		}

		global $pagenow;

		if ( in_array( (string) $pagenow, array( 'admin-ajax.php', 'admin-post.php' ), true ) ) {
			return;
		}

		if ( ! self::is_locked_from_admin() ) {
			return;
		}

		wp_safe_redirect( home_url( '/' ) );
		exit;
	}

	/**
	 * Team and Client accounts do not use the REST API.
	 *
	 * Logged-out requests are left for the privacy redirect. Administrators pass through.
	 *
	 * @since 1.7.0
	 * @param mixed           $result  Response from an earlier callback.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function block_rest( $result, $server, $request ) {
		unset( $server, $request );

		if ( $result instanceof WP_Error || ! self::is_locked_from_admin() ) {
			return $result;
		}

		return new WP_Error(
			'lidev_rest_forbidden',
			__( 'You cannot do that.', 'li-dev-theme' ),
			array( 'status' => 403 )
		);
	}

	/**
	 * Team and Client accounts use the front end. Administrators do not.
	 *
	 * @since 1.2.0
	 * @param int $user_id User ID. 0 uses the current user.
	 * @return bool
	 */
	public static function is_locked_from_admin( int $user_id = 0 ): bool {
		$user_id = $user_id > 0 ? $user_id : get_current_user_id();

		if ( $user_id < 1 || user_can( $user_id, 'manage_options' ) ) {
			return false;
		}

		return user_can( $user_id, 'lidev_view_all_projects' ) || user_can( $user_id, 'lidev_view_own_projects' );
	}

	/**
	 * Hide the admin bar for client accounts.
	 *
	 * @since 1.1.0
	 * @param bool $show Whether the bar is showing.
	 * @return bool
	 */
	public static function filter_admin_bar( $show ) {
		if ( self::is_locked_from_admin() ) {
			return false;
		}

		return $show;
	}

	/**
	 * Keep clients from landing in wp-admin after they sign in.
	 *
	 * @since 1.1.0
	 * @param string           $redirect           Calculated redirect.
	 * @param string           $requested_redirect Requested redirect.
	 * @param WP_User|WP_Error $user               The user logging in.
	 * @return string
	 */
	public static function filter_login_redirect( $redirect, $requested_redirect, $user ): string {
		unset( $requested_redirect );

		if ( $user instanceof WP_User && self::is_locked_from_admin( (int) $user->ID ) ) {
			return home_url( '/' );
		}

		return is_string( $redirect ) ? $redirect : '';
	}

	/**
	 * Replace a role's capabilities with the given map.
	 *
	 * @since 1.1.0
	 * @param string               $slug  Role slug.
	 * @param string               $label Display name.
	 * @param array<string, bool>  $caps  Capability map.
	 * @return void
	 */
	private static function sync_role( string $slug, string $label, array $caps ): void {
		$role = get_role( $slug );

		if ( ! $role ) {
			add_role( $slug, $label, $caps );
			return;
		}

		foreach ( array_keys( $role->capabilities ) as $existing ) {
			if ( empty( $caps[ $existing ] ) ) {
				$role->remove_cap( $existing );
			}
		}

		foreach ( $caps as $cap => $grant ) {
			if ( $grant ) {
				$role->add_cap( $cap );
			}
		}
	}

	/**
	 * Rebuild the current user's caps after a role change in this request.
	 *
	 * @since 1.1.0
	 * @return void
	 */
	private static function refresh_current_user(): void {
		$user = wp_get_current_user();

		if ( $user instanceof WP_User && $user->ID ) {
			$user->get_role_caps();
		}
	}
}
