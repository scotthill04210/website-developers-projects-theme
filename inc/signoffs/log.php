<?php
/**
 * Activity log helper.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Append one activity row for the current actor.
 *
 * Pass a null phase ID when the event is about the project as a whole.
 * The actor and IP are filled here so callers cannot spoof them.
 *
 * @since 1.1.0
 * @param string     $action     Machine action name, such as project_created.
 * @param int        $project_id Project post ID. 0 when there is no project.
 * @param int|null   $phase_id   Phase row ID, or null.
 * @param array<string, mixed> $details Extra JSON details.
 * @return int New row ID, or 0 when the write failed.
 */
function lidev_log( string $action, int $project_id = 0, ?int $phase_id = null, array $details = array() ): int {
	$user_id = get_current_user_id();
	$label   = $user_id > 0
		? (string) wp_get_current_user()->display_name
		: __( 'System', 'li-dev-theme' );

	$result = Lidev_Activity_Repository::insert(
		array(
			'actor_user_id' => $user_id > 0 ? $user_id : null,
			'actor_label'   => $label,
			'action'        => $action,
			'project_id'    => $project_id,
			'phase_id'      => $phase_id,
			'details'       => $details,
			'ip_address'    => lidev_request_ip(),
		)
	);

	return is_wp_error( $result ) ? 0 : $result;
}
