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
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to projects', 'li-dev-theme' ); ?></a>
</p>

<header class="lidev-page-header">
	<div class="lidev-signoffs__heading">
		<div>
			<h1><?php echo esc_html( get_the_title( $lidev_project ) ); ?></h1>
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
			<a class="lidev-button" href="<?php echo esc_url( Lidev_Routing::edit_url( $lidev_project_id ) ); ?>"><?php esc_html_e( 'Edit project', 'li-dev-theme' ); ?></a>
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
						<dt><?php esc_html_e( 'Message', 'li-dev-theme' ); ?></dt>
						<dd><?php echo wp_kses_post( (string) $lidev_phase['team_message'] ); ?></dd>
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
					<div>
						<dt><?php esc_html_e( 'PDF', 'li-dev-theme' ); ?></dt>
						<dd><span class="lidev-signoffs__pdf"><?php esc_html_e( 'PDF download', 'li-dev-theme' ); ?></span></dd>
					</div>
				<?php endif; ?>
			</dl>

			<?php if ( 'signed' !== $lidev_status && current_user_can( 'lidev_send_phases' ) ) : ?>
				<form class="lidev-signoffs__form lidev-signoffs__send" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="lidev_send_phase">
					<input type="hidden" name="project_id" value="<?php echo esc_attr( (string) $lidev_project_id ); ?>">
					<input type="hidden" name="phase_id" value="<?php echo esc_attr( (string) $lidev_phase_id ); ?>">
					<?php wp_nonce_field( 'lidev_send_phase_' . $lidev_phase_id, 'lidev_nonce' ); ?>

					<label class="lidev-signoffs__field" for="lidev-link-<?php echo esc_attr( (string) $lidev_phase_id ); ?>">
						<span><?php echo esc_html( lidev_phase_link_label( $lidev_type ) ); ?></span>
						<input id="lidev-link-<?php echo esc_attr( (string) $lidev_phase_id ); ?>" name="lidev_link" type="url" required inputmode="url" value="<?php echo esc_attr( (string) ( $lidev_phase['external_link'] ?? '' ) ); ?>" placeholder="https://">
					</label>

					<label class="lidev-signoffs__field" for="lidev-message-<?php echo esc_attr( (string) $lidev_phase_id ); ?>">
						<span><?php esc_html_e( 'Message to the client', 'li-dev-theme' ); ?></span>
						<textarea id="lidev-message-<?php echo esc_attr( (string) $lidev_phase_id ); ?>" name="lidev_message" rows="4"><?php echo esc_textarea( (string) ( $lidev_phase['team_message'] ?? '' ) ); ?></textarea>
					</label>

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
	<?php $lidev_activity = Lidev_Activity_Repository::for_project( $lidev_project_id ); ?>
	<details class="lidev-signoffs__log">
		<summary><?php esc_html_e( 'Activity', 'li-dev-theme' ); ?></summary>
		<?php if ( ! $lidev_activity ) : ?>
			<p class="lidev-signoffs__empty"><?php esc_html_e( 'No activity yet.', 'li-dev-theme' ); ?></p>
		<?php else : ?>
			<ol>
				<?php foreach ( $lidev_activity as $lidev_row ) : ?>
					<?php
					$lidev_actor_id = absint( $lidev_row['actor_user_id'] ?? 0 );
					$lidev_actor    = $lidev_actor_id ? get_userdata( $lidev_actor_id ) : false;
					$lidev_who      = $lidev_actor instanceof WP_User ? $lidev_actor->display_name : (string) ( $lidev_row['actor_label'] ?? '' );
					?>
					<li>
						<strong><?php echo esc_html( lidev_activity_label( (string) $lidev_row['action'] ) ); ?></strong>
						<span>
							<?php
							echo esc_html(
								trim(
									$lidev_who . ' ' . lidev_format_mysql_date( (string) ( $lidev_row['created_at'] ?? '' ) )
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
