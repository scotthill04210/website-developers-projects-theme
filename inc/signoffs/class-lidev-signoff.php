<?php
/**
 * Client signature and the append-only sign-off chain.
 *
 * @package LIDevTheme
 * @since   1.4.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Records one signature and checks the hash chain.
 *
 * The record hash is SHA-256 over this row only: its snapshot, signature,
 * references, and the previous row's hash. The snapshot is a copy of the
 * project name, domain, phase, link, message, approval statement, and signer
 * taken at signing time. Later edits to the project or the client do not
 * change the hash. Editing the sign-off row does.
 *
 * @since 1.4.0
 */
final class Lidev_Signoff {

	/**
	 * Font file bundled in assets/fonts. Typed signatures store this id.
	 *
	 * @since 1.4.0
	 */
	public const FONT_ID = 'great-vibes';

	/**
	 * Snapshot fields, in the order they are stored.
	 *
	 * @since 1.4.0
	 * @var list<string>
	 */
	public const SNAPSHOT_KEYS = array(
		'project_name',
		'domain',
		'phase_type',
		'phase_label',
		'external_link',
		'team_message',
		'statement',
		'signer_name',
		'signer_email',
	);

	/**
	 * Log that the assigned client opened the phase.
	 *
	 * @since 1.4.0
	 * @param WP_Post              $project Project.
	 * @param array<string, mixed> $phase   Phase row.
	 * @return void
	 */
	public static function record_view( WP_Post $project, array $phase ): void {
		if ( ! lidev_user_is_client_only() ) {
			return;
		}

		$user_id = get_current_user_id();

		if ( ! in_array( $user_id, lidev_project_client_ids( (int) $project->ID ), true ) ) {
			return;
		}

		if ( ! in_array( (string) ( $phase['status'] ?? '' ), array( 'awaiting', 'signed' ), true ) ) {
			return;
		}

		lidev_log(
			'phase_viewed',
			(int) $project->ID,
			(int) $phase['id'],
			array( 'type' => (string) ( $phase['type'] ?? '' ) )
		);
	}

	/**
	 * Save a signature. A second submit for a signed phase only shows that record.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function submit(): void {
		$phase_id   = isset( $_POST['lidev_phase_id'] ) ? absint( $_POST['lidev_phase_id'] ) : 0;
		$project_id = isset( $_POST['lidev_project_id'] ) ? absint( $_POST['lidev_project_id'] ) : 0;

		if ( ! is_user_logged_in() || $phase_id < 1 || $project_id < 1 ) {
			self::redirect( home_url( '/' ), __( 'This phase cannot be signed.', 'li-dev-theme' ) );
			return;
		}

		check_admin_referer( 'lidev_sign_phase_' . $phase_id, 'lidev_nonce' );

		$project = get_post( $project_id );
		$phase   = Lidev_Phase_Repository::get( $phase_id );
		$url     = Lidev_Routing::phase_url( $project_id, $phase_id );

		if ( ! $project instanceof WP_Post || 'lidev_project' !== $project->post_type || ! $phase || (int) $phase['project_id'] !== $project_id ) {
			self::redirect( home_url( '/' ), __( 'This phase cannot be signed.', 'li-dev-theme' ) );
			return;
		}

		if ( ! self::client_may_sign( $project_id ) ) {
			self::redirect( home_url( '/' ), __( 'This phase cannot be signed.', 'li-dev-theme' ) );
			return;
		}

		if ( 'signed' === (string) $phase['status'] ) {
			self::redirect( $url );
			return;
		}

		if ( 'awaiting' !== (string) $phase['status'] || '' === lidev_phase_statement( (string) $phase['type'] ) ) {
			self::redirect( home_url( '/' ), __( 'This phase cannot be signed.', 'li-dev-theme' ) );
			return;
		}

		$name = isset( $_POST['lidev_signer_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lidev_signer_name'] ) ) : '';
		$type = isset( $_POST['lidev_signature_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['lidev_signature_type'] ) ) : '';
		$ack  = isset( $_POST['lidev_acknowledge'] ) && '1' === (string) wp_unslash( $_POST['lidev_acknowledge'] );
		$raw  = isset( $_POST['lidev_signature'] ) ? (string) wp_unslash( $_POST['lidev_signature'] ) : '';

		if ( ! $ack || strlen( $name ) < 2 || strlen( $name ) > 191 || ! in_array( $type, array( 'typed', 'drawn' ), true ) ) {
			self::redirect( $url, __( 'Enter your full name, complete the signature, and confirm the acknowledgement.', 'li-dev-theme' ) );
			return;
		}

		$signature = Lidev_Signoff_Repository::accept_signature( $type, 'typed' === $type ? self::FONT_ID : $raw );

		if ( '' === $signature ) {
			self::redirect( $url, __( 'Enter your full name, complete the signature, and confirm the acknowledgement.', 'li-dev-theme' ) );
			return;
		}

		$result = self::store( $project, $phase_id, $name, $type, $signature );

		if ( is_wp_error( $result ) ) {
			if ( 'lidev_already_signed' === $result->get_error_code() ) {
				self::redirect( $url );
				return;
			}

			self::redirect( $url, __( 'The sign-off could not be saved. Please try again.', 'li-dev-theme' ) );
			return;
		}

		lidev_log( 'phase_signed', $project_id, $phase_id, array( 'signoff_id' => $result, 'type' => $type ) );

		/**
		 * A phase was signed. Stages 05 and 06 listen here. This stage does not mail or build a PDF.
		 *
		 * @since 1.4.0
		 * @param int $signoff_id Sign-off row ID.
		 */
		do_action( 'lidev_phase_signed', $result );

