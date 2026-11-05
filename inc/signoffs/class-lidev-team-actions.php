<?php
/**
 * Front-end form handlers for Team screens.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * admin-post.php actions. These are not wp-admin screens.
 *
 * @since 1.2.0
 */
final class Lidev_Team_Actions {

	/**
	 * Add a project and its first client.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function create_project(): void {
		self::require_cap( 'lidev_manage_projects' );
		check_admin_referer( 'lidev_create_project', 'lidev_nonce' );

		$title    = isset( $_POST['lidev_title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lidev_title'] ) ) : '';
		$domain   = isset( $_POST['lidev_domain'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lidev_domain'] ) ) : '';
		$names    = isset( $_POST['lidev_client_name'] ) ? (array) wp_unslash( $_POST['lidev_client_name'] ) : array();
		$emails   = isset( $_POST['lidev_client_email'] ) ? (array) wp_unslash( $_POST['lidev_client_email'] ) : array();
		$name     = sanitize_text_field( (string) ( $names[0] ?? '' ) );
		$email    = sanitize_email( (string) ( $emails[0] ?? '' ) );

		if ( ! is_email( $email ) ) {
			self::fail( Lidev_Routing::new_url(), __( 'Enter a valid client email.', 'li-dev-theme' ) );
		}

		$existing = get_user_by( 'email', $email );

		if ( $existing instanceof WP_User && ! lidev_user_is_only_client( $existing ) ) {
			self::fail(
				Lidev_Routing::new_url(),
				sprintf(
					/* translators: %s: email address. */
					__( '%s already belongs to an account that is not a client.', 'li-dev-theme' ),
					$email
				)
			);
		}

		if ( ! $existing instanceof WP_User && '' === $name ) {
			self::fail( Lidev_Routing::new_url(), __( 'A name is required for a new client.', 'li-dev-theme' ) );
		}

		$project_id = lidev_create_project(
			array(
				'title'      => $title,
				'domain'     => $domain,
				'client_ids' => $existing instanceof WP_User ? array( (int) $existing->ID ) : array(),
				'created_by' => get_current_user_id(),
			)
		);

		if ( is_wp_error( $project_id ) ) {
			self::fail( Lidev_Routing::new_url(), $project_id->get_error_message() );
		}

		$client = lidev_find_or_create_client( $email, $name, (int) $project_id );

		if ( is_wp_error( $client ) ) {
			self::fail( Lidev_Routing::project_url( (int) $project_id ), $client->get_error_message() );
		}

		$ids = lidev_project_client_ids( (int) $project_id );

		if ( ! in_array( $client['id'], $ids, true ) ) {
			$ids[] = $client['id'];
			update_post_meta( (int) $project_id, '_lidev_client_ids', $ids );
		}

		lidev_log(
			'client_attached',
			(int) $project_id,
			null,
			array(
				'user_id' => $client['id'],
			)
		);

		self::finish( Lidev_Routing::project_url( (int) $project_id ), 'created' );
	}

	/**
	 * Save the project name, domain, and client email list.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function update_project(): void {
		self::require_cap( 'lidev_manage_projects' );
		check_admin_referer( 'lidev_update_project', 'lidev_nonce' );

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		self::require_project( $project_id );

		$emails = array_values( isset( $_POST['lidev_client_email'] ) ? wp_unslash( (array) $_POST['lidev_client_email'] ) : array() );
		$names  = array_values( isset( $_POST['lidev_client_name'] ) ? wp_unslash( (array) $_POST['lidev_client_name'] ) : array() );
		$ids    = array();

		foreach ( $emails as $index => $raw_email ) {
			$email = sanitize_email( (string) $raw_email );

			if ( '' === $email ) {
				continue;
			}

			$name   = sanitize_text_field( (string) ( $names[ $index ] ?? '' ) );
			$client = lidev_find_or_create_client( $email, $name, $project_id );

			if ( is_wp_error( $client ) ) {
				self::fail( Lidev_Routing::edit_url( $project_id ), $client->get_error_message() );
			}

			$ids[] = $client['id'];
		}

		$ids = array_values( array_unique( $ids ) );

		if ( ! $ids ) {
			self::fail( Lidev_Routing::edit_url( $project_id ), __( 'Add at least one client email.', 'li-dev-theme' ) );
		}

		$before = lidev_project_client_ids( $project_id );
		$result = lidev_update_project(
			$project_id,
			array(
				'title'      => isset( $_POST['lidev_title'] ) ? wp_unslash( (string) $_POST['lidev_title'] ) : '',
				'domain'     => isset( $_POST['lidev_domain'] ) ? wp_unslash( (string) $_POST['lidev_domain'] ) : '',
				'client_ids' => $ids,
			)
		);

		if ( is_wp_error( $result ) ) {
			self::fail( Lidev_Routing::edit_url( $project_id ), $result->get_error_message() );
		}

		foreach ( array_diff( $ids, $before ) as $user_id ) {
			lidev_log(
				'client_attached',
				$project_id,
				null,
				array(
					'user_id' => (int) $user_id,
				)
			);
		}

		self::finish( Lidev_Routing::project_url( $project_id ), 'updated' );
	}

	/**
	 * Add one more client to a project.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function add_client(): void {
		self::require_cap( 'lidev_manage_projects' );
		check_admin_referer( 'lidev_add_client', 'lidev_nonce' );

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		self::require_project( $project_id );

		$name   = isset( $_POST['lidev_client_name'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['lidev_client_name'] ) ) : '';
		$email  = isset( $_POST['lidev_client_email'] ) ? sanitize_email( wp_unslash( (string) $_POST['lidev_client_email'] ) ) : '';
		$client = lidev_find_or_create_client( $email, $name, $project_id );

		if ( is_wp_error( $client ) ) {
			self::fail( Lidev_Routing::project_url( $project_id ), $client->get_error_message() );
		}

		$ids = lidev_project_client_ids( $project_id );

		if ( in_array( $client['id'], $ids, true ) ) {
			self::finish( Lidev_Routing::project_url( $project_id ), 'already' );
		}

		$ids[] = $client['id'];
		update_post_meta( $project_id, '_lidev_client_ids', $ids );

		lidev_log(
			'client_attached',
			$project_id,
			null,
			array(
				'user_id' => $client['id'],
			)
		);

		self::finish( Lidev_Routing::project_url( $project_id ), 'client_added' );
	}

	/**
	 * Send or re-send one phase.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function send_phase(): void {
		self::require_cap( 'lidev_send_phases' );

		$project_id = isset( $_POST['project_id'] ) ? absint( $_POST['project_id'] ) : 0;
		$phase_id   = isset( $_POST['phase_id'] ) ? absint( $_POST['phase_id'] ) : 0;

		check_admin_referer( 'lidev_send_phase_' . $phase_id, 'lidev_nonce' );
		self::require_project( $project_id );

		$phase = Lidev_Phase_Repository::get( $phase_id );

		if ( ! $phase || (int) $phase['project_id'] !== $project_id ) {
			self::fail( Lidev_Routing::project_url( $project_id ), __( 'That phase does not belong to this project.', 'li-dev-theme' ) );
		}

		$link = isset( $_POST['lidev_link'] ) ? lidev_sanitize_external_url( wp_unslash( (string) $_POST['lidev_link'] ) ) : '';

		if ( '' === $link ) {
			self::fail( Lidev_Routing::project_url( $project_id ), __( 'Enter a full link starting with http:// or https://.', 'li-dev-theme' ) );
		}

		$message = isset( $_POST['lidev_message'] ) ? wp_kses_post( wp_unslash( (string) $_POST['lidev_message'] ) ) : '';
		$was     = (string) $phase['status'];
		$result  = Lidev_Phase_Repository::send( $phase_id, $link, $message, get_current_user_id() );

		if ( is_wp_error( $result ) ) {
			self::fail( Lidev_Routing::project_url( $project_id ), $result->get_error_message() );
		}

		lidev_log(
			'awaiting' === $was ? 'phase_resent' : 'phase_sent',
			$project_id,
			$phase_id,
			array(
				'type' => (string) $phase['type'],
				'link' => $link,
			)
		);

		lidev_send_phase_email( $project_id, $phase_id );
		self::finish( Lidev_Routing::project_url( $project_id ), 'awaiting' === $was ? 'resent' : 'sent' );
	}

	/**
	 * Save front-end sign-off settings.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function save_settings(): void {
		self::require_cap( 'lidev_manage_settings' );
		check_admin_referer( 'lidev_save_settings', 'lidev_nonce' );

		foreach ( array( 'team_email', 'from_email' ) as $key ) {
			$raw = isset( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) ) : '';

			if ( '' !== $raw && ! is_email( $raw ) ) {
				self::fail( Lidev_Routing::settings_url(), __( 'Enter a valid email address.', 'li-dev-theme' ) );
			}
		}

		Lidev_Settings::update( wp_unslash( $_POST ) );
		self::finish( Lidev_Routing::settings_url(), 'settings' );
	}

	/**
	 * @since 1.2.0
	 * @param string $cap Capability.
	 * @return void
	 */
	private static function require_cap( string $cap ): void {
		if ( ! current_user_can( $cap ) ) {
			wp_die( esc_html__( 'You cannot do that.', 'li-dev-theme' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * @since 1.2.0
	 * @param int $project_id Project ID.
	 * @return void
	 */
	private static function require_project( int $project_id ): void {
		if ( 'lidev_project' !== get_post_type( $project_id ) ) {
			wp_die( esc_html__( 'That project does not exist.', 'li-dev-theme' ), '', array( 'response' => 404 ) );
		}
	}

	/**
	 * @since 1.2.0
	 * @param string $url     Redirect target.
	 * @param string $message Error text.
	 * @return void
	 */
	private static function fail( string $url, string $message ): void {
		self::remember( 'error', $message );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @since 1.2.0
	 * @param string $url  Redirect target.
	 * @param string $code Notice code.
	 * @return void
	 */
	private static function finish( string $url, string $code ): void {
		self::remember( $code, '' );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @since 1.2.0
	 * @param string $code   Notice code.
	 * @param string $detail Extra text for errors.
	 * @return void
	 */
	private static function remember( string $code, string $detail ): void {
		set_transient(
			'lidev_notice_' . get_current_user_id(),
			array(
				'code'   => sanitize_key( $code ),
				'detail' => wp_strip_all_tags( $detail ),
			),
			MINUTE_IN_SECONDS
		);
	}
}
