<?php
/**
 * Archived projects. Team only.
 *
 * @package LIDevTheme
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_search = isset( $_GET['lidev_q'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['lidev_q'] ) ) : '';
$lidev_status = isset( $_GET['lidev_status'] ) ? sanitize_key( wp_unslash( (string) $_GET['lidev_status'] ) ) : '';
$lidev_groups = lidev_dashboard_groups( $lidev_search, $lidev_status, true );
?>

<p class="lidev-signoffs__back">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to projects', 'li-dev-theme' ); ?></a>
</p>

<header class="lidev-page-header">
	<h1><?php esc_html_e( 'Archive', 'li-dev-theme' ); ?></h1>
	<p class="lidev-lede"><?php esc_html_e( 'Projects filed away. Clients do not see these.', 'li-dev-theme' ); ?></p>
</header>

<form class="lidev-search lidev-signoffs__filters" method="get" action="<?php echo esc_url( Lidev_Routing::archive_url() ); ?>">
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
	<p class="lidev-signoffs__empty"><?php esc_html_e( 'No archived projects.', 'li-dev-theme' ); ?></p>
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
							</li>
						<?php endforeach; ?>
					</ul>
					<?php lidev_project_archive_control( (int) $lidev_card['id'] ); ?>
				</li>
			<?php endforeach; ?>
		</ul>
	</section>
<?php endforeach; ?>
