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
$lidev_tabs     = array(
	'email'     => __( 'Email', 'li-dev-theme' ),
	'reminders' => __( 'Reminders', 'li-dev-theme' ),
	'messages'  => __( 'Messages', 'li-dev-theme' ),
);
$lidev_tab      = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'email';

if ( ! isset( $lidev_tabs[ $lidev_tab ] ) ) {
	$lidev_tab = 'email';
}

$lidev_leads = array(
	'email'     => __( 'Where phase mail is sent from, and where signed approvals are delivered.', 'li-dev-theme' ),
	'reminders' => __( 'How often a waiting client is reminded, and how long a sign-in link lasts.', 'li-dev-theme' ),
	'messages'  => __( 'The message each phase email starts with. A note on a project is added underneath.', 'li-dev-theme' ),
);
?>

<p class="lidev-signoffs__back">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to projects', 'li-dev-theme' ); ?></a>
</p>

<header class="lidev-page-header">
	<h1><?php esc_html_e( 'Sign-off settings', 'li-dev-theme' ); ?></h1>
	<p class="lidev-lede"><?php echo esc_html( $lidev_leads[ $lidev_tab ] ); ?></p>
</header>

<nav class="lidev-signoffs__tabs" aria-label="<?php esc_attr_e( 'Settings sections', 'li-dev-theme' ); ?>">
	<?php foreach ( $lidev_tabs as $lidev_key => $lidev_label ) : ?>
		<a href="<?php echo esc_url( Lidev_Routing::settings_url( $lidev_key ) ); ?>" <?php echo $lidev_key === $lidev_tab ? 'aria-current="page"' : ''; ?>>
			<?php echo esc_html( $lidev_label ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<?php if ( 'email' === $lidev_tab ) : ?>
	<p class="lidev-meta"><?php echo esc_html( Lidev_Graph_Mailer::status_label() ); ?></p>
	<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lidev_save_settings">
		<input type="hidden" name="lidev_settings_tab" value="email">
		<?php wp_nonce_field( 'lidev_save_settings', 'lidev_nonce' ); ?>

		<label class="lidev-signoffs__field" for="lidev-team-email">
			<span><?php esc_html_e( 'Team email', 'li-dev-theme' ); ?></span>
			<input id="lidev-team-email" name="team_email" type="email" value="<?php echo esc_attr( $lidev_settings['team_email'] ); ?>">
		</label>

		<label class="lidev-signoffs__field" for="lidev-from-name">
			<span><?php esc_html_e( 'From name', 'li-dev-theme' ); ?></span>
			<input id="lidev-from-name" name="from_name" type="text" value="<?php echo esc_attr( $lidev_settings['from_name'] ); ?>">
		</label>

		<label class="lidev-signoffs__field" for="lidev-from-email">
			<span><?php esc_html_e( 'From address', 'li-dev-theme' ); ?></span>
			<input id="lidev-from-email" name="from_email" type="email" value="<?php echo esc_attr( $lidev_settings['from_email'] ); ?>" <?php echo Lidev_Graph_Mailer::is_configured() ? 'readonly' : ''; ?>>
		</label>
		<?php if ( Lidev_Graph_Mailer::is_configured() ) : ?>
			<p class="lidev-meta"><?php esc_html_e( 'This address is the Microsoft 365 shared mailbox and cannot be changed here.', 'li-dev-theme' ); ?></p>
		<?php endif; ?>

		<button class="lidev-button" type="submit"><?php esc_html_e( 'Save email', 'li-dev-theme' ); ?></button>
	</form>
<?php elseif ( 'reminders' === $lidev_tab ) : ?>
	<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lidev_save_settings">
		<input type="hidden" name="lidev_settings_tab" value="reminders">
		<?php wp_nonce_field( 'lidev_save_settings', 'lidev_nonce' ); ?>

		<label class="lidev-signoffs__field" for="lidev-reminder-days">
			<span><?php esc_html_e( 'Reminder interval (days)', 'li-dev-theme' ); ?></span>
			<input id="lidev-reminder-days" name="reminder_days" type="number" min="1" max="365" required value="<?php echo esc_attr( (string) $lidev_settings['reminder_days'] ); ?>">
		</label>

		<label class="lidev-signoffs__field" for="lidev-magic-days">
			<span><?php esc_html_e( 'Magic link lifetime (days)', 'li-dev-theme' ); ?></span>
			<input id="lidev-magic-days" name="magic_link_days" type="number" min="1" max="365" required value="<?php echo esc_attr( (string) $lidev_settings['magic_link_days'] ); ?>">
		</label>

		<button class="lidev-button" type="submit"><?php esc_html_e( 'Save reminders', 'li-dev-theme' ); ?></button>
	</form>

	<form class="lidev-signoffs__form lidev-signoffs__reminders" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lidev_run_reminders">
		<?php wp_nonce_field( 'lidev_run_reminders', 'lidev_nonce' ); ?>
		<button class="lidev-button" type="submit"><?php esc_html_e( 'Run reminder check', 'li-dev-theme' ); ?></button>
		<p class="lidev-meta"><?php esc_html_e( 'Sends reminders that are already due. WordPress also runs this check twice a day.', 'li-dev-theme' ); ?></p>
	</form>
<?php else : ?>
	<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="lidev_save_settings">
		<input type="hidden" name="lidev_settings_tab" value="messages">
		<?php wp_nonce_field( 'lidev_save_settings', 'lidev_nonce' ); ?>

		<p class="lidev-meta"><?php esc_html_e( 'Bold, italic, lists, and links are kept. Insert a placeholder and it is replaced for each client when the email is sent. {link} is the wireframe, Figma, or staging link on that send.', 'li-dev-theme' ); ?></p>

		<label class="lidev-signoffs__field" for="lidev-message-content">
			<span><?php esc_html_e( 'Wireframe', 'li-dev-theme' ); ?></span>
			<textarea id="lidev-message-content" class="lidev-email-source" name="message_content" rows="6"><?php echo esc_textarea( (string) $lidev_settings['message_content'] ); ?></textarea>
		</label>
		<?php lidev_message_token_controls( 'lidev-message-content' ); ?>

		<label class="lidev-signoffs__field" for="lidev-message-design">
			<span><?php esc_html_e( 'Figma', 'li-dev-theme' ); ?></span>
			<textarea id="lidev-message-design" class="lidev-email-source" name="message_design" rows="6"><?php echo esc_textarea( (string) $lidev_settings['message_design'] ); ?></textarea>
		</label>
		<?php lidev_message_token_controls( 'lidev-message-design' ); ?>

		<label class="lidev-signoffs__field" for="lidev-message-launch">
			<span><?php esc_html_e( 'Pre-launch review', 'li-dev-theme' ); ?></span>
			<textarea id="lidev-message-launch" class="lidev-email-source" name="message_launch" rows="6"><?php echo esc_textarea( (string) $lidev_settings['message_launch'] ); ?></textarea>
		</label>
		<?php lidev_message_token_controls( 'lidev-message-launch' ); ?>

		<button class="lidev-button" type="submit"><?php esc_html_e( 'Save messages', 'li-dev-theme' ); ?></button>
	</form>
<?php endif; ?>
