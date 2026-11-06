<?php
/**
 * Team project detail and phase send forms.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_project = Lidev_Routing::project();

if ( ! $lidev_project instanceof WP_Post ) {
	return;
}

$lidev_project_id = (int) $lidev_project->ID;
$lidev_phases     = Lidev_Phase_Repository::for_project( $lidev_project_id );
$lidev_clients    = lidev_project_clients( $lidev_project_id );
$lidev_domain     = (string) get_post_meta( $lidev_project_id, '_lidev_domain', true );
$lidev_content    = 'not_sent';

foreach ( $lidev_phases as $lidev_phase_row ) {
	if ( 'content' === ( $lidev_phase_row['type'] ?? '' ) ) {
		$lidev_content = (string) $lidev_phase_row['status'];
	}
}
?>

<p class="lidev-signoffs__back">
	<a href="<?php echo esc_url( lidev_project_is_archived( $lidev_project_id ) ? Lidev_Routing::archive_url() : home_url( '/' ) ); ?>">
		<?php echo esc_html( lidev_project_is_archived( $lidev_project_id ) ? __( 'Back to archive', 'li-dev-theme' ) : __( 'Back to projects', 'li-dev-theme' ) ); ?>
	</a>
</p>

<header class="lidev-page-header">
	<div class="lidev-signoffs__heading">
		<div>
			<h1><?php echo esc_html( get_the_title( $lidev_project ) ); ?></h1>
			<?php echo lidev_project_progress_html( lidev_project_progress( $lidev_phases ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lidev_project_progress_html() escapes. ?>
			<?php if ( lidev_project_is_archived( $lidev_project_id ) ) : ?>
				<p class="lidev-meta"><?php esc_html_e( 'This project is archived.', 'li-dev-theme' ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $lidev_domain ) : ?>
				<p class="lidev-lede"><?php echo esc_html( $lidev_domain ); ?></p>
			<?php endif; ?>
			<?php if ( $lidev_clients ) : ?>
				<ul class="lidev-signoffs__people">
					<?php foreach ( $lidev_clients as $lidev_client ) : ?>
						<li>
							<?php echo esc_html( $lidev_client->display_name ); ?>
							<span><?php echo esc_html( $lidev_client->user_email ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php if ( current_user_can( 'lidev_manage_projects' ) ) : ?>
			<div class="lidev-signoffs__actions">
				<a class="lidev-button" href="<?php echo esc_url( Lidev_Routing::edit_url( $lidev_project_id ) ); ?>"><?php esc_html_e( 'Edit project', 'li-dev-theme' ); ?></a>
				<?php lidev_project_archive_control( $lidev_project_id ); ?>
			</div>
		<?php endif; ?>
	</div>
</header>

<div class="lidev-signoffs__phases">
	<?php foreach ( $lidev_phases as $lidev_phase ) : ?>
		<?php
		$lidev_phase_id = (int) $lidev_phase['id'];
		$lidev_type     = (string) $lidev_phase['type'];
		$lidev_status   = (string) $lidev_phase['status'];
		$lidev_sender   = absint( $lidev_phase['sent_by'] ?? 0 );
		$lidev_sender_u = $lidev_sender ? get_userdata( $lidev_sender ) : false;
		$lidev_signoff  = 'signed' === $lidev_status ? Lidev_Signoff_Repository::for_phase( $lidev_phase_id ) : null;
		$lidev_warn     = 'design' === $lidev_type && 'signed' !== $lidev_content && 'signed' !== $lidev_status;
		?>
		<section class="lidev-card lidev-signoffs__phase">
			<header class="lidev-signoffs__phase-head">
				<h2><?php echo esc_html( lidev_phase_panel_title( $lidev_type ) ); ?></h2>
				<span class="lidev-signoffs__chip lidev-signoffs__chip--<?php echo esc_attr( $lidev_status ); ?>">
					<?php echo esc_html( lidev_phase_status_label( $lidev_status ) ); ?>
				</span>
			</header>

			<?php if ( $lidev_warn ) : ?>
				<p class="lidev-signoffs__warning"><?php esc_html_e( 'Content is not signed yet. You can still send Design.', 'li-dev-theme' ); ?></p>
			<?php endif; ?>

			<dl class="lidev-signoffs__facts">
				<?php if ( ! empty( $lidev_phase['external_link'] ) ) : ?>
					<div>
						<dt><?php echo esc_html( lidev_phase_link_label( $lidev_type ) ); ?></dt>
						<dd><a href="<?php echo esc_url( (string) $lidev_phase['external_link'] ); ?>"><?php echo esc_html( (string) $lidev_phase['external_link'] ); ?></a></dd>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $lidev_phase['team_message'] ) ) : ?>
					<div>
						<dt><?php esc_html_e( 'Added note', 'li-dev-theme' ); ?></dt>
						<dd><?php echo lidev_format_email_html( (string) $lidev_phase['team_message'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lidev_format_email_html() escapes. ?></dd>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $lidev_phase['sent_at'] ) ) : ?>
					<div>
						<dt><?php esc_html_e( 'Sent', 'li-dev-theme' ); ?></dt>
						<dd>
							<?php echo esc_html( lidev_format_mysql_date( (string) $lidev_phase['sent_at'] ) ); ?>
							<?php if ( $lidev_sender_u instanceof WP_User ) : ?>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: person who sent the phase. */
										__( 'by %s', 'li-dev-theme' ),
										$lidev_sender_u->display_name
									)
								);
								?>
							<?php endif; ?>
						</dd>
					</div>
				<?php endif; ?>
				<?php if ( is_array( $lidev_signoff ) ) : ?>
					<div>
						<dt><?php esc_html_e( 'Signed by', 'li-dev-theme' ); ?></dt>
						<dd>
							<?php echo esc_html( (string) $lidev_signoff['signer_name'] ); ?>
							<?php if ( ! empty( $lidev_signoff['signed_at'] ) ) : ?>
								<?php echo esc_html( lidev_format_mysql_date( (string) $lidev_signoff['signed_at'] ) ); ?>
							<?php endif; ?>
						</dd>
					</div>
					<?php if ( '' !== (string) ( $lidev_signoff['ip_address'] ?? '' ) ) : ?>
						<div>
							<dt><?php esc_html_e( 'Client IP address', 'li-dev-theme' ); ?></dt>
							<dd><?php echo esc_html( (string) $lidev_signoff['ip_address'] ); ?></dd>
						</div>
					<?php endif; ?>
					<div>
						<dt><?php esc_html_e( 'PDF', 'li-dev-theme' ); ?></dt>
						<dd><a class="lidev-signoffs__text-link" href="<?php echo esc_url( lidev_pdf_url( (int) $lidev_signoff['id'] ) ); ?>"><?php esc_html_e( 'PDF download', 'li-dev-theme' ); ?></a></dd>
					</div>
				<?php endif; ?>
			</dl>

			<?php if ( 'awaiting' === $lidev_status && current_user_can( 'lidev_send_phases' ) ) : ?>
				<form class="lidev-signoffs__pause" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="lidev_pause_reminders">
					<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $lidev_project_id ); ?>">
					<input type="hidden" name="phase_id" value="<?php echo esc_attr( (string) $lidev_phase_id ); ?>">
					<input type="hidden" name="lidev_paused" value="<?php echo empty( $lidev_phase['reminders_paused'] ) ? '1' : '0'; ?>">
					<?php wp_nonce_field( 'lidev_pause_reminders_' . $lidev_phase_id, 'lidev_nonce' ); ?>
					<button class="lidev-signoffs__text-link" type="submit">
						<?php echo esc_html( empty( $lidev_phase['reminders_paused'] ) ? __( 'Pause reminders', 'li-dev-theme' ) : __( 'Resume reminders', 'li-dev-theme' ) ); ?>
					</button>
					<?php if ( ! empty( $lidev_phase['reminder_count'] ) ) : ?>
						<p class="lidev-meta">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %d: number of reminders already sent. */
									_n( '%d reminder sent', '%d reminders sent', (int) $lidev_phase['reminder_count'], 'li-dev-theme' ),
									(int) $lidev_phase['reminder_count']
								)
							);
							?>
						</p>
					<?php endif; ?>
				</form>
			<?php endif; ?>

			<?php if ( 'signed' !== $lidev_status && current_user_can( 'lidev_send_phases' ) ) : ?>
				<form
					class="lidev-signoffs__form lidev-signoffs__send"
					method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					data-lidev-preview
					data-lidev-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"
					data-lidev-nonce="<?php echo esc_attr( wp_create_nonce( 'lidev_preview_phase_email' ) ); ?>"
					data-lidev-project="<?php echo esc_attr( (string) $lidev_project_id ); ?>"
					data-lidev-phase="<?php echo esc_attr( (string) $lidev_phase_id ); ?>"
				>
					<input type="hidden" name="action" value="lidev_send_phase">
					<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $lidev_project_id ); ?>">
					<input type="hidden" name="phase_id" value="<?php echo esc_attr( (string) $lidev_phase_id ); ?>">
					<?php wp_nonce_field( 'lidev_send_phase_' . $lidev_phase_id, 'lidev_nonce' ); ?>

					<label class="lidev-signoffs__field" for="lidev-link-<?php echo esc_attr( (string) $lidev_phase_id ); ?>">
						<span><?php echo esc_html( lidev_phase_link_label( $lidev_type ) ); ?></span>
						<input id="lidev-link-<?php echo esc_attr( (string) $lidev_phase_id ); ?>" name="lidev_link" type="url" required inputmode="url" value="<?php echo esc_attr( (string) ( $lidev_phase['external_link'] ?? '' ) ); ?>" placeholder="https://">
					</label>

					<label class="lidev-signoffs__field" for="lidev-message-<?php echo esc_attr( (string) $lidev_phase_id ); ?>">
						<span><?php esc_html_e( '(Optional) Add to message', 'li-dev-theme' ); ?></span>
						<textarea id="lidev-message-<?php echo esc_attr( (string) $lidev_phase_id ); ?>" name="lidev_message" rows="2"><?php echo esc_textarea( (string) ( $lidev_phase['team_message'] ?? '' ) ); ?></textarea>
					</label>
					<?php lidev_message_token_controls( 'lidev-message-' . $lidev_phase_id ); ?>

					<button class="lidev-signoffs__preview-open" type="button" data-lidev-preview-open><?php esc_html_e( 'Email preview', 'li-dev-theme' ); ?></button>
					<dialog class="lidev-signoffs__preview-dialog" data-lidev-preview-dialog>
						<div class="lidev-signoffs__preview-dialog-head">
							<h3><?php esc_html_e( 'Email preview', 'li-dev-theme' ); ?></h3>
							<button class="lidev-signoffs__preview-close" type="button" data-lidev-preview-close><?php esc_html_e( 'Close', 'li-dev-theme' ); ?></button>
						</div>
						<div class="lidev-signoffs__email-preview">
							<p class="lidev-meta" data-lidev-email-caption></p>
							<iframe data-lidev-email-frame title="<?php esc_attr_e( 'Email preview', 'li-dev-theme' ); ?>" sandbox=""></iframe>
							<p class="lidev-meta"><?php esc_html_e( 'The review button is created when you send. This preview does not use that link.', 'li-dev-theme' ); ?></p>
						</div>
					</dialog>

					<button class="lidev-button" type="submit">
						<?php echo esc_html( 'awaiting' === $lidev_status ? __( 'Resend', 'li-dev-theme' ) : __( 'Send', 'li-dev-theme' ) ); ?>
					</button>
				</form>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>

