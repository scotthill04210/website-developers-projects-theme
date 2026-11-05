<?php
/**
 * Find or create a client account for a project.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Attach an existing client, or create one. Any other role is refused.
 *
 * @since 1.2.0
 * @param string $email      Client email.
 * @param string $name       Display name, required when the user is new.
 * @param int    $project_id Project to mention in the log. 0 logs without one.
 * @return array{id: int, created: bool}|WP_Error
 */
function lidev_find_or_create_client( string $email, string $name, int $project_id = 0 ) {
	$email = sanitize_email( $email );
	$name  = sanitize_text_field( $name );

	if ( ! is_email( $email ) ) {
		return new WP_Error( 'lidev_client_email', __( 'Enter a valid client email.', 'li-dev-theme' ) );
	}

	$existing = get_user_by( 'email', $email );

	if ( $existing instanceof WP_User ) {
		if ( ! lidev_user_is_only_client( $existing ) ) {
			return new WP_Error(
				'lidev_client_role',
				sprintf(
					/* translators: %s: email address. */
					__( '%s already belongs to an account that is not a client.', 'li-dev-theme' ),
					$email
				)
			);
		}

		return array(
			'id'      => (int) $existing->ID,
			'created' => false,
		);
	}

	if ( '' === $name ) {
		return new WP_Error( 'lidev_client_name', __( 'A name is required for a new client.', 'li-dev-theme' ) );
	}

	if ( ! current_user_can( 'lidev_create_clients' ) ) {
		return new WP_Error( 'lidev_client_cap', __( 'You cannot create client accounts.', 'li-dev-theme' ) );
	}

	$user_id = wp_insert_user(
		array(
			'user_login'   => lidev_unique_client_login( $email ),
			'user_email'   => $email,
			'display_name' => $name,
			'first_name'   => $name,
			'user_pass'    => wp_generate_password( 32, true, true ),
			'role'         => 'lidev_client',
		)
	);

	if ( is_wp_error( $user_id ) ) {
		return $user_id;
	}

	lidev_log(
		'client_created',
		$project_id,
		null,
		array(
			'user_id' => (int) $user_id,
			'email'   => $email,
		)
	);

	return array(
		'id'      => (int) $user_id,
		'created' => true,
	);
}

/**
 * A client account is only the Client role, with no staff role mixed in.
 *
 * @since 1.2.0
 * @param WP_User $user User to test.
 * @return bool
 */
function lidev_user_is_only_client( WP_User $user ): bool {
	$roles = array_values( $user->roles );

	return array( 'lidev_client' ) === $roles;
}

/**
 * Build a unique login from the mailbox name.
 *
 * @since 1.2.0
 * @param string $email Email address.
 * @return string
 */
function lidev_unique_client_login( string $email ): string {
	$mailbox = strstr( $email, '@', true );
	$base    = sanitize_user( is_string( $mailbox ) ? $mailbox : 'client', true );

	if ( '' === $base ) {
		$base = 'client';
	}

	$login = $base;
	$count = 1;

	while ( username_exists( $login ) ) {
		$login = $base . $count;
		++$count;
	}

	return $login;
}
