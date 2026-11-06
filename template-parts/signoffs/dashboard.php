<?php
/**
 * Team dashboard on the front page.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_search = isset( $_GET['lidev_q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['lidev_q'] ) ) : '';
$lidev_status = isset( $_GET['lidev_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['lidev_status'] ) ) : '';
$lidev_groups = lidev_dashboard_groups( $lidev_search, $lidev_status );

get_header();
?>

<main id="main" class="lidev-main">
	<div class="lidev-wrap">
		<div class="lidev-signoffs">
			<?php require LIDEV_PATH . 'template-parts/signoffs/notice.php'; ?>

			<header class="lidev-page-header">
				<div class="lidev-signoffs__heading">
					<div>
						<h1><?php esc_html_e( 'Projects', 'li-dev-theme' ); ?></h1>
						<p class="lidev-lede"><?php esc_html_e( 'Clients and the phase each project is in.', 'li-dev-theme' ); ?></p>
					</div>
					<div class="lidev-signoffs__actions">
						<?php if ( current_user_can( 'lidev_manage_projects' ) ) : ?>
							<a class="lidev-button" href="<?php echo esc_url( Lidev_Routing::new_url() ); ?>"><?php esc_html_e( 'Add project', 'li-dev-theme' ); ?></a>
						<?php endif; ?>
						<?php if ( current_user_can( 'lidev_view_activity' ) ) : ?>
							<a class="lidev-signoffs__text-link lidev-signoffs__icon-link" href="<?php echo esc_url( Lidev_Routing::activity_url() ); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/></svg>
								<span><?php esc_html_e( 'Activity', 'li-dev-theme' ); ?></span>
							</a>
						<?php endif; ?>
						<?php if ( current_user_can( 'lidev_manage_settings' ) ) : ?>
							<a class="lidev-signoffs__text-link lidev-signoffs__icon-link" href="<?php echo esc_url( Lidev_Routing::settings_url() ); ?>">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 3.5v2.2M12 18.3V20.5M4.9 6.9l1.5 1.5M17.6 15.6l1.5 1.5M3.5 12h2.2M18.3 12h2.2M4.9 17.1l1.5-1.5M17.6 8.4l1.5-1.5"/></svg>
								<span><?php esc_html_e( 'Settings', 'li-dev-theme' ); ?></span>
							</a>
						<?php endif; ?>
						<a class="lidev-signoffs__text-link lidev-signoffs__icon-link" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
							<span><?php esc_html_e( 'Log out', 'li-dev-theme' ); ?></span>
						</a>
					</div>
				</div>
			</header>

			<form class="lidev-search lidev-signoffs__filters" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="lidev-signoffs__sr" for="lidev-q"><?php esc_html_e( 'Search clients or projects', 'li-dev-theme' ); ?></label>
				<input id="lidev-q" type="search" name="lidev_q" value="<?php echo esc_attr( $lidev_search ); ?>" placeholder="<?php esc_attr_e( 'Client, email, or project', 'li-dev-theme' ); ?>">
				<label class="lidev-signoffs__sr" for="lidev-status"><?php esc_html_e( 'Status', 'li-dev-theme' ); ?></label>
				<select id="lidev-status" name="lidev_status">
					<option value=""><?php esc_html_e( 'Any status', 'li-dev-theme' ); ?></option>
					<?php foreach ( lidev_phase_statuses() as $lidev_phase_status ) : ?>
						<option value="<?php echo esc_attr( $lidev_phase_status ); ?>" <?php selected( $lidev_status, $lidev_phase_status ); ?>>
							<?php echo esc_html( lidev_phase_status_label( $lidev_phase_status ) ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit"><?php esc_html_e( 'Search', 'li-dev-theme' ); ?></button>
			</form>

			<?php if ( ! $lidev_groups ) : ?>
				<p class="lidev-signoffs__empty"><?php esc_html_e( 'No projects match.', 'li-dev-theme' ); ?></p>
			<?php endif; ?>

			<?php foreach ( $lidev_groups as $lidev_group ) : ?>
				<section class="lidev-signoffs__client">
					<h2><?php echo esc_html( (string) $lidev_group['label'] ); ?></h2>
					<?php if ( '' !== (string) $lidev_group['email'] ) : ?>
						<p class="lidev-meta"><?php echo esc_html( (string) $lidev_group['email'] ); ?></p>
					<?php endif; ?>

					<ul class="lidev-signoffs__projects">
						<?php foreach ( $lidev_group['projects'] as $lidev_card ) : ?>
							<li class="lidev-card lidev-signoffs__project">
								<div class="lidev-signoffs__project-head">
									<h3 class="lidev-card__title">
										<a href="<?php echo esc_url( (string) $lidev_card['url'] ); ?>"><?php echo esc_html( (string) $lidev_card['title'] ); ?></a>
									</h3>
									<?php echo lidev_project_progress_html( (int) $lidev_card['progress'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- lidev_project_progress_html() escapes. ?>
								</div>
								<?php if ( '' !== (string) $lidev_card['domain'] ) : ?>
									<p class="lidev-meta"><?php echo esc_html( (string) $lidev_card['domain'] ); ?></p>
								<?php endif; ?>
								<ul class="lidev-signoffs__chips">
									<?php foreach ( $lidev_card['chips'] as $lidev_chip ) : ?>
										<li class="lidev-signoffs__chip lidev-signoffs__chip--<?php echo esc_attr( (string) $lidev_chip['status'] ); ?>">
											<span class="lidev-signoffs__chip-phase"><?php echo esc_html( (string) $lidev_chip['label'] ); ?></span>
											<span><?php echo esc_html( (string) $lidev_chip['text'] ); ?></span>
											<?php if ( '' !== (string) $lidev_chip['date'] ) : ?>
												<time><?php echo esc_html( (string) $lidev_chip['date'] ); ?></time>
											<?php endif; ?>
										</li>
									<?php endforeach; ?>
								</ul>
								<?php lidev_project_archive_control( (int) $lidev_card['id'] ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
		</div>
	</div>
</main>

<?php
get_footer();
