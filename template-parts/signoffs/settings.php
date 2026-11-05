<?php
/**
 * Front-end sign-off settings.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_settings = Lidev_Settings::get();
?>

<p class="lidev-signoffs__back">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to projects', 'li-dev-theme' ); ?></a>
</p>

<header class="lidev-page-header">
	<h1><?php esc_html_e( 'Sign-off settings', 'li-dev-theme' ); ?></h1>
	<p class="lidev-lede"><?php esc_html_e( 'These values are used when phase emails and reminders are turned on.', 'li-dev-theme' ); ?></p>
</header>

<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="lidev_save_settings">
	<?php wp_nonce_field( 'lidev_save_settings', 'lidev_nonce' ); ?>

	<label class="lidev-signoffs__field" for="lidev-team-email">
		<span><?php esc_html_e( 'Team email', 'li-dev-theme' ); ?></span>
		<input id="lidev-team-email" name="team_email" type="email" value="<?php echo esc_attr( $lidev_settings['team_email'] ); ?>">
	</label>

	<label class="lidev-signoffs__field" for="lidev-reminder-days">
		<span><?php esc_html_e( 'Reminder interval (days)', 'li-dev-theme' ); ?></span>
		<input id="lidev-reminder-days" name="reminder_days" type="number" min="1" max="365" required value="<?php echo esc_attr( (string) $lidev_settings['reminder_days'] ); ?>">
	</label>

	<label class="lidev-signoffs__field" for="lidev-magic-days">
		<span><?php esc_html_e( 'Magic link lifetime (days)', 'li-dev-theme' ); ?></span>
		<input id="lidev-magic-days" name="magic_link_days" type="number" min="1" max="365" required value="<?php echo esc_attr( (string) $lidev_settings['magic_link_days'] ); ?>">
	</label>

	<label class="lidev-signoffs__field" for="lidev-from-name">
		<span><?php esc_html_e( 'From name', 'li-dev-theme' ); ?></span>
		<input id="lidev-from-name" name="from_name" type="text" value="<?php echo esc_attr( $lidev_settings['from_name'] ); ?>">
	</label>

	<label class="lidev-signoffs__field" for="lidev-from-email">
		<span><?php esc_html_e( 'From address', 'li-dev-theme' ); ?></span>
		<input id="lidev-from-email" name="from_email" type="email" value="<?php echo esc_attr( $lidev_settings['from_email'] ); ?>">
	</label>

	<button class="lidev-button" type="submit"><?php esc_html_e( 'Save settings', 'li-dev-theme' ); ?></button>
</form>
