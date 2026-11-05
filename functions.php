<?php
/**
 * LI Dev Theme functions.
 *
 * Brand colors, Inter, and logo files match the Local Image Timetracker theme.
 *
 * @package LIDevTheme
 * @since   1.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIDEV_VERSION', '1.2.0' );
define( 'LIDEV_PATH', trailingslashit( get_template_directory() ) );
define( 'LIDEV_URI', trailingslashit( get_template_directory_uri() ) );

/**
 * Theme supports and menus.
 *
 * @since 1.0.0
 * @return void
 */
function lidev_theme_setup(): void {
	load_theme_textdomain( 'li-dev-theme', LIDEV_PATH . 'languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor.css' );
	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
			'navigation-widgets',
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary Menu', 'li-dev-theme' ),
		)
	);
}
add_action( 'after_setup_theme', 'lidev_theme_setup' );

/**
 * Theme file URL.
 *
 * @since 1.0.0
 * @param string $relative Path relative to the theme root.
 * @return string
 */
function lidev_asset_uri( string $relative ): string {
	return LIDEV_URI . ltrim( $relative, '/' );
}

/**
 * Cache-busting version from filemtime.
 *
 * @since 1.0.0
 * @param string $relative Path relative to the theme root.
 * @return string
 */
function lidev_asset_version( string $relative ): string {
	$path = LIDEV_PATH . ltrim( $relative, '/' );

	if ( file_exists( $path ) ) {
		return (string) filemtime( $path );
	}

	return LIDEV_VERSION;
}

/**
 * Whether the header should render a menu (assigned menu or published pages).
 *
 * @since 1.0.0
 * @return bool
 */
function lidev_has_primary_nav(): bool {
	static $has = null;

	if ( null !== $has ) {
		return $has;
	}

	if ( has_nav_menu( 'primary' ) ) {
		$has = true;
		return true;
	}

	$pages = get_pages(
		array(
			'number'      => 1,
			'sort_column' => 'ID',
		)
	);
	$has   = ! empty( $pages );

	return $has;
}

/**
 * Page list used when no Primary menu is assigned.
 *
 * @since 1.0.0
 * @param array<string, mixed> $args wp_nav_menu arguments.
 * @return void
 */
function lidev_fallback_menu( array $args ): void {
	$pages = wp_list_pages(
		array(
			'title_li' => '',
			'echo'     => false,
			'depth'    => 1,
		)
	);

	if ( ! $pages ) {
		return;
	}

	printf(
		'<ul class="%1$s">%2$s</ul>',
		esc_attr( (string) $args['menu_class'] ),
		$pages // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_list_pages() escapes item markup.
	);
}

/**
 * Front-end styles and the mobile menu script.
 *
 * @since 1.0.0
 * @return void
 */
function lidev_enqueue_assets(): void {
	wp_enqueue_style(
		'lidev-style',
		get_stylesheet_uri(),
		array(),
		lidev_asset_version( 'style.css' )
	);

	wp_enqueue_style(
		'lidev-nav',
		lidev_asset_uri( 'assets/css/nav.css' ),
		array( 'lidev-style' ),
		lidev_asset_version( 'assets/css/nav.css' )
	);

	if ( ! lidev_has_primary_nav() ) {
		return;
	}

	wp_enqueue_script(
		'lidev-nav',
		lidev_asset_uri( 'assets/js/nav.js' ),
		array(),
		lidev_asset_version( 'assets/js/nav.js' ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'lidev_enqueue_assets' );

/**
 * Dark Jungle mark used as the site icon.
 *
 * @since 1.0.0
 * @return string
 */
function lidev_favicon_url(): string {
	return lidev_asset_uri( 'assets/images/LI_Icon_DarkJungle.png' );
}

/**
 * Print the brand icon as the favicon.
 *
 * @since 1.0.0
 * @return void
 */
function lidev_output_favicon(): void {
	$url = lidev_favicon_url();

	printf(
		'<link rel="icon" href="%1$s" type="image/png" sizes="any">' . "\n" .
		'<link rel="apple-touch-icon" href="%1$s">' . "\n",
		esc_url( $url )
	);
}
add_action( 'wp_head', 'lidev_output_favicon', 1 );
add_action( 'admin_head', 'lidev_output_favicon', 1 );
add_action( 'login_head', 'lidev_output_favicon', 1 );

/**
 * Point the Site Icon API at the brand mark.
 *
 * @since 1.0.0
 * @param string|false $url     Site icon URL.
 * @param int          $size    Requested size.
 * @param int          $blog_id Blog ID.
 * @return string
 */
function lidev_force_site_icon_url( $url, $size = 512, $blog_id = 0 ): string {
	unset( $url, $size, $blog_id );

	return lidev_favicon_url();
}
add_filter( 'get_site_icon_url', 'lidev_force_site_icon_url', 10, 3 );

/**
 * Shorter excerpts on cards.
 *
 * @since 1.0.0
 * @param int $length Default length.
 * @return int
 */
function lidev_excerpt_length( int $length ): int {
	unset( $length );

	return 28;
}
add_filter( 'excerpt_length', 'lidev_excerpt_length' );

/**
 * Ellipsis instead of the default bracketed more string.
 *
 * @since 1.0.0
 * @param string $more Default more string.
 * @return string
 */
function lidev_excerpt_more( string $more ): string {
	unset( $more );

	return '&hellip;';
}
add_filter( 'excerpt_more', 'lidev_excerpt_more' );

/**
 * Posts pagination.
 *
 * @since 1.0.0
 * @return void
 */
function lidev_pagination(): void {
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => __( 'Previous', 'li-dev-theme' ),
			'next_text' => __( 'Next', 'li-dev-theme' ),
			'class'     => 'lidev-pagination',
		)
	);
}

