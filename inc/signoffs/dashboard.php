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
 * @param string $search   Search text.
 * @param string $status   Phase status, or an empty string for all.
 * @param bool   $archived True for the archive list. False for the dashboard.
 * @return list<array{label: string, email: string, projects: list<array<string, mixed>>}>
 */
function lidev_dashboard_groups( string $search, string $status, bool $archived = false ): array {
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

		if ( Lidev_Project::is_archived( (int) $post->ID ) !== $archived ) {
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
		'archived'   => __( 'Project archived.', 'li-dev-theme' ),
		'restored'   => __( 'Project restored.', 'li-dev-theme' ),
		'settings'           => __( 'Settings saved.', 'li-dev-theme' ),
		'reminders_ran'      => __( 'Reminder check finished.', 'li-dev-theme' ),
		'reminders_paused'   => __( 'Reminders paused.', 'li-dev-theme' ),
		'reminders_resumed'  => __( 'Reminders resumed.', 'li-dev-theme' ),
		'magic_sent'   => __( 'If that email belongs to a client, a sign-in link is on its way.', 'li-dev-theme' ),
		'magic_limited'=> __( 'Please wait an hour and try again.', 'li-dev-theme' ),
		'login_failed' => __( 'Those sign-in details do not match.', 'li-dev-theme' ),
		'captcha_failed' => __( 'Please verify that you are not a robot.', 'li-dev-theme' ),
		'email_invalid'=> __( 'Enter a valid email address.', 'li-dev-theme' ),
		'reset_sent'   => __( 'If that account uses a password, a reset link is on its way.', 'li-dev-theme' ),
		'reset_missing'=> __( 'Enter your username or email.', 'li-dev-theme' ),
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
		'id'       => (int) $post->ID,
		'title'    => get_the_title( $post ),
		'domain'   => (string) get_post_meta( $post->ID, '_lidev_domain', true ),
		'url'      => Lidev_Routing::project_url( (int) $post->ID ),
		'progress' => lidev_project_progress( $phases ),
		'chips'    => $chips,
	);
}

/**
 * Signed phases as a whole-number percent.
 *
 * @since 1.8.0
 * @param list<array<string, mixed>> $phases Phase rows.
 * @return int
 */
function lidev_project_progress( array $phases ): int {
	$total  = count( $phases );
	$signed = 0;

	if ( $total < 1 ) {
		return 0;
	}

	foreach ( $phases as $phase ) {
		if ( 'signed' === (string) ( $phase['status'] ?? '' ) ) {
			++$signed;
		}
	}

	return (int) round( ( $signed / $total ) * 100 );
}

/**
 * Small progress readout. The percent is already an integer from 0 to 100.
 *
 * @since 1.8.0
 * @param int $percent Signed share.
 * @return string
 */
function lidev_project_progress_html( int $percent ): string {
	$percent = max( 0, min( 100, $percent ) );
	$label   = sprintf(
		/* translators: %d: percent of phases signed. */
		__( '%d%% complete', 'li-dev-theme' ),
		$percent
	);

	return sprintf(
		'<p class="lidev-signoffs__progress" aria-label="%1$s"><span class="lidev-signoffs__progress-track" aria-hidden="true"><span style="width:%2$d%%"></span></span><span>%2$d%%</span></p>',
		esc_attr( $label ),
		$percent
	);
}

/**
 * Archive or restore control. Hidden from anyone who cannot manage projects.
 *
 * @since 1.8.0
 * @param int $project_id Project ID.
 * @return void
 */
function lidev_project_archive_control( int $project_id ): void {
	if ( $project_id < 1 || ! current_user_can( 'lidev_manage_projects' ) ) {
		return;
	}

	$archived = lidev_project_is_archived( $project_id );
	$confirm  = __( 'Are you sure you want to archive this project? You can restore it later.', 'li-dev-theme' );
	?>
	<form class="lidev-signoffs__archive" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo $archived ? '' : ' onsubmit="return confirm(this.getAttribute(\'data-lidev-confirm\'));"'; ?> <?php echo $archived ? '' : 'data-lidev-confirm="' . esc_attr( $confirm ) . '"'; ?>>
		<input type="hidden" name="action" value="lidev_archive_project">
		<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $project_id ); ?>">
		<input type="hidden" name="lidev_archive" value="<?php echo esc_attr( $archived ? '0' : '1' ); ?>">
		<?php wp_nonce_field( 'lidev_archive_project_' . $project_id, 'lidev_nonce' ); ?>
		<button type="submit"><?php echo esc_html( $archived ? __( 'Restore', 'li-dev-theme' ) : __( 'Archive', 'li-dev-theme' ) ); ?></button>
	</form>
	<?php
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
 * @param string $mysql     MySQL datetime.
 * @param bool   $with_time Include the site time as well as the date.
 * @return string
 */
