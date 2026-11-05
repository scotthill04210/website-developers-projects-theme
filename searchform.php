<?php
/**
 * Search form.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_search_id = wp_unique_id( 'lidev-search-' );
?>
<form class="lidev-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $lidev_search_id ); ?>">
		<?php esc_html_e( 'Search', 'li-dev-theme' ); ?>
	</label>
	<input
		id="<?php echo esc_attr( $lidev_search_id ); ?>"
		type="search"
		name="s"
		value="<?php echo esc_attr( get_search_query( false ) ); ?>"
		placeholder="<?php esc_attr_e( 'Search', 'li-dev-theme' ); ?>"
	>
	<button type="submit"><?php esc_html_e( 'Search', 'li-dev-theme' ); ?></button>
</form>
