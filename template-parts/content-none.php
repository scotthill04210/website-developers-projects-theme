<?php
/**
 * Empty loop.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="lidev-card lidev-empty">
	<?php if ( is_search() ) : ?>
		<h2 class="lidev-card__title"><?php esc_html_e( 'Nothing matched that search', 'li-dev-theme' ); ?></h2>
		<p class="lidev-lede"><?php esc_html_e( 'Try a different word or a shorter phrase.', 'li-dev-theme' ); ?></p>
	<?php else : ?>
		<h2 class="lidev-card__title"><?php esc_html_e( 'Nothing here yet', 'li-dev-theme' ); ?></h2>
		<p class="lidev-lede"><?php esc_html_e( 'Published posts will show up in this list.', 'li-dev-theme' ); ?></p>
	<?php endif; ?>
</section>