/**
 * Branded login screen styles.
 *
 * @since 1.0.0
 * @return void
 */
function lidev_login_enqueue(): void {
	wp_enqueue_style(
		'lidev-login',
		lidev_asset_uri( 'assets/css/login.css' ),
		array(),
		lidev_asset_version( 'assets/css/login.css' )
	);

	$css = sprintf(
		':root{--lidev-login-logo:url("%s");}',
		esc_url_raw( lidev_asset_uri( 'assets/images/LI_MainLogo_DarkJungle.png' ) )
	);

	wp_add_inline_style( 'lidev-login', $css );
}
add_action( 'login_enqueue_scripts', 'lidev_login_enqueue' );

/**
 * Login logo links home.
 *
 * @since 1.0.0
 * @return string
 */
function lidev_login_header_url(): string {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'lidev_login_header_url' );

/**
 * Accessible name for the login logo.
 *
 * @since 1.0.0
 * @return string
 */
function lidev_login_header_text(): string {
	return (string) get_bloginfo( 'name', 'display' );
}
add_filter( 'login_headertext', 'lidev_login_header_text' );

/**
 * Document title on the login screen.
 *
 * @since 1.0.0
 * @param string $title  Default title.
 * @param string $action Login action.
 * @return string
 */
function lidev_login_document_title( string $title, string $action ): string {
	unset( $title, $action );

	return sprintf(
		/* translators: %s: site name */
		__( 'Sign in — %s', 'li-dev-theme' ),
		(string) get_bloginfo( 'name', 'display' )
	);
}
add_filter( 'login_title', 'lidev_login_document_title', 10, 2 );

/**
 * Tagline above the login form.
 *
 * @since 1.0.0
 * @param string $message Existing message HTML.
 * @return string
 */
function lidev_login_message( string $message ): string {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only action check on the login screen.
	$action = isset( $_REQUEST['action'] ) ? sanitize_key( (string) wp_unslash( $_REQUEST['action'] ) ) : 'login';

	if ( $action && 'login' !== $action ) {
		return $message;
	}

	if ( '' !== $message ) {
		return $message;
	}

	$tagline = (string) get_bloginfo( 'description', 'display' );
	if ( '' === $tagline ) {
		$tagline = 'Local Image';
	}

	return '<p class="lidev-login-tagline">' . esc_html( $tagline ) . '</p>';
}
add_filter( 'login_message', 'lidev_login_message' );

/**
 * Small brand line under the login form.
 *
 * @since 1.0.0
 * @return void
 */
function lidev_login_footer_note(): void {
	echo '<p class="lidev-login-footer">' . esc_html( (string) get_bloginfo( 'name', 'display' ) ) . '</p>';
}
add_action( 'login_footer', 'lidev_login_footer_note' );

require_once LIDEV_PATH . 'inc/signoffs/bootstrap.php';