<?php if ( current_user_can( 'lidev_manage_projects' ) && current_user_can( 'lidev_create_clients' ) ) : ?>
	<section class="lidev-card lidev-signoffs__add-client">
		<h2><?php esc_html_e( 'Add another client', 'li-dev-theme' ); ?></h2>
		<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="lidev_add_client">
			<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $lidev_project_id ); ?>">
			<?php wp_nonce_field( 'lidev_add_client', 'lidev_nonce' ); ?>
			<div class="lidev-signoffs__client-row">
				<label class="lidev-signoffs__field" for="lidev-add-client-name">
					<span><?php esc_html_e( 'Client name', 'li-dev-theme' ); ?></span>
					<input id="lidev-add-client-name" name="lidev_client_name" type="text" required>
				</label>
				<label class="lidev-signoffs__field" for="lidev-add-client-email">
					<span><?php esc_html_e( 'Client email', 'li-dev-theme' ); ?></span>
					<input id="lidev-add-client-email" name="lidev_client_email" type="email" required>
				</label>
			</div>
			<button class="lidev-button" type="submit"><?php esc_html_e( 'Add client', 'li-dev-theme' ); ?></button>
		</form>
	</section>
<?php endif; ?>

<?php if ( current_user_can( 'lidev_view_activity' ) ) : ?>
	<?php
	$lidev_action   = isset( $_GET['lidev_action'] ) ? sanitize_key( wp_unslash( (string) $_GET['lidev_action'] ) ) : '';
	$lidev_failures = Lidev_Activity_Repository::search( $lidev_project_id, 'email_failed', 20 );
	$lidev_activity = Lidev_Activity_Repository::search( $lidev_project_id, $lidev_action );
	?>
	<?php if ( $lidev_failures ) : ?>
		<section class="lidev-signoffs__failures">
			<h2><?php esc_html_e( 'Email problems', 'li-dev-theme' ); ?></h2>
			<ul>
				<?php foreach ( $lidev_failures as $lidev_failure ) : ?>
					<?php $lidev_details = is_array( $lidev_failure['details'] ?? null ) ? $lidev_failure['details'] : array(); ?>
					<li>
						<?php
						echo esc_html(
							trim(
								sprintf(
									'%1$s %2$s',
									(string) ( $lidev_details['type'] ?? '' ),
									(string) ( $lidev_details['recipient'] ?? '' )
								)
							) . ' ' . lidev_format_mysql_date( (string) ( $lidev_failure['created_at'] ?? '' ), true )
						);
						?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>
	<details class="lidev-signoffs__log" open>
		<summary><?php esc_html_e( 'Activity', 'li-dev-theme' ); ?></summary>
		<form class="lidev-signoffs__filters" method="get" action="<?php echo esc_url( Lidev_Routing::project_url( $lidev_project_id ) ); ?>">
			<label class="lidev-signoffs__sr" for="lidev-project-action"><?php esc_html_e( 'Action', 'li-dev-theme' ); ?></label>
			<select id="lidev-project-action" name="lidev_action">
				<option value=""><?php esc_html_e( 'Any action', 'li-dev-theme' ); ?></option>
				<?php foreach ( lidev_activity_actions() as $lidev_key => $lidev_label ) : ?>
					<option value="<?php echo esc_attr( $lidev_key ); ?>" <?php selected( $lidev_action, $lidev_key ); ?>><?php echo esc_html( $lidev_label ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit"><?php esc_html_e( 'Filter', 'li-dev-theme' ); ?></button>
		</form>
		<?php if ( ! $lidev_activity ) : ?>
			<p class="lidev-signoffs__empty"><?php esc_html_e( 'No activity yet.', 'li-dev-theme' ); ?></p>
		<?php else : ?>
			<ol>
				<?php foreach ( $lidev_activity as $lidev_row ) : ?>
					<?php
					$lidev_actor_id = absint( $lidev_row['actor_user_id'] ?? 0 );
					$lidev_actor    = $lidev_actor_id ? get_userdata( $lidev_actor_id ) : false;
					$lidev_who      = $lidev_actor instanceof WP_User ? $lidev_actor->display_name : (string) ( $lidev_row['actor_label'] ?? '' );
					$lidev_phase_name = lidev_activity_phase_label( $lidev_row );
					?>
					<li>
						<strong><?php echo esc_html( lidev_activity_label( (string) $lidev_row['action'] ) ); ?></strong>
						<span>
							<?php
							echo esc_html(
								trim(
									$lidev_who . ' ' . $lidev_phase_name . ' ' . lidev_format_mysql_date( (string) ( $lidev_row['created_at'] ?? '' ), true )
								)
							);
							?>
						</span>
					</li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
	</details>
<?php endif; ?>
