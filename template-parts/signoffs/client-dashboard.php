<?php
/**
 * Client dashboard on the front page.
 *
 * @package LIDevTheme
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_projects = lidev_client_projects( get_current_user_id() );

get_header();
?>

<main id="main" class="lidev-main">
	<div class="lidev-wrap">
		<div class="lidev-signoffs">
			<header class="lidev-page-header">
				<div class="lidev-signoffs__heading">
					<div>
						<h1><?php esc_html_e( 'Your projects', 'li-dev-theme' ); ?></h1>
						<p class="lidev-lede"><?php esc_html_e( 'Review a phase when it is ready, then sign it.', 'li-dev-theme' ); ?></p>
					</div>
					<a class="lidev-signoffs__text-link lidev-signoffs__icon-link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
						<span><?php esc_html_e( 'Log out', 'li-dev-theme' ); ?></span>
					</a>
				</div>
			</header>

			<?php if ( ! $lidev_projects ) : ?>
				<p class="lidev-signoffs__empty"><?php esc_html_e( 'No projects yet.', 'li-dev-theme' ); ?></p>
			<?php endif; ?>

			<ul class="lidev-signoffs__projects">
				<?php foreach ( $lidev_projects as $lidev_project ) : ?>
					<?php
					$lidev_domain = (string) get_post_meta( $lidev_project->ID, '_lidev_domain', true );
					$lidev_phases = Lidev_Phase_Repository::for_project( (int) $lidev_project->ID );
					?>
					<li class="lidev-card lidev-signoffs__project">
						<div class="lidev-signoffs__project-head">
							<h2 class="lidev-card__title"><?php echo esc_html( get_the_title( $lidev_project ) ); ?></h2>
							<?php echo lidev_project_progress_html( lidev_project_progress( $lidev_phases ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lidev_project_progress_html() escapes. ?>
						</div>
						<?php if ( '' !== $lidev_domain ) : ?>
							<p class="lidev-meta"><?php echo esc_html( $lidev_domain ); ?></p>
						<?php endif; ?>
						<ul class="lidev-signoffs__phases">
							<?php foreach ( $lidev_phases as $lidev_phase ) : ?>
								<?php
								$lidev_status  = (string) $lidev_phase['status'];
								$lidev_signed  = (string) ( $lidev_phase['signed_at'] ?? '' );
								$lidev_signoff = 'signed' === $lidev_status ? Lidev_Signoff_Repository::for_phase( (int) $lidev_phase['id'] ) : null;
								?>
								<li class="lidev-signoffs__client-phase">
									<div>
										<strong><?php echo esc_html( lidev_phase_panel_title( (string) $lidev_phase['type'] ) ); ?></strong>
										<span class="lidev-signoffs__chip lidev-signoffs__chip--<?php echo esc_attr( $lidev_status ); ?>">
											<?php echo esc_html( lidev_client_phase_status_label( $lidev_status, $lidev_signed ) ); ?>
										</span>
									</div>
									<?php if ( 'awaiting' === $lidev_status ) : ?>
										<a class="lidev-button" href="<?php echo esc_url( Lidev_Routing::phase_url( (int) $lidev_project->ID, (int) $lidev_phase['id'] ) ); ?>">
											<?php esc_html_e( 'Review', 'li-dev-theme' ); ?>
										</a>
									<?php elseif ( 'signed' === $lidev_status ) : ?>
										<span class="lidev-signoffs__actions">
											<a class="lidev-signoffs__text-link" href="<?php echo esc_url( Lidev_Routing::phase_url( (int) $lidev_project->ID, (int) $lidev_phase['id'] ) ); ?>">
												<?php esc_html_e( 'View', 'li-dev-theme' ); ?>
											</a>
											<?php if ( is_array( $lidev_signoff ) ) : ?>
												<a class="lidev-signoffs__text-link" href="<?php echo esc_url( lidev_pdf_url( (int) $lidev_signoff['id'] ) ); ?>"><?php esc_html_e( 'PDF download', 'li-dev-theme' ); ?></a>
											<?php endif; ?>
										</span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	</div>
</main>

<?php
get_footer();
