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
	 * @return array<string, mixed>
	 */
	public static function get(): array {
		$saved = get_option( self::OPTION, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$from_email = sanitize_email( (string) ( $saved['from_email'] ?? '' ) );

		if ( class_exists( 'Lidev_Graph_Mailer' ) && Lidev_Graph_Mailer::is_configured() ) {
			$from_email = Lidev_Graph_Mailer::sender();
		}
		$messages   = self::default_messages();

		foreach ( $messages as $key => $fallback ) {
			$messages[ $key ] = array_key_exists( $key, $saved )
				? lidev_sanitize_email_html( (string) $saved[ $key ] )
				: $fallback;
		}

		return array(
			'team_email'      => sanitize_email( (string) ( $saved['team_email'] ?? '' ) ),
			'reminder_days'   => self::bounded_int( $saved['reminder_days'] ?? 3, 3 ),
			'magic_link_days' => self::bounded_int( $saved['magic_link_days'] ?? 30, 30 ),
			'from_name'       => sanitize_text_field( (string) ( $saved['from_name'] ?? '' ) ),
			'from_email'      => is_email( $from_email ) ? $from_email : '',
			'message_content' => $messages['message_content'],
			'message_design'  => $messages['message_design'],
			'message_launch'  => $messages['message_launch'],
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
		$current = self::get();
		$tab     = sanitize_key( (string) ( $input['lidev_settings_tab'] ?? '' ) );
		$next    = array(
			'team_email'      => $current['team_email'],
			'reminder_days'   => $current['reminder_days'],
			'magic_link_days' => $current['magic_link_days'],
			'from_name'       => $current['from_name'],
			'from_email'      => $current['from_email'],
			'message_content' => $current['message_content'],
			'message_design'  => $current['message_design'],
			'message_launch'  => $current['message_launch'],
		);

		if ( 'email' === $tab ) {
			$team_email = sanitize_email( (string) ( $input['team_email'] ?? '' ) );
			$from_email = sanitize_email( (string) ( $input['from_email'] ?? '' ) );

			if ( '' !== $team_email && ! is_email( $team_email ) ) {
				$team_email = $current['team_email'];
			}

			if ( '' !== $from_email && ! is_email( $from_email ) ) {
				$from_email = $current['from_email'];
			}

			if ( class_exists( 'Lidev_Graph_Mailer' ) && Lidev_Graph_Mailer::is_configured() ) {
				$from_email = Lidev_Graph_Mailer::sender();
			}

			$next['team_email'] = $team_email;
			$next['from_name']  = sanitize_text_field( (string) ( $input['from_name'] ?? '' ) );
			$next['from_email'] = $from_email;
		} elseif ( 'reminders' === $tab ) {
			$next['reminder_days']   = self::bounded_int( $input['reminder_days'] ?? 3, 3 );
			$next['magic_link_days'] = self::bounded_int( $input['magic_link_days'] ?? 30, 30 );
		} elseif ( 'messages' === $tab ) {
			$next['message_content'] = self::message_field( $input, 'message_content', $current['message_content'] );
			$next['message_design']  = self::message_field( $input, 'message_design', $current['message_design'] );
			$next['message_launch']  = self::message_field( $input, 'message_launch', $current['message_launch'] );
		} else {
			return;
		}

		update_option( self::OPTION, $next, false );
	}

	/**
	 * Built-in phase messages, used until an administrator saves their own.
	 *
	 * @since 1.7.0
	 * @return array{message_content: string, message_design: string, message_launch: string}
	 */
	private static function default_messages(): array {
		return array(
			'message_content' => __( "Hi {client_name},\n\nPlease review the wireframe for {project_name}:\n{link}", 'li-dev-theme' ),
			'message_design'  => __( "Hi {client_name},\n\nPlease review the design for {project_name}:\n{link}", 'li-dev-theme' ),
			'message_launch'  => __( "Hi {client_name},\n\nPlease review {project_name} before it launches:\n{link}", 'li-dev-theme' ),
		);
	}

	/**
	 * Keep a stored message, including a blank one the administrator cleared.
	 *
	 * @since 1.7.0
	 * @param array<string, mixed> $input    Raw fields.
	 * @param string               $key      Option key.
	 * @param string               $fallback Current value when the field was not submitted.
	 * @return string
	 */
	private static function message_field( array $input, string $key, string $fallback ): string {
		if ( ! array_key_exists( $key, $input ) ) {
			return $fallback;
		}

		return lidev_sanitize_email_html( (string) $input[ $key ] );
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
