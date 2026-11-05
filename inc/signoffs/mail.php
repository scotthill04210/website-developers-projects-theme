<?php
/**
 * Placeholder for the phase email.
 *
 * Stage 06 replaces the body of this function. Calling it now records nothing.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lidev_send_phase_email' ) ) {
	/**
	 * Send the client sign-off email for a phase.
	 *
	 * @since 1.2.0
	 * @param int $project_id Project ID.
	 * @param int $phase_id   Phase row ID.
	 * @return void
	 */
	function lidev_send_phase_email( int $project_id, int $phase_id ): void {
		unset( $project_id, $phase_id );
	}
}
