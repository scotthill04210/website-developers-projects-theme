<?php
/**
 * Append-only activity log.
 *
 * Insert and read only. Do not add update or delete methods.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Activity table access.
 *
 * @since 1.1.0
 */
final class Lidev_Activity_Repository {

	/**
	 * Store one log row.
	 *
	 * @since 1.1.0
	 * @param array<string, mixed> $row Row values.
	 * @return int|WP_Error New ID, or an error.
	 */
	public static function insert( array $row ) {
		global $wpdb;

		$action = isset( $row['action'] ) ? sanitize_key( (string) $row['action'] ) : '';

		if ( '' === $action ) {
			return new WP_Error( 'lidev_activity_action', __( 'Activity needs an action.', 'li-dev-theme' ) );
		}

		$actor_id = isset( $row['actor_user_id'] ) ? absint( $row['actor_user_id'] ) : 0;
		$phase_id = isset( $row['phase_id'] ) ? absint( $row['phase_id'] ) : 0;
		$details  = isset( $row['details'] ) && is_array( $row['details'] ) ? $row['details'] : array();
		$json     = wp_json_encode( $details );

		$inserted = $wpdb->insert(
			lidev_table( 'activity' ),
			array(
				'actor_user_id' => $actor_id > 0 ? $actor_id : null,
				'actor_label'   => substr( sanitize_text_field( (string) ( $row['actor_label'] ?? '' ) ), 0, 191 ),
				'action'        => substr( $action, 0, 64 ),
				'project_id'    => absint( $row['project_id'] ?? 0 ),
				'phase_id'      => $phase_id > 0 ? $phase_id : null,
				'details'       => is_string( $json ) ? $json : '{}',
				'ip_address'    => substr( sanitize_text_field( (string) ( $row['ip_address'] ?? '' ) ), 0, 45 ),
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'lidev_activity_insert', __( 'Could not write the activity log.', 'li-dev-theme' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * One row by ID.
	 *
	 * @since 1.1.0
	 * @param int $id Row ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . lidev_table( 'activity' ) . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? self::hydrate( $row ) : null;
	}

	/**
	 * Rows for a project, newest first.
	 *
	 * @since 1.1.0
	 * @param int $project_id Project post ID.
	 * @param int $limit      Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public static function for_project( int $project_id, int $limit = 100 ): array {
		global $wpdb;

		$limit = max( 1, min( 500, $limit ) );
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . lidev_table( 'activity' ) . ' WHERE project_id = %d ORDER BY id DESC LIMIT %d',
				$project_id,
				$limit
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Newest rows, optionally limited to one project and one action.
	 *
	 * @since 1.6.0
	 * @param int    $project_id Project ID, or 0 for every project.
	 * @param string $action     Action slug, or empty for every action.
	 * @param int    $limit      Maximum rows.
	 * @return list<array<string, mixed>>
	 */
	public static function search( int $project_id, string $action = '', int $limit = 100 ): array {
		global $wpdb;

		$limit  = max( 1, min( 500, $limit ) );
		$action = sanitize_key( $action );
		$where  = array( '1=1' );
		$args   = array();

		if ( $project_id > 0 ) {
			$where[] = 'project_id = %d';
			$args[]  = $project_id;
		}

		if ( '' !== $action ) {
			$where[] = 'action = %s';
			$args[]  = $action;
		}

		$args[] = $limit;
		$sql    = 'SELECT * FROM ' . lidev_table( 'activity' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY id DESC LIMIT %d';
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, ...$args ), ARRAY_A );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		return array_map( array( self::class, 'hydrate' ), $rows );
	}

	/**
	 * Decode the JSON details column.
	 *
	 * @since 1.1.0
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	private static function hydrate( array $row ): array {
		$details = json_decode( (string) ( $row['details'] ?? '' ), true );
		$row['details'] = is_array( $details ) ? $details : array();

		return $row;
	}
}
