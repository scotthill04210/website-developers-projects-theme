<?php
/**
 * Shared sign-off helpers.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom table name. Only the three sign-off tables are allowed.
 *
 * @since 1.1.0
 * @param string $suffix phases, signoffs, or activity.
 * @return string
 */
function lidev_table( string $suffix ): string {
	$allowed = array( 'phases', 'signoffs', 'activity' );

	if ( ! in_array( $suffix, $allowed, true ) ) {
		return '';
	}

	global $wpdb;

	return $wpdb->prefix . 'lidev_' . $suffix;
}

/**
 * Phase slugs in product order.
 *
 * @since 1.1.0
 * @return list<string>
 */
function lidev_phase_types(): array {
	return array( 'content', 'design', 'launch' );
}

/**
 * Human label for a phase slug.
 *
 * @since 1.1.0
 * @param string $type Phase slug.
 * @return string
 */
function lidev_phase_label( string $type ): string {
	$labels = array(
		'content' => __( 'Wireframe', 'li-dev-theme' ),
		'design'  => __( 'Figma', 'li-dev-theme' ),
		'launch'  => __( 'Ready to Launch', 'li-dev-theme' ),
	);

	return $labels[ $type ] ?? $type;
}

/**
 * Field label for the link a phase sends to the client.
 *
 * @since 1.2.0
 * @param string $type Phase slug.
 * @return string
 */
function lidev_phase_link_label( string $type ): string {
	$labels = array(
		'content' => __( 'Wireframe link', 'li-dev-theme' ),
		'design'  => __( 'Figma link', 'li-dev-theme' ),
		'launch'  => __( 'Staging site link', 'li-dev-theme' ),
	);

	return $labels[ $type ] ?? __( 'Link', 'li-dev-theme' );
}

/**
 * Short status label for chips and filters.
 *
 * @since 1.2.0
 * @param string $status Phase status.
 * @return string
 */
function lidev_phase_status_label( string $status ): string {
	$labels = array(
		'not_sent' => __( 'Not sent', 'li-dev-theme' ),
		'awaiting' => __( 'Awaiting', 'li-dev-theme' ),
		'signed'   => __( 'Signed', 'li-dev-theme' ),
	);

	return $labels[ $status ] ?? $status;
}

/**
 * Require an http or https URL.
 *
 * @since 1.2.0
 * @param string $url Raw URL.
 * @return string Empty when the value is not a usable URL.
 */
function lidev_sanitize_external_url( string $url ): string {
	$url = trim( $url );

	if ( ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}

	$clean = esc_url_raw( $url );

	return filter_var( $clean, FILTER_VALIDATE_URL ) ? $clean : '';
}

/**
 * Allowed phase statuses, in transition order.
 *
 * @since 1.1.0
 * @return list<string>
 */
function lidev_phase_statuses(): array {
	return array( 'not_sent', 'awaiting', 'signed' );
}

/**
 * Client user IDs stored on a project.
 *
 * @since 1.1.0
 * @param int $project_id Project post ID.
 * @return list<int>
 */
function lidev_project_client_ids( int $project_id ): array {
	$ids = get_post_meta( $project_id, '_lidev_client_ids', true );

	if ( ! is_array( $ids ) ) {
		return array();
	}

	$clean = array();

	foreach ( $ids as $id ) {
		$id = absint( $id );

		if ( $id > 0 ) {
			$clean[] = $id;
		}
	}

	return array_values( array_unique( $clean ) );
}

/**
 * Keep only IDs of users that exist.
 *
 * @since 1.1.0
 * @param mixed $ids Raw IDs.
 * @return list<int>
 */
function lidev_sanitize_user_ids( $ids ): array {
	if ( ! is_array( $ids ) ) {
		return array();
	}

	$clean = array();

	foreach ( $ids as $id ) {
		$id = absint( $id );

		if ( $id > 0 && get_userdata( $id ) ) {
			$clean[] = $id;
		}
	}

	return array_values( array_unique( $clean ) );
}

/**
 * Whether a user may see a project. Team capability wins; clients must be assigned.
 *
 * @since 1.1.0
 * @param int $project_id Project post ID.
 * @param int $user_id    User ID. 0 uses the current user.
 * @return bool
 */
function lidev_user_can_view_project( int $project_id, int $user_id = 0 ): bool {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id < 1 || 'lidev_project' !== get_post_type( $project_id ) ) {
		return false;
	}

	if ( user_can( $user_id, 'lidev_view_all_projects' ) ) {
		return true;
	}

	if ( ! user_can( $user_id, 'lidev_view_own_projects' ) ) {
		return false;
	}

	return in_array( $user_id, lidev_project_client_ids( $project_id ), true );
}

/**
 * A client account with no team or admin capability.
 *
 * @since 1.1.0
 * @param int $user_id User ID. 0 uses the current user.
 * @return bool
 */
function lidev_user_is_client_only( int $user_id = 0 ): bool {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id < 1 || ! user_can( $user_id, 'lidev_view_own_projects' ) ) {
		return false;
	}

	$staff_caps = array( 'lidev_view_all_projects', 'lidev_manage_projects', 'manage_options' );

	foreach ( $staff_caps as $cap ) {
		if ( user_can( $user_id, $cap ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Remote address for the activity log. Not taken from forwarded headers.
 *
 * @since 1.1.0
 * @return string
 */
function lidev_request_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';

	return substr( $ip, 0, 45 );
}

/**
 * Browser string for a sign-off record.
 *
 * @since 1.1.0
 * @return string
 */
function lidev_request_user_agent(): string {
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	return substr( $agent, 0, 255 );
}
