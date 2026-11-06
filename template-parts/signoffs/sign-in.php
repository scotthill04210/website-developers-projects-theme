<?php
/**
 * Logged-out front page. Team password and client magic link.
 *
 * @package LIDevTheme
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_notice = lidev_public_notice_text();
$lidev_code   = isset( $_GET['lidev_notice'] ) ? sanitize_key( wp_unslash( (string) $_GET['lidev_notice'] ) ) : '';

get_header();
?>

<main id="main" class="lidev-main">
	<div class="lidev-wrap">
		<div class="lidev-signoffs">
			<header class="lidev-page-header">
				<h1><?php esc_html_e( 'Sign in', 'li-dev-theme' ); ?></h1>
				<p class="lidev-lede"><?php esc_html_e( 'Clients get a link by email. Development Team members use a password.', 'li-dev-theme' ); ?></p>
			</header>

			<?php if ( '' !== $lidev_notice ) : ?>
				<p class="lidev-signoffs__notice<?php echo in_array( $lidev_code, array( 'magic_sent', 'reset_sent' ), true ) ? '' : ' lidev-signoffs__notice--error'; ?>" role="status">
					<?php echo esc_html( $lidev_notice ); ?>
				</p>
			<?php endif; ?>

			<div class="lidev-signoffs__gates">
				<section class="lidev-card lidev-signoffs__gate">
					<h2><?php esc_html_e( 'Client', 'li-dev-theme' ); ?></h2>
					<p class="lidev-meta"><?php esc_html_e( 'Enter your email and we’ll send you a link.', 'li-dev-theme' ); ?></p>
					<?php require LIDEV_PATH . 'template-parts/signoffs/magic-form.php'; ?>
				</section>

				<section class="lidev-card lidev-signoffs__gate">
					<h2><?php esc_html_e( 'Development Team', 'li-dev-theme' ); ?></h2>
					<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="lidev_team_login">
						<?php wp_nonce_field( 'lidev_team_login', 'lidev_nonce' ); ?>
						<label class="lidev-signoffs__field" for="lidev-login">
							<span><?php esc_html_e( 'Username or email', 'li-dev-theme' ); ?></span>
							<input id="lidev-login" name="lidev_login" type="text" autocomplete="username" required>
						</label>
						<label class="lidev-signoffs__field" for="lidev-password">
							<span><?php esc_html_e( 'Password', 'li-dev-theme' ); ?></span>
							<input id="lidev-password" name="lidev_password" type="password" autocomplete="current-password" required>
						</label>
						<label class="lidev-signoffs__check" for="lidev-remember">
							<input id="lidev-remember" name="lidev_remember" type="checkbox" value="1">
							<span><?php esc_html_e( 'Keep me signed in', 'li-dev-theme' ); ?></span>
						</label>
						<?php
						/**
						 * Defender prints its Turnstile widget here, the same hook wp-login.php uses.
						 * The posted token is what Defender checks during wp_signon().
						 */
						do_action( 'login_form' );
						?>
						<input class="lidev-button" type="submit" value="<?php esc_attr_e( 'Sign in', 'li-dev-theme' ); ?>">
					</form>
					<details class="lidev-signoffs__lost">
						<summary><?php esc_html_e( 'Lost password', 'li-dev-theme' ); ?></summary>
						<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="lidev_lost_password">
							<?php wp_nonce_field( 'lidev_lost_password', 'lidev_nonce' ); ?>
							<label class="lidev-signoffs__field" for="lidev-lost-login">
								<span><?php esc_html_e( 'Username or email', 'li-dev-theme' ); ?></span>
								<input id="lidev-lost-login" name="lidev_login" type="text" autocomplete="username" required>
							</label>
							<input class="lidev-button" type="submit" value="<?php esc_attr_e( 'Email me a reset link', 'li-dev-theme' ); ?>">
						</form>
					</details>
				</section>
			</div>
		</div>
	</div>
</main>

<?php
get_footer();