		self::redirect( $url );
	}

	/**
	 * Problems in the hash chain. An empty list means every row still matches.
	 *
	 * @since 1.4.0
	 * @return list<string>
	 */
	public static function verify_chain(): array {
		$problems = array();
		$previous = '';

		foreach ( Lidev_Signoff_Repository::all() as $row ) {
			$id = (int) $row['id'];

			if ( '' === trim( (string) ( $row['snapshot'] ?? '' ) ) ) {
				$problems[] = sprintf( 'Sign-off %d has no snapshot.', $id );
			}

			if ( strtolower( (string) ( $row['prev_hash'] ?? '' ) ) !== $previous ) {
				$problems[] = sprintf( 'Sign-off %d previous hash does not match the row before it.', $id );
			}

			$rebuilt = self::hash_row( $row );

			if ( '' === $rebuilt || $rebuilt !== strtolower( (string) ( $row['record_hash'] ?? '' ) ) ) {
				$problems[] = sprintf( 'Sign-off %d record hash does not match its contents.', $id );
			}

			$previous = strtolower( (string) ( $row['record_hash'] ?? '' ) );
		}

		return $problems;
	}

	/**
	 * Copy current project and phase text onto sign-off rows that have no snapshot.
	 *
	 * Runs once per row. Later checks never read the project or phase again.
	 *
	 * @since 1.4.0
	 * @return void
	 */
	public static function backfill_snapshots(): void {
		global $wpdb;

		$previous = '';
		$rewrote  = false;
		$table    = lidev_table( 'signoffs' );

		foreach ( Lidev_Signoff_Repository::all() as $row ) {
			$filled = false;

			if ( '' === trim( (string) ( $row['snapshot'] ?? '' ) ) ) {
				$row['snapshot'] = self::snapshot_from_current( $row );
				$filled          = true;
				$phase_id        = (int) $row['phase_id'];

				lidev_log(
					'signoff_backfilled',
					(int) $row['project_id'],
					$phase_id > 0 ? $phase_id : null,
					array( 'signoff_id' => (int) $row['id'] )
				);
			}

			if ( ! $filled ) {
				$stored_prev = strtolower( (string) ( $row['prev_hash'] ?? '' ) );
				$stored_hash = strtolower( (string) ( $row['record_hash'] ?? '' ) );

				// A later row is rewritten only when an earlier hash in this run
				// changed and this row still points at the old link.
				if ( ! $rewrote || $stored_prev === $previous ) {
					$previous = $stored_hash;
					continue;
				}
			}

			$row['prev_hash'] = $previous;
			$hash             = self::hash_row( $row );

			$wpdb->update(
				$table,
				array(
					'snapshot'    => (string) $row['snapshot'],
					'record_hash' => $hash,
					'prev_hash'   => $previous,
				),
				array( 'id' => (int) $row['id'] ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			$rewrote  = true;
			$previous = $hash;
		}
	}

	/**
	 * @since 1.4.0
	 * @param int $project_id Project ID.
	 * @return bool
	 */
	private static function client_may_sign( int $project_id ): bool {
		if ( ! lidev_user_is_client_only() ) {
			return false;
		}

		return in_array( get_current_user_id(), lidev_project_client_ids( $project_id ), true );
	}

	/**
	 * Insert the sign-off and mark the phase signed, or roll both back.
	 *
	 * @since 1.4.0
	 * @param WP_Post $project    Project.
	 * @param int     $phase_id   Phase row ID.
	 * @param string  $name       Signer name.
	 * @param string  $type       typed or drawn.
	 * @param string  $signature  Stored signature payload.
	 * @return int|WP_Error New sign-off ID.
	 */
	private static function store( WP_Post $project, int $phase_id, string $name, string $type, string $signature ) {
		global $wpdb;

		$locked = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', 'lidev_signoff_chain', 15 ) );

		if ( '1' !== (string) $locked ) {
			return new WP_Error( 'lidev_signoff_lock', __( 'The sign-off could not be saved. Please try again.', 'li-dev-theme' ) );
		}

		$wpdb->query( 'START TRANSACTION' );

		$phase = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . lidev_table( 'phases' ) . ' WHERE id = %d FOR UPDATE',
				$phase_id
			),
			ARRAY_A
		);

		if ( ! is_array( $phase ) || (int) $phase['project_id'] !== (int) $project->ID ) {
			self::release_lock( true );
			return new WP_Error( 'lidev_signoff_closed', __( 'This phase cannot be signed.', 'li-dev-theme' ) );
		}

		if ( 'signed' === (string) $phase['status'] ) {
			self::release_lock( true );
			return new WP_Error( 'lidev_already_signed', __( 'This phase is already signed.', 'li-dev-theme' ) );
		}

		if ( 'awaiting' !== (string) $phase['status'] ) {
			self::release_lock( true );
			return new WP_Error( 'lidev_signoff_closed', __( 'This phase cannot be signed.', 'li-dev-theme' ) );
		}

		$prev = $wpdb->get_var( 'SELECT record_hash FROM ' . lidev_table( 'signoffs' ) . ' ORDER BY id DESC LIMIT 1' );
		$prev = is_string( $prev ) && preg_match( '/^[a-f0-9]{64}$/', strtolower( $prev ) ) ? strtolower( $prev ) : '';
		$when = current_time( 'mysql' );
		$user_id = get_current_user_id();
		$stored  = array(
			'project_id'     => (int) $project->ID,
			'phase_id'       => $phase_id,
			'user_id'        => $user_id,
			'signer_name'    => $name,
			'signature_type' => $type,
			'signature_data' => $signature,
			'snapshot'       => self::encode_snapshot( self::capture( $project, $phase, $name, $user_id ) ),
			'prev_hash'      => $prev,
			'ip_address'     => lidev_request_ip(),
			'user_agent'     => lidev_request_user_agent(),
			'signed_at'      => $when,
		);
		$hash    = self::hash_row( $stored );

		if ( '' === $stored['snapshot'] || ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
			self::release_lock( true );
			return new WP_Error( 'lidev_signoff_hash', __( 'The sign-off could not be saved. Please try again.', 'li-dev-theme' ) );
		}

		$signoff_id = Lidev_Signoff_Repository::insert(
			array(
				'project_id'     => $stored['project_id'],
				'phase_id'       => $stored['phase_id'],
				'user_id'        => $stored['user_id'],
				'signer_name'    => $stored['signer_name'],
				'signature_type' => $stored['signature_type'],
				'signature_data' => $stored['signature_data'],
				'snapshot'       => $stored['snapshot'],
				'record_hash'    => $hash,
				'prev_hash'      => $stored['prev_hash'],
				'ip_address'     => $stored['ip_address'],
				'user_agent'     => $stored['user_agent'],
				'signed_at'      => $stored['signed_at'],
			)
		);

		if ( is_wp_error( $signoff_id ) ) {
			$code = 'lidev_signoff_exists' === $signoff_id->get_error_code() ? 'lidev_already_signed' : 'lidev_signoff_insert';
			self::release_lock( true );
			return new WP_Error( $code, $signoff_id->get_error_message() );
		}

		$moved = Lidev_Phase_Repository::transition(
			$phase_id,
			'signed',
			array( 'signoff_id' => $signoff_id )
		);

		if ( is_wp_error( $moved ) ) {
			self::release_lock( true );
			return $moved;
		}

		self::release_lock( false );

		return $signoff_id;
	}

	/**
	 * @since 1.4.0
	 * @param bool $rollback Undo the open transaction.
	 * @return void
	 */
	private static function release_lock( bool $rollback ): void {
		global $wpdb;

		$wpdb->query( $rollback ? 'ROLLBACK' : 'COMMIT' );
		$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'lidev_signoff_chain' ) );
	}

	/**
	 * Copy the values the client is approving. Used only while signing or backfilling.
	 *
	 * @since 1.4.0
	 * @param WP_Post              $project Project as it exists now.
	 * @param array<string, mixed> $phase   Phase row as it exists now.
	 * @param string               $name    Name typed on the form.
	 * @param int                  $user_id Signer.
	 * @return array<string, string>
	 */
	private static function capture( WP_Post $project, array $phase, string $name, int $user_id ): array {
		$type = (string) ( $phase['type'] ?? '' );
		$user = get_userdata( $user_id );

		return array(
			'project_name'  => (string) $project->post_title,
			'domain'        => (string) get_post_meta( $project->ID, '_lidev_domain', true ),
			'phase_type'    => $type,
			'phase_label'   => lidev_phase_panel_title( $type ),
			'external_link' => (string) ( $phase['external_link'] ?? '' ),
			'team_message'  => lidev_compose_phase_message(
				$type,
				(string) ( $phase['team_message'] ?? '' ),
				lidev_phase_message_values(
					$user instanceof WP_User ? (string) $user->display_name : $name,
					(string) $project->post_title,
					lidev_phase_panel_title( $type ),
					(string) ( $phase['external_link'] ?? '' ),
					(string) get_post_meta( $project->ID, '_lidev_domain', true )
				)
			),
			'statement'     => lidev_phase_statement( $type ),
			'signer_name'   => $name,
			'signer_email'  => $user instanceof WP_User ? sanitize_email( (string) $user->user_email ) : '',
		);
	}

	/**
	 * Build a snapshot for an older row from the project and phase as they are now.
	 *
	 * @since 1.4.0
	 * @param array<string, mixed> $row Sign-off row with an empty snapshot.
	 * @return string
	 */
	private static function snapshot_from_current( array $row ): string {
		$project = get_post( (int) $row['project_id'] );
		$phase   = Lidev_Phase_Repository::get( (int) $row['phase_id'] );
		$fields  = array(
			'project_name'  => '',
			'domain'        => '',
			'phase_type'    => '',
			'phase_label'   => '',
			'external_link' => '',
			'team_message'  => '',
			'statement'     => '',
			'signer_name'   => (string) ( $row['signer_name'] ?? '' ),
			'signer_email'  => '',
		);

		if ( $project instanceof WP_Post ) {
			$fields['project_name'] = (string) $project->post_title;
			$fields['domain']       = (string) get_post_meta( $project->ID, '_lidev_domain', true );
		}

		if ( is_array( $phase ) ) {
			$type                    = (string) ( $phase['type'] ?? '' );
			$fields['phase_type']    = $type;
			$fields['phase_label']   = lidev_phase_panel_title( $type );
			$fields['external_link'] = (string) ( $phase['external_link'] ?? '' );
			$fields['team_message']  = (string) ( $phase['team_message'] ?? '' );
			$fields['statement']     = lidev_phase_statement( $type );
		}

		$user = get_userdata( (int) ( $row['user_id'] ?? 0 ) );

		if ( $user instanceof WP_User ) {
			$fields['signer_email'] = sanitize_email( (string) $user->user_email );
		}

		return self::encode_snapshot( $fields );
	}

	/**
	 * @since 1.4.0
	 * @param array<string, string> $fields Snapshot values.
	 * @return string
	 */
	private static function encode_snapshot( array $fields ): string {
		$ordered = array();

		foreach ( self::SNAPSHOT_KEYS as $key ) {
			$ordered[ $key ] = (string) ( $fields[ $key ] ?? '' );
		}

		$json = wp_json_encode( $ordered );

		return is_string( $json ) ? $json : '';
	}

	/**
	 * Hash one stored row. Does not read the project or the phase.
	 *
	 * @since 1.4.0
	 * @param array<string, mixed> $row Sign-off row.
	 * @return string
	 */
	private static function hash_row( array $row ): string {
		$encoded = wp_json_encode(
			array(
				'project_id'       => (int) ( $row['project_id'] ?? 0 ),
				'phase_id'         => (int) ( $row['phase_id'] ?? 0 ),
				'user_id'          => (int) ( $row['user_id'] ?? 0 ),
				'signer_name'      => (string) ( $row['signer_name'] ?? '' ),
				'signature_type'   => (string) ( $row['signature_type'] ?? '' ),
				'signature_sha256' => hash( 'sha256', (string) ( $row['signature_data'] ?? '' ) ),
				'snapshot'         => (string) ( $row['snapshot'] ?? '' ),
				'prev_hash'        => strtolower( (string) ( $row['prev_hash'] ?? '' ) ),
				'ip_address'       => (string) ( $row['ip_address'] ?? '' ),
				'user_agent'       => (string) ( $row['user_agent'] ?? '' ),
				'signed_at'        => (string) ( $row['signed_at'] ?? '' ),
			)
		);

		if ( ! is_string( $encoded ) || '' === $encoded ) {
			return '';
		}

		return hash( 'sha256', $encoded );
	}

	/**
	 * @since 1.4.0
	 * @param string $url    Redirect target.
	 * @param string $detail Error text. Empty when there is nothing to report.
	 * @return void
	 */
	private static function redirect( string $url, string $detail = '' ): void {
		if ( '' !== $detail ) {
			set_transient(
				'lidev_notice_' . get_current_user_id(),
				array(
					'code'   => 'error',
					'detail' => wp_strip_all_tags( $detail ),
				),
				MINUTE_IN_SECONDS
			);
		}

		wp_safe_redirect( $url );
		exit;
	}
}

/**
 * Recompute the sign-off hash chain.
 *
 * @since 1.4.0
 * @return list<string> Empty when every row matches.
 */
function lidev_verify_signoff_chain(): array {
	return Lidev_Signoff::verify_chain();
}