function lidev_format_mysql_date( string $mysql, bool $with_time = false ): string {
	$when = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', trim( $mysql ), wp_timezone() );

	if ( ! $when instanceof DateTimeImmutable ) {
		return '';
	}

	$format = (string) get_option( 'date_format' );

	if ( $with_time ) {
		$format .= ' ' . (string) get_option( 'time_format' );
	}

	return wp_date( $format, $when->getTimestamp() );
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
		'launch'  => __( 'Pre-launch review', 'li-dev-theme' ),
	);

	return $titles[ $type ] ?? lidev_phase_label( $type );
}

/**
 * @since 1.6.0
 * @return array<string, string>
 */
function lidev_activity_actions(): array {
	return array(
		'project_created'      => __( 'Project created', 'li-dev-theme' ),
		'project_updated'      => __( 'Project updated', 'li-dev-theme' ),
		'client_created'       => __( 'Client account created', 'li-dev-theme' ),
		'client_attached'      => __( 'Client added to the project', 'li-dev-theme' ),
		'phase_sent'           => __( 'Phase sent', 'li-dev-theme' ),
		'phase_resent'         => __( 'Phase resent', 'li-dev-theme' ),
		'phase_viewed'         => __( 'Phase opened', 'li-dev-theme' ),
		'phase_signed'         => __( 'Phase signed', 'li-dev-theme' ),
		'signoff_backfilled'   => __( 'Sign-off snapshot backfilled', 'li-dev-theme' ),
		'pdf_generated'        => __( 'PDF generated', 'li-dev-theme' ),
		'pdf_downloaded'       => __( 'PDF downloaded', 'li-dev-theme' ),
		'magic_link_issued'    => __( 'Magic link issued', 'li-dev-theme' ),
		'magic_link_used'      => __( 'Magic link used', 'li-dev-theme' ),
		'email_sent'           => __( 'Email sent', 'li-dev-theme' ),
		'email_failed'         => __( 'Email failed', 'li-dev-theme' ),
		'reminder_sent'        => __( 'Reminder sent', 'li-dev-theme' ),
		'reminder_paused'      => __( 'Reminders paused', 'li-dev-theme' ),
		'reminder_resumed'     => __( 'Reminders resumed', 'li-dev-theme' ),
		'reminder_unanswered'  => __( 'Unanswered phase alert', 'li-dev-theme' ),
		'project_archived'     => __( 'Project archived', 'li-dev-theme' ),
		'project_restored'     => __( 'Project restored', 'li-dev-theme' ),
	);
}

/**
 * @since 1.2.0
 * @param string $action Activity action slug.
 * @return string
 */
function lidev_activity_label( string $action ): string {
	$labels = lidev_activity_actions();

	return $labels[ $action ] ?? $action;
}

/**
 * @since 1.6.0
 * @param array<string, mixed> $row Activity row.
 * @return string
 */
function lidev_activity_phase_label( array $row ): string {
	$phase_id = absint( $row['phase_id'] ?? 0 );

	if ( $phase_id < 1 ) {
		return '';
	}

	$phase = Lidev_Phase_Repository::get( $phase_id );

	return $phase ? lidev_phase_panel_title( (string) ( $phase['type'] ?? '' ) ) : '';
}

/**
 * Status words a client sees. Team screens keep the shorter labels.
 *
 * @since 1.3.0
 * @param string $status    Phase status.
 * @param string $signed_at MySQL datetime when signed.
 * @return string
 */
function lidev_client_phase_status_label( string $status, string $signed_at = '' ): string {
	if ( 'signed' === $status ) {
		$when = lidev_format_mysql_date( $signed_at );

		return '' !== $when
			? sprintf(
				/* translators: %s: date the phase was signed. */
				__( 'Signed %s', 'li-dev-theme' ),
				$when
			)
			: __( 'Signed', 'li-dev-theme' );
	}

	if ( 'awaiting' === $status ) {
		return __( 'Ready for your review', 'li-dev-theme' );
	}

	return __( 'Not started', 'li-dev-theme' );
}

/**
 * Projects assigned to one client. The meta query is the boundary.
 *
 * @since 1.3.0
 * @param int $user_id Client user ID.
 * @return list<WP_Post>
 */
function lidev_client_projects( int $user_id ): array {
	if ( $user_id < 1 || ! user_can( $user_id, 'lidev_view_own_projects' ) || user_can( $user_id, 'lidev_view_all_projects' ) ) {
		return array();
	}

	$posts = get_posts(
		array(
			'post_type'      => 'lidev_project',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => '_lidev_client_ids',
					'value'   => 'i:' . $user_id . ';',
					'compare' => 'LIKE',
				),
			),
		)
	);
	$owned = array();

	foreach ( $posts as $post ) {
		if ( $post instanceof WP_Post && in_array( $user_id, lidev_project_client_ids( (int) $post->ID ), true ) && ! lidev_project_is_archived( (int) $post->ID ) ) {
			$owned[] = $post;
		}
	}

	return $owned;
}

/**
 * Logged-out notice carried in the redirect, limited to known codes.
 *
 * @since 1.3.0
 * @return string
 */
function lidev_public_notice_text(): string {
	$code = isset( $_GET['lidev_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['lidev_notice'] ) ) : '';

	return lidev_notice_text(
		array(
			'code'   => $code,
			'detail' => '',
		)
	);
}
