<?php
/**
 * Client review and signature for one phase.
 *
 * @package LIDevTheme
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_project = Lidev_Routing::project();
$lidev_phase   = Lidev_Routing::phase();

if ( ! $lidev_project instanceof WP_Post || ! is_array( $lidev_phase ) ) {
	return;
}

Lidev_Signoff::record_view( $lidev_project, $lidev_phase );

$lidev_status    = (string) $lidev_phase['status'];
$lidev_type      = (string) $lidev_phase['type'];
$lidev_signoff   = 'signed' === $lidev_status ? Lidev_Signoff_Repository::for_phase( (int) $lidev_phase['id'] ) : null;
$lidev_snapshot  = is_array( $lidev_signoff ) ? lidev_signoff_snapshot( $lidev_signoff ) : array();
$lidev_signed_at = is_array( $lidev_signoff ) ? (string) ( $lidev_signoff['signed_at'] ?? '' ) : (string) ( $lidev_phase['signed_at'] ?? '' );

if ( $lidev_snapshot ) {
	$lidev_heading     = $lidev_snapshot['project_name'];
	$lidev_domain      = $lidev_snapshot['domain'];
	$lidev_phase_label = $lidev_snapshot['phase_label'];
	$lidev_link        = $lidev_snapshot['external_link'];
	$lidev_message     = $lidev_snapshot['team_message'];
	$lidev_statement   = $lidev_snapshot['statement'];
	$lidev_signer      = $lidev_snapshot['signer_name'];
	$lidev_link_type   = $lidev_snapshot['phase_type'];
} else {
	$lidev_heading     = get_the_title( $lidev_project );
	$lidev_domain      = (string) get_post_meta( $lidev_project->ID, '_lidev_domain', true );
	$lidev_phase_label = lidev_phase_panel_title( $lidev_type );
	$lidev_link        = (string) ( $lidev_phase['external_link'] ?? '' );
	$lidev_viewer      = wp_get_current_user();
	$lidev_message     = lidev_compose_phase_message(
		$lidev_type,
		(string) ( $lidev_phase['team_message'] ?? '' ),
		lidev_phase_message_values(
			$lidev_viewer->exists() ? (string) $lidev_viewer->display_name : '',
			(string) $lidev_heading,
			$lidev_phase_label,
			$lidev_link,
			(string) $lidev_domain
		)
	);
	$lidev_statement   = lidev_phase_statement( $lidev_type );
	$lidev_signer      = is_array( $lidev_signoff ) ? (string) $lidev_signoff['signer_name'] : '';
	$lidev_link_type   = $lidev_type;
}

get_header();
?>

<main id="main" class="lidev-main">
	<div class="lidev-wrap">
		<div class="lidev-signoffs">
			<p class="lidev-signoffs__back">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to projects', 'li-dev-theme' ); ?></a>
			</p>
			<header class="lidev-page-header">
				<h1><?php echo esc_html( $lidev_heading ); ?></h1>
				<?php if ( '' !== $lidev_domain ) : ?>
					<p class="lidev-meta"><?php echo esc_html( $lidev_domain ); ?></p>
				<?php endif; ?>
				<p class="lidev-lede"><?php echo esc_html( $lidev_phase_label ); ?></p>
			</header>

			<?php require LIDEV_PATH . 'template-parts/signoffs/notice.php'; ?>

			<section class="lidev-card lidev-signoffs__phase">
				<p class="lidev-signoffs__chip lidev-signoffs__chip--<?php echo esc_attr( $lidev_status ); ?>">
					<?php echo esc_html( lidev_client_phase_status_label( $lidev_status, $lidev_signed_at ) ); ?>
				</p>

				<?php if ( '' !== $lidev_link ) : ?>
					<p>
						<a class="lidev-button" href="<?php echo esc_url( $lidev_link ); ?>" target="_blank" rel="noopener noreferrer">
							<?php echo esc_html( lidev_phase_link_label( $lidev_link_type ) ); ?>
						</a>
					</p>
				<?php endif; ?>

				<?php if ( '' !== $lidev_message ) : ?>
					<div class="lidev-signoffs__message"><?php echo lidev_format_email_html( $lidev_message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lidev_format_email_html() escapes. ?></div>
				<?php endif; ?>

				<?php if ( 'signed' === $lidev_status ) : ?>
					<h2><?php esc_html_e( 'Thank you. This approval is recorded and cannot be changed.', 'li-dev-theme' ); ?></h2>
					<?php if ( '' !== $lidev_statement ) : ?>
						<p class="lidev-signoffs__statement"><?php echo esc_html( $lidev_statement ); ?></p>
					<?php endif; ?>
					<dl class="lidev-signoffs__summary">
						<div>
							<dt><?php esc_html_e( 'Project', 'li-dev-theme' ); ?></dt>
							<dd><?php echo esc_html( $lidev_heading ); ?></dd>
						</div>
						<?php if ( '' !== $lidev_domain ) : ?>
							<div>
								<dt><?php esc_html_e( 'Domain', 'li-dev-theme' ); ?></dt>
								<dd><?php echo esc_html( $lidev_domain ); ?></dd>
							</div>
						<?php endif; ?>
						<div>
							<dt><?php esc_html_e( 'Phase', 'li-dev-theme' ); ?></dt>
							<dd><?php echo esc_html( $lidev_phase_label ); ?></dd>
						</div>
						<?php if ( is_array( $lidev_signoff ) ) : ?>
							<div>
								<dt><?php esc_html_e( 'Signed by', 'li-dev-theme' ); ?></dt>
								<dd><?php echo esc_html( $lidev_signer ); ?></dd>
							</div>
							<div>
								<dt><?php esc_html_e( 'Signed', 'li-dev-theme' ); ?></dt>
								<dd><?php echo esc_html( lidev_format_mysql_date( $lidev_signed_at, true ) ); ?></dd>
							</div>
							<?php if ( '' !== (string) ( $lidev_signoff['ip_address'] ?? '' ) ) : ?>
								<div>
									<dt><?php esc_html_e( 'Client IP address', 'li-dev-theme' ); ?></dt>
									<dd><?php echo esc_html( (string) $lidev_signoff['ip_address'] ); ?></dd>
								</div>
							<?php endif; ?>
						<?php endif; ?>
					</dl>
					<?php if ( is_array( $lidev_signoff ) && 'typed' === (string) $lidev_signoff['signature_type'] && Lidev_Signoff::FONT_ID === (string) $lidev_signoff['signature_data'] ) : ?>
						<p class="lidev-signoffs__signature-typed"><?php echo esc_html( $lidev_signer ); ?></p>
					<?php elseif ( is_array( $lidev_signoff ) && 'drawn' === (string) $lidev_signoff['signature_type'] ) : ?>
						<?php $lidev_src = Lidev_Signoff_Repository::accept_signature( 'drawn', (string) $lidev_signoff['signature_data'] ); ?>
						<?php if ( '' !== $lidev_src ) : ?>
							<img class="lidev-signoffs__signature-drawn" src="<?php echo esc_attr( $lidev_src ); ?>" alt="<?php echo esc_attr( (string) $lidev_signoff['signer_name'] ); ?>">
						<?php endif; ?>
					<?php endif; ?>
					<?php if ( is_array( $lidev_signoff ) ) : ?>
						<p><a class="lidev-signoffs__text-link" href="<?php echo esc_url( lidev_pdf_url( (int) $lidev_signoff['id'] ) ); ?>"><?php esc_html_e( 'PDF download', 'li-dev-theme' ); ?></a></p>
					<?php endif; ?>
				<?php elseif ( 'awaiting' === $lidev_status && '' !== $lidev_statement ) : ?>
					<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-lidev-sign>
						<input type="hidden" name="action" value="lidev_sign_phase">
						<input type="hidden" name="lidev_project_id" value="<?php echo esc_attr( (string) $lidev_project->ID ); ?>">
						<input type="hidden" name="lidev_phase_id" value="<?php echo esc_attr( (string) $lidev_phase['id'] ); ?>">
						<?php wp_nonce_field( 'lidev_sign_phase_' . (int) $lidev_phase['id'], 'lidev_nonce' ); ?>
						<p class="lidev-signoffs__statement"><?php echo esc_html( $lidev_statement ); ?></p>
						<label class="lidev-signoffs__field" for="lidev-signer-name">
							<span><?php esc_html_e( 'Full name', 'li-dev-theme' ); ?></span>
							<input id="lidev-signer-name" name="lidev_signer_name" type="text" autocomplete="name" required maxlength="191">
						</label>
						<div class="lidev-signoffs__modes" role="tablist" aria-label="<?php esc_attr_e( 'Signature', 'li-dev-theme' ); ?>">
							<button class="lidev-signoffs__mode" type="button" role="tab" id="lidev-tab-typed" aria-selected="true" aria-controls="lidev-panel-typed" data-lidev-mode="typed"><?php esc_html_e( 'Type', 'li-dev-theme' ); ?></button>
							<button class="lidev-signoffs__mode" type="button" role="tab" id="lidev-tab-drawn" aria-selected="false" aria-controls="lidev-panel-drawn" data-lidev-mode="drawn"><?php esc_html_e( 'Draw', 'li-dev-theme' ); ?></button>
						</div>
						<input type="hidden" name="lidev_signature_type" value="typed" data-lidev-type>
						<div id="lidev-panel-typed" role="tabpanel" aria-labelledby="lidev-tab-typed" data-lidev-panel="typed">
							<p class="lidev-signoffs__signature-typed" data-lidev-typed aria-hidden="true"></p>
						</div>
						<div id="lidev-panel-drawn" role="tabpanel" aria-labelledby="lidev-tab-drawn" data-lidev-panel="drawn" hidden>
							<canvas class="lidev-signoffs__pad" width="600" height="180" data-lidev-pad aria-label="<?php esc_attr_e( 'Draw your signature', 'li-dev-theme' ); ?>"></canvas>
							<button class="lidev-signoffs__text-link" type="button" data-lidev-clear><?php esc_html_e( 'Clear', 'li-dev-theme' ); ?></button>
							<input type="hidden" name="lidev_signature" value="" data-lidev-drawn>
						</div>
						<label class="lidev-signoffs__check" for="lidev-acknowledge">
							<input id="lidev-acknowledge" name="lidev_acknowledge" type="checkbox" value="1" required>
							<span><?php esc_html_e( 'I understand this is a legally binding approval and cannot be undone', 'li-dev-theme' ); ?></span>
						</label>
						<button class="lidev-button" type="submit" disabled><?php esc_html_e( 'Sign and approve', 'li-dev-theme' ); ?></button>
						<noscript><p class="lidev-meta"><?php esc_html_e( 'Turn on JavaScript to sign.', 'li-dev-theme' ); ?></p></noscript>
					</form>
				<?php endif; ?>
			</section>
		</div>
	</div>
</main>

<?php
get_footer();
