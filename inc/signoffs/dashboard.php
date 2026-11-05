<?php
/**
 * Team dashboard grouping.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Projects grouped by client, after search and status filters.
 *
 * @since 1.2.0
 * @param string $search Search text.
 * @param string $status Phase status, or an empty string for all.
 * @return list<array{label: string, email: string, projects: list<array<string, mixed>>}>
 */
function lidev_dashboard_groups( string $search, string $status ): array {
	if ( ! current_user_can( 'lidev_view_all_projects' ) ) {
		return array();
	}

	$search = strtolower( trim( $search ) );
	$status = sanitize_key( $status );

	if ( ! in_array( $status, lidev_phase_statuses(), true ) ) {
		$status = '';
	}

	$posts  = get_posts(
		array(
			'post_type'      => 'lidev_project',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	$groups = array();

	foreach ( $posts as $post ) {
		if ( ! $post instanceof WP_Post ) {
			continue;
		}

		$phases = Lidev_Phase_Repository::for_project( (int) $post->ID );

		if ( '' !== $status && ! lidev_project_has_status( $phases, $status ) ) {
			continue;
		}

		$clients = lidev_project_clients( (int) $post->ID );
		$card    = lidev_project_card( $post, $phases );

		if ( '' !== $search && ! lidev_dashboard_matches( $search, $card, $clients, $phases ) ) {
			continue;
		}

		if ( ! $clients ) {
			if ( ! isset( $groups[0] ) ) {
				$groups[0] = array(
					'label'    => __( 'No client yet', 'li-dev-theme' ),
					'email'    => '',
					'projects' => array(),
				);
			}

			$groups[0]['projects'][] = $card;
			continue;
		}

		foreach ( $clients as $client ) {
			if ( ! isset( $groups[ $client->ID ] ) ) {
				$groups[ $client->ID ] = array(
					'label'    => $client->display_name,
					'email'    => $client->user_email,
					'projects' => array(),
				);
			}

			$groups[ $client->ID ]['projects'][] = $card;
		}
	}

	$groups = array_values( $groups );

	usort(
		$groups,
		static function ( array $a, array $b ): int {
			return strcasecmp( (string) $a['label'], (string) $b['label'] );
		}
	);

	return $groups;
}

/**
 * Read and clear the one-time notice for the current user.
 *
 * @since 1.2.0
 * @return array{code: string, detail: string}
 */
function lidev_pull_notice(): array {
	$key    = 'lidev_notice_' . get_current_user_id();
	$notice = get_transient( $key );
	delete_transient( $key );

	if ( ! is_array( $notice ) ) {
		return array(
			'code'   => '',
			'detail' => '',
		);
	}

	return array(
		'code'   => sanitize_key( (string) ( $notice['code'] ?? '' ) ),
		'detail' => (string) ( $notice['detail'] ?? '' ),
	);
}

/**
 * Human sentence for a notice code.
 *
 * @since 1.2.0
 * @param array{code: string, detail: string} $notice Notice payload.
 * @return string
 */
function lidev_notice_text( array $notice ): string {
	if ( 'error' === $notice['code'] ) {
		return $notice['detail'];
	}

	$messages = array(
		'created'      => __( 'Project created.', 'li-dev-theme' ),
		'updated'      => __( 'Project updated.', 'li-dev-theme' ),
		'sent'         => __( 'Phase sent.', 'li-dev-theme' ),
		'resent'       => __( 'Phase resent.', 'li-dev-theme' ),
		'client_added' => __( 'Client added.', 'li-dev-theme' ),
		'already'      => __( 'That client is already on this project.', 'li-dev-theme' ),
		'settings'     => __( 'Settings saved.', 'li-dev-theme' ),
	);

	return $messages[ $notice['code'] ] ?? '';
}

/**
 * @param list<array<string, mixed>> $phases Phase rows.
 * @param string                      $status Status to find.
 * @return bool
 */
function lidev_project_has_status( array $phases, string $status ): bool {
	foreach ( $phases as $phase ) {
		if ( $status === ( $phase['status'] ?? '' ) ) {
			return true;
		}
	}

	return false;
}

/**
 * @param int $project_id Project ID.
 * @return list<WP_User>
 */
function lidev_project_clients( int $project_id ): array {
	$users = array();

	foreach ( lidev_project_client_ids( $project_id ) as $user_id ) {
		$user = get_userdata( $user_id );

		if ( $user instanceof WP_User ) {
			$users[] = $user;
		}
	}

	return $users;
}

/**
 * @param WP_Post                     $post   Project.
 * @param list<array<string, mixed>>  $phases Phase rows.
 * @return array<string, mixed>
 */
function lidev_project_card( WP_Post $post, array $phases ): array {
	$chips = array();

	foreach ( $phases as $phase ) {
		$when = '';

		if ( 'signed' === $phase['status'] && ! empty( $phase['signed_at'] ) ) {
			$when = lidev_format_mysql_date( (string) $phase['signed_at'] );
		} elseif ( 'awaiting' === $phase['status'] && ! empty( $phase['sent_at'] ) ) {
			$when = lidev_format_mysql_date( (string) $phase['sent_at'] );
		}

		$chips[] = array(
			'label'  => lidev_phase_label( (string) $phase['type'] ),
			'status' => (string) $phase['status'],
			'text'   => lidev_phase_status_label( (string) $phase['status'] ),
			'date'   => $when,
		);
	}

	return array(
		'id'     => (int) $post->ID,
		'title'  => get_the_title( $post ),
		'domain' => (string) get_post_meta( $post->ID, '_lidev_domain', true ),
		'url'    => Lidev_Routing::project_url( (int) $post->ID ),
		'chips'  => $chips,
	);
}

/**
 * @param string                     $search  Lowercase search text.
 * @param array<string, mixed>       $card    Project card.
 * @param list<WP_User>              $clients Assigned clients.
 * @param list<array<string, mixed>> $phases  Phase rows.
 * @return bool
 */
function lidev_dashboard_matches( string $search, array $card, array $clients, array $phases ): bool {
	$haystack = strtolower( $card['title'] . ' ' . $card['domain'] );

	foreach ( $clients as $client ) {
		$haystack .= ' ' . strtolower( $client->display_name . ' ' . $client->user_email );
	}

	foreach ( $phases as $phase ) {
		$haystack .= ' ' . strtolower( lidev_phase_status_label( (string) $phase['status'] ) );
		$haystack .= ' ' . strtolower( (string) $phase['status'] );
	}

	return str_contains( $haystack, $search );
}

/**
 * @since 1.2.0
 * @param string $mysql MySQL datetime.
 * @return string
 */
function lidev_format_mysql_date( string $mysql ): string {
	$time = strtotime( $mysql );

	if ( ! $time ) {
		return '';
	}

	return date_i18n( (string) get_option( 'date_format' ), $time );
}

/**
 * Panel heading for a phase.
 *
 * @since 1.2.0
 * @param string $type Phase slug.
 * @return string
 */
function lidev_phase_panel_title( string $type ): string {
	$titles = array(
		'content' => __( 'Content (Wireframe)', 'li-dev-theme' ),
		'design'  => __( 'Design (Figma)', 'li-dev-theme' ),
		'launch'  => __( 'Ready to Launch', 'li-dev-theme' ),
	);

	return $titles[ $type ] ?? lidev_phase_label( $type );
}

/**
 * @since 1.2.0
 * @param string $action Activity action slug.
 * @return string
 */
function lidev_activity_label( string $action ): string {
	$labels = array(
		'project_created' => __( 'Project created', 'li-dev-theme' ),
		'project_updated' => __( 'Project updated', 'li-dev-theme' ),
		'client_created'  => __( 'Client account created', 'li-dev-theme' ),
		'client_attached' => __( 'Client added to the project', 'li-dev-theme' ),
		'phase_sent'      => __( 'Phase sent', 'li-dev-theme' ),
		'phase_resent'    => __( 'Phase resent', 'li-dev-theme' ),
	);

	return $labels[ $action ] ?? $action;
}
