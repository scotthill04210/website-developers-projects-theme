<?php
/**
 * Sign-off settings.
 *
 * The values are edited on the front end. This class only stores and reads them.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Team notification email, reminder interval, magic-link lifetime, and From identity.
 *
 * @since 1.1.0
 */
final class Lidev_Settings {

	public const OPTION = 'lidev_signoff_settings';

	/**
	 * Stored settings with defaults.
	 *
	 * @since 1.1.0
	 * @return array{team_email: string, reminder_days: int, magic_link_days: int, from_name: string, from_email: string}
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$from_email = sanitize_email( (string) ( $saved['from_email'] ?? '' ) );

		return array(
			'team_email'      => sanitize_email( (string) ( $saved['team_email'] ?? '' ) ),
			'reminder_days'   => self::bounded_int( $saved['reminder_days'] ?? 3, 3 ),
			'magic_link_days' => self::bounded_int( $saved['magic_link_days'] ?? 30, 30 ),
			'from_name'       => sanitize_text_field( (string) ( $saved['from_name'] ?? '' ) ),
			'from_email'      => is_email( $from_email ) ? $from_email : '',
		);
	}

	/**
	 * Replace the stored settings after the caller has checked a nonce and capability.
	 *
	 * @since 1.2.0
	 * @param array<string, mixed> $input Raw fields.
	 * @return void
	 */
	public static function update( array $input ): void {
		$current    = self::get();
		$team_email = sanitize_email( (string) ( $input['team_email'] ?? '' ) );
		$from_email = sanitize_email( (string) ( $input['from_email'] ?? '' ) );

		if ( '' !== $team_email && ! is_email( $team_email ) ) {
			$team_email = $current['team_email'];
		}

		if ( '' !== $from_email && ! is_email( $from_email ) ) {
			$from_email = $current['from_email'];
		}

		update_option(
			self::OPTION,
			array(
				'team_email'      => $team_email,
				'reminder_days'   => self::bounded_int( $input['reminder_days'] ?? 3, 3 ),
				'magic_link_days' => self::bounded_int( $input['magic_link_days'] ?? 30, 30 ),
				'from_name'       => sanitize_text_field( (string) ( $input['from_name'] ?? '' ) ),
				'from_email'      => $from_email,
			),
			false
		);
	}

	/**
	 * Clamp a day count to 1–365.
	 *
	 * @since 1.1.0
	 * @param mixed $value   Raw value.
	 * @param int   $default Used when the value is empty.
	 * @return int
	 */
	private static function bounded_int( $value, int $default ): int {
		$number = absint( $value );

		if ( $number < 1 ) {
			$number = $default;
		}

		return min( 365, $number );
	}
}
