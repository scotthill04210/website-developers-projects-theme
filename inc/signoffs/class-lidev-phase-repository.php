<?php
/**
 * Phase rows.
 *
 * Status may move only from not_sent to awaiting, then to signed.
 * Signed rows cannot change status. There is no delete method.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Phase table access.
 *
 * @since 1.1.0
 */
final class Lidev_Phase_Repository {

	/**
	 * Next status for each current status. Signed is absent on purpose.
	 *
	 * @var array<string, string>
	 */
	private const NEXT_STATUS = array(
		'not_sent' => 'awaiting',
		'awaiting' => 'signed',
	);

	/**
	 * Create any missing phase rows for a project.
	 *
	 * @since 1.1.0
	 * @param int $project_id Project post ID.
	 * @return bool Whether all three rows exist afterward.
	 */
	public static function ensure_for_project( int $project_id ): bool {
		global $wpdb;

		if ( $project_id < 1 || '' === lidev_table( 'phases' ) ) {
			return false;
		}

		foreach ( lidev_phase_types() as $type ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT id FROM ' . lidev_table( 'phases' ) . ' WHERE project_id = %d AND type = %s',
					$project_id,
					$type
				)
			);

			if ( $existing ) {
				continue;
			}

			$wpdb->insert(
				lidev_table( 'phases' ),
				array(
					'project_id'     => $project_id,
					'type'           => $type,
					'status'         => 'not_sent',
					'reminder_count' => 0,
				),
				array( '%d', '%s', '%s', '%d' )
			);
		}

		return 3 === self::count_for_project( $project_id );
	}

	/**
	 * How many phase rows a project has.
	 *
	 * @since 1.1.0
	 * @param int $project_id Project post ID.
	 * @return int
	 */
	public static function count_for_project( int $project_id ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . lidev_table( 'phases' ) . ' WHERE project_id = %d',
				$project_id
			)
		);
	}

	/**
	 * Whether any phase on the project is signed.
	 *
	 * @since 1.1.0
	 * @param int $project_id Project post ID.
	 * @return bool
	 */
	public static function project_has_signed( int $project_id ): bool {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . lidev_table( 'phases' ) . ' WHERE project_id = %d AND status = %s LIMIT 1',
				$project_id,
				'signed'
			)
		);

		return (bool) $found;
	}

	/**
	 * Phases for a project in wireframe, Figma, launch order.
	 *
	 * @since 1.1.0
	 * @param int $project_id Project post ID.
	 * @return list<array<string, mixed>>
	 */
	public static function for_project( int $project_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . lidev_table( 'phases' ) . ' WHERE project_id = %d',
				$project_id
			),
			ARRAY_A
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$order = array_flip( lidev_phase_types() );

		usort(
			$rows,
			static function ( array $a, array $b ) use ( $order ): int {
				return ( $order[ $a['type'] ] ?? 99 ) <=> ( $order[ $b['type'] ] ?? 99 );
			}
		);

		return $rows;
	}

	/**
	 * One phase row.
	 *
	 * @since 1.1.0
	 * @param int $id Phase row ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . lidev_table( 'phases' ) . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Move a phase one step forward and write only the fields for that step.
	 *
	 * not_sent accepts external_link, team_message, sent_at, and sent_by on the way to awaiting.
	 * awaiting accepts signed_at and signoff_id on the way to signed.
	 *
	 * @since 1.1.0
	 * @param int                  $phase_id Phase row ID.
	 * @param string               $to_status Target status.
	 * @param array<string, mixed> $data Fields allowed for that step.
	 * @return true|WP_Error
	 */
	public static function transition( int $phase_id, string $to_status, array $data = array() ) {
		$phase = self::get( $phase_id );

		if ( ! $phase ) {
			return new WP_Error( 'lidev_phase_missing', __( 'That phase does not exist.', 'li-dev-theme' ) );
		}

		$from = (string) $phase['status'];

		if ( 'signed' === $from ) {
			return new WP_Error( 'lidev_phase_locked', __( 'A signed phase cannot be changed.', 'li-dev-theme' ) );
		}

		if ( ( self::NEXT_STATUS[ $from ] ?? '' ) !== $to_status ) {
			return new WP_Error( 'lidev_phase_transition', __( 'That phase status change is not allowed.', 'li-dev-theme' ) );
		}

		$update = array( 'status' => $to_status );
		$format = array( '%s' );

		if ( 'awaiting' === $to_status ) {
			$update['external_link'] = esc_url_raw( (string) ( $data['external_link'] ?? '' ) );
			$update['team_message']  = wp_kses_post( (string) ( $data['team_message'] ?? '' ) );
			$update['sent_at']       = current_time( 'mysql' );
			$update['sent_by']       = absint( $data['sent_by'] ?? get_current_user_id() );
			$format                  = array( '%s', '%s', '%s', '%s', '%d' );
		}

		if ( 'signed' === $to_status ) {
			$signoff_id = absint( $data['signoff_id'] ?? 0 );

			if ( $signoff_id < 1 ) {
				return new WP_Error( 'lidev_phase_signoff', __( 'A signed phase needs a sign-off record.', 'li-dev-theme' ) );
			}

			$update['signed_at']  = current_time( 'mysql' );
			$update['signoff_id'] = $signoff_id;
			$format               = array( '%s', '%s', '%d' );
		}

		global $wpdb;

		$updated = $wpdb->update(
			lidev_table( 'phases' ),
			$update,
			array(
				'id'     => $phase_id,
				'status' => $from,
			),
			$format,
			array( '%d', '%s' )
		);

		if ( 1 !== $updated ) {
			return new WP_Error( 'lidev_phase_transition', __( 'That phase status change is not allowed.', 'li-dev-theme' ) );
		}

		return true;
	}

	/**
	 * Send or re-send a phase.
	 *
	 * not_sent moves to awaiting. awaiting keeps its status and updates the link and message.
	 * signed is rejected.
	 *
	 * @since 1.2.0
	 * @param int    $phase_id Phase row ID.
	 * @param string $link     Validated external URL.
	 * @param string $message  Message to the client.
	 * @param int    $user_id  Team user who is sending.
	 * @return true|WP_Error
	 */
	public static function send( int $phase_id, string $link, string $message, int $user_id ) {
		$phase = self::get( $phase_id );

		if ( ! $phase ) {
			return new WP_Error( 'lidev_phase_missing', __( 'That phase does not exist.', 'li-dev-theme' ) );
		}

		if ( 'signed' === $phase['status'] ) {
			return new WP_Error( 'lidev_phase_locked', __( 'A signed phase cannot be changed.', 'li-dev-theme' ) );
		}

		if ( 'not_sent' === $phase['status'] ) {
			return self::transition(
				$phase_id,
				'awaiting',
				array(
					'external_link' => $link,
					'team_message'  => $message,
					'sent_by'       => $user_id,
				)
			);
		}

		if ( 'awaiting' !== $phase['status'] ) {
			return new WP_Error( 'lidev_phase_transition', __( 'That phase status change is not allowed.', 'li-dev-theme' ) );
		}

		global $wpdb;

		$updated = $wpdb->update(
			lidev_table( 'phases' ),
			array(
				'external_link' => esc_url_raw( $link ),
				'team_message'  => wp_kses_post( $message ),
				'sent_at'       => current_time( 'mysql' ),
				'sent_by'       => $user_id,
			),
			array(
				'id'     => $phase_id,
				'status' => 'awaiting',
			),
			array( '%s', '%s', '%s', '%d' ),
			array( '%d', '%s' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'lidev_phase_transition', __( 'That phase status change is not allowed.', 'li-dev-theme' ) );
		}

		$again = self::get( $phase_id );

		if ( ! $again || 'awaiting' !== $again['status'] ) {
			return new WP_Error( 'lidev_phase_locked', __( 'A signed phase cannot be changed.', 'li-dev-theme' ) );
		}

		return true;
	}
}
