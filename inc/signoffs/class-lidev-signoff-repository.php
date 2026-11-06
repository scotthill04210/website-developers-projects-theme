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
		$snapshot   = self::sanitize_snapshot( (string) ( $row['snapshot'] ?? '' ) );

		if ( $project_id < 1 || $phase_id < 1 || $user_id < 1 || '' === $name || '' === $signature || '' === $snapshot ) {
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
				'signed_at'      => self::signed_at( (string) ( $row['signed_at'] ?? '' ) ),
				'snapshot'       => $snapshot,
			),
			array( '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
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
	 * Every sign-off, oldest first. Used to check the hash chain.
	 *
	 * @since 1.4.0
	 * @return list<array<string, mixed>>
	 */
	public static function all(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			'SELECT * FROM ' . lidev_table( 'signoffs' ) . ' ORDER BY id ASC',
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Signature value that is safe to store. Empty when it is not allowed.
	 *
	 * @since 1.4.0
	 * @param string $type signature_type.
	 * @param string $data Raw signature payload.
	 * @return string
	 */
	public static function accept_signature( string $type, string $data ): string {
		return self::sanitize_signature( $type, $data );
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

		$binary = base64_decode( $payload, true );

		if ( ! is_string( $binary ) || ! self::png_has_ink( $binary ) ) {
			return '';
		}

		return $prefix . $payload;
	}

	/**
	 * The frozen JSON copied at signing time. Empty when the shape is wrong.
	 *
	 * @since 1.4.0
	 * @param string $json Raw snapshot.
	 * @return string
	 */
	private static function sanitize_snapshot( string $json ): string {
		$decoded = json_decode( $json, true );

		if ( ! is_array( $decoded ) ) {
			return '';
		}

		foreach ( Lidev_Signoff::SNAPSHOT_KEYS as $key ) {
			if ( ! array_key_exists( $key, $decoded ) || ! is_string( $decoded[ $key ] ) ) {
				return '';
			}
		}

		return $json;
	}

	/**
	 * Use the caller's timestamp when it is a MySQL datetime, so the hash matches the row.
	 *
	 * @since 1.4.0
	 * @param string $value Proposed signed_at.
	 * @return string
	 */
	private static function signed_at( string $value ): string {
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value ) ) {
			return $value;
		}

		return current_time( 'mysql' );
	}

	/**
	 * A drawn signature must be a PNG with visible ink, not an empty canvas.
	 *
	 * @since 1.4.0
	 * @param string $binary Decoded PNG bytes.
	 * @return bool
	 */
	private static function png_has_ink( string $binary ): bool {
		if ( strlen( $binary ) < 32 || strlen( $binary ) > 400000 || ! str_starts_with( $binary, "\x89PNG\r\n\x1a\n" ) ) {
			return false;
		}

		if ( ! function_exists( 'imagecreatefromstring' ) ) {
			return false;
		}

		$image = imagecreatefromstring( $binary );

		if ( false === $image ) {
			return false;
		}

		$width  = imagesx( $image );
		$height = imagesy( $image );

		if ( $width < 1 || $height < 1 || $width > 2000 || $height > 1000 ) {
			imagedestroy( $image );
			return false;
		}

		$ink  = 0;
		$step = max( 1, (int) floor( max( $width, $height ) / 400 ) );

		for ( $y = 0; $y < $height; $y += $step ) {
			for ( $x = 0; $x < $width; $x += $step ) {
				$color = imagecolorat( $image, $x, $y );

				if ( imageistruecolor( $image ) ) {
					$alpha = ( $color >> 24 ) & 0x7F;
					$red   = ( $color >> 16 ) & 0xFF;
					$green = ( $color >> 8 ) & 0xFF;
					$blue  = $color & 0xFF;
				} else {
					$rgba  = imagecolorsforindex( $image, $color );
					$alpha = (int) ( $rgba['alpha'] ?? 127 );
					$red   = (int) ( $rgba['red'] ?? 255 );
					$green = (int) ( $rgba['green'] ?? 255 );
					$blue  = (int) ( $rgba['blue'] ?? 255 );
				}

				if ( $alpha < 80 && ( $red < 245 || $green < 245 || $blue < 245 ) ) {
					++$ink;
				}

				if ( $ink >= 4 ) {
					imagedestroy( $image );
					return true;
				}
			}
		}

		imagedestroy( $image );

		return false;
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
