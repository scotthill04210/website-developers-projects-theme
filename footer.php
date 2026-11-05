<?php
/**
 * Theme footer.
 *
 * Night Core bar with the Cultured Local Image icon.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_footer_icon = lidev_asset_uri( 'assets/images/LI_Icon_Cultured.png' );
?>

<footer class="lidev-footer" role="contentinfo">
	<div class="lidev-footer__inner">
		<img
			class="lidev-footer__mark"
			src="<?php echo esc_url( $lidev_footer_icon ); ?>"
			alt=""
			width="24"
			height="24"
			decoding="async"
		>
		<p class="lidev-footer__copy">
			&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?>
			<?php bloginfo( 'name' ); ?>
		</p>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
