<?php
/**
 * Client "email me a link" form.
 *
 * @package LIDevTheme
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<form class="lidev-signoffs__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="lidev_request_magic_link">
	<?php wp_nonce_field( 'lidev_request_magic_link', 'lidev_nonce', false ); ?>
	<label class="lidev-signoffs__field" for="lidev-magic-email">
		<span><?php esc_html_e( 'Email', 'li-dev-theme' ); ?></span>
		<input id="lidev-magic-email" name="lidev_email" type="email" autocomplete="email" required>
	</label>
	<button class="lidev-button" type="submit"><?php esc_html_e( 'Email me a link', 'li-dev-theme' ); ?></button>
</form>
