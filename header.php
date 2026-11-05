<?php
/**
 * Theme header.
 *
 * Dark Night Core bar with the Cultured Local Image logo.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lidev_show_nav = function_exists( 'lidev_has_primary_nav' ) && lidev_has_primary_nav();
$lidev_logo     = lidev_asset_uri( 'assets/images/LI_MainLogo_Cultured.png' );
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'lidev-site' ); ?>>
<?php wp_body_open(); ?>

<a class="lidev-skip" href="#main"><?php esc_html_e( 'Skip to content', 'li-dev-theme' ); ?></a>

<header class="lidev-header" role="banner">
	<div class="lidev-header__inner">
		<a class="lidev-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<img
				src="<?php echo esc_url( $lidev_logo ); ?>"
				alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>"
				width="180"
				height="40"
				decoding="async"
			>
		</a>

		<?php if ( $lidev_show_nav ) : ?>
			<nav class="lidev-nav-desktop" aria-label="<?php esc_attr_e( 'Primary', 'li-dev-theme' ); ?>">
				<?php
				wp_nav_menu(
					array(
						'theme_location' => 'primary',
						'container'      => false,
						'menu_class'     => 'lidev-nav-menu',
						'fallback_cb'    => 'lidev_fallback_menu',
						'depth'          => 2,
					)
				);
				?>
			</nav>

			<button
				type="button"
				class="lidev-nav-toggle"
				id="lidev-nav-toggle"
				aria-controls="lidev-nav-drawer"
				aria-expanded="false"
				aria-label="<?php esc_attr_e( 'Open menu', 'li-dev-theme' ); ?>"
				data-label-open="<?php esc_attr_e( 'Open menu', 'li-dev-theme' ); ?>"
				data-label-close="<?php esc_attr_e( 'Close menu', 'li-dev-theme' ); ?>"
			>
				<span class="lidev-nav-toggle__box" aria-hidden="true">
					<span class="lidev-nav-toggle__bar"></span>
					<span class="lidev-nav-toggle__bar"></span>
					<span class="lidev-nav-toggle__bar"></span>
				</span>
			</button>
		<?php endif; ?>
	</div>
</header>

<?php if ( $lidev_show_nav ) : ?>
	<div class="lidev-nav-backdrop" id="lidev-nav-backdrop" hidden></div>
	<aside
		class="lidev-nav-drawer"
		id="lidev-nav-drawer"
		role="dialog"
		aria-modal="true"
		aria-label="<?php esc_attr_e( 'Site menu', 'li-dev-theme' ); ?>"
		aria-hidden="true"
		inert
	>
		<div class="lidev-nav-drawer__head">
			<p class="lidev-nav-drawer__title"><?php esc_html_e( 'Menu', 'li-dev-theme' ); ?></p>
			<button type="button" class="lidev-nav-drawer__close" id="lidev-nav-close" aria-label="<?php esc_attr_e( 'Close menu', 'li-dev-theme' ); ?>">
				<span aria-hidden="true">&times;</span>
			</button>
		</div>
		<nav class="lidev-nav-drawer__nav" aria-label="<?php esc_attr_e( 'Primary', 'li-dev-theme' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'lidev-nav-menu',
					'fallback_cb'    => 'lidev_fallback_menu',
					'depth'          => 2,
				)
			);
			?>
		</nav>
	</aside>
<?php endif; ?>
