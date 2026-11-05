<?php
/**
 * Append-only sign-off records.
 *
 * Insert and read only. Do not add update or delete methods.
 * A signed record is permanent, including for administrators.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sign-off table access.
 *
 * @since 1.1.0
 */
final class Lidev_Signoff_Repository {

	/**
	 * Store a signature. Fails if this phase already has one.
	 *
	 * @since 1.1.0
	 * @param array<string, mixed> $row Row values.
	 * @return int|WP_Error New ID, or an error.
	 */
	public static function insert( array $row ) {
		global $wpdb;

		$project_id = absint( $row['project_id'] ?? 0 );
		$phase_id   = absint( $row['phase_id'] ?? 0 );
		$user_id    = absint( $row['user_id'] ?? 0 );
		$type       = sanitize_key( (string) ( $row['signature_type'] ?? '' ) );
		$name       = sanitize_text_field( (string) ( $row['signer_name'] ?? '' ) );
		$signature  = self::sanitize_signature( $type, (string) ( $row['signature_data'] ?? '' ) );

		if ( $project_id < 1 || $phase_id < 1 || $user_id < 1 || '' === $name || '' === $signature ) {
			return new WP_Error( 'lidev_signoff_invalid', __( 'The sign-off record is incomplete.', 'li-dev-theme' ) );
		}

		if ( null !== self::for_phase( $phase_id ) ) {
			return new WP_Error( 'lidev_signoff_exists', __( 'This phase is already signed.', 'li-dev-theme' ) );
		}

		$inserted = $wpdb->insert(
			lidev_table( 'signoffs' ),
			array(
				'project_id'     => $project_id,
				'phase_id'       => $phase_id,
				'user_id'        => $user_id,
				'signer_name'    => substr( $name, 0, 191 ),
				'signature_type' => $type,
				'signature_data' => $signature,
				'record_hash'    => self::sanitize_hash( (string) ( $row['record_hash'] ?? '' ) ),
				'prev_hash'      => self::sanitize_hash( (string) ( $row['prev_hash'] ?? '' ) ),
				'ip_address'     => substr( sanitize_text_field( (string) ( $row['ip_address'] ?? '' ) ), 0, 45 ),
				'user_agent'     => substr( sanitize_text_field( (string) ( $row['user_agent'] ?? '' ) ), 0, 255 ),
				'signed_at'      => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return new WP_Error( 'lidev_signoff_insert', __( 'Could not store the sign-off.', 'li-dev-theme' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * One sign-off by ID.
	 *
	 * @since 1.1.0
	 * @param int $id Row ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . lidev_table( 'signoffs' ) . ' WHERE id = %d',
				$id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * The sign-off for a phase, if one exists.
	 *
	 * @since 1.1.0
	 * @param int $phase_id Phase row ID.
	 * @return array<string, mixed>|null
	 */
	public static function for_phase( int $phase_id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . lidev_table( 'signoffs' ) . ' WHERE phase_id = %d ORDER BY id ASC LIMIT 1',
				$phase_id
			),
			ARRAY_A
		);

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Sign-offs for a project, oldest first.
	 *
	 * @since 1.1.0
	 * @param int $project_id Project post ID.
	 * @return list<array<string, mixed>>
	 */
	public static function for_project( int $project_id ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . lidev_table( 'signoffs' ) . ' WHERE project_id = %d ORDER BY id ASC',
				$project_id
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Typed names are plain text. Drawn signatures must be a PNG data URL.
	 *
	 * @since 1.1.0
	 * @param string $type signature_type.
	 * @param string $data Raw signature payload.
	 * @return string Empty when the payload is not allowed.
	 */
	private static function sanitize_signature( string $type, string $data ): string {
		if ( 'typed' === $type ) {
			return sanitize_text_field( $data );
		}

		if ( 'drawn' !== $type ) {
			return '';
		}

		$prefix = 'data:image/png;base64,';

		if ( ! str_starts_with( $data, $prefix ) ) {
			return '';
		}

		$payload = substr( $data, strlen( $prefix ) );

		if ( ! preg_match( '/^[A-Za-z0-9+\/=]+$/', $payload ) || strlen( $data ) > 500000 ) {
			return '';
		}

		return $prefix . $payload;
	}

	/**
	 * Optional hex hash. Blank is allowed until a later stage fills the chain.
	 *
	 * @since 1.1.0
	 * @param string $hash Raw hash.
	 * @return string
	 */
	private static function sanitize_hash( string $hash ): string {
		$hash = strtolower( $hash );

		if ( '' === $hash ) {
			return '';
		}

		return preg_match( '/^[a-f0-9]{64}$/', $hash ) ? $hash : '';
	}
}
