<?php
/**
 * Magic-link landing. A bad link offers a new one.
 *
 * @package LIDevTheme
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_notice = lidev_public_notice_text();
$lidev_token  = isset( $_GET['token'] ) ? (string) wp_unslash( $_GET['token'] ) : '';

get_header();
?>

<main id="main" class="lidev-main">
	<div class="lidev-wrap">
		<div class="lidev-signoffs">
			<header class="lidev-page-header">
				<h1><?php esc_html_e( 'Sign in', 'li-dev-theme' ); ?></h1>
				<?php if ( '' !== $lidev_token && '' === $lidev_notice ) : ?>
					<p class="lidev-lede"><?php esc_html_e( 'That link has expired or is not valid. Enter your email and we’ll send you a new one.', 'li-dev-theme' ); ?></p>
				<?php else : ?>
					<p class="lidev-lede"><?php esc_html_e( 'Enter your email and we’ll send you a link.', 'li-dev-theme' ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( '' !== $lidev_notice ) : ?>
				<p class="lidev-signoffs__notice<?php echo 'magic_sent' === sanitize_key( (string) ( $_GET['lidev_notice'] ?? '' ) ) ? '' : ' lidev-signoffs__notice--error'; ?>" role="status">
					<?php echo esc_html( $lidev_notice ); ?>
				</p>
			<?php endif; ?>

			<section class="lidev-card lidev-signoffs__gate">
				<?php require LIDEV_PATH . 'template-parts/signoffs/magic-form.php'; ?>
			</section>
		</div>
	</div>
</main>

<?php
get_footer();
