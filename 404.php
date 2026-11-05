<?php
/**
 * 404 template.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main id="main" class="lidev-main">
	<div class="lidev-wrap">
		<header class="lidev-page-header">
			<h1><?php esc_html_e( 'Page not found', 'li-dev-theme' ); ?></h1>
			<p class="lidev-lede"><?php esc_html_e( 'That page is missing, or the link is out of date.', 'li-dev-theme' ); ?></p>
		</header>

		<?php get_search_form(); ?>

		<p>
			<a class="lidev-button" href="<?php echo esc_url( home_url( '/' ) ); ?>">
				<?php esc_html_e( 'Back home', 'li-dev-theme' ); ?>
			</a>
		</p>
	</div>
</main>

<?php
get_footer();
