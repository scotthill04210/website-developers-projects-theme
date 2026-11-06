<?php
/**
 * Keep logged-out visitors off public content.
 *
 * @package LIDevTheme
 * @since   1.3.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirects public reads to the sign-in page and marks that page noindex.
 *
 * @since 1.3.0
 */
final class Lidev_Privacy {

	/**
	 * Tell caches not to store the sign-in page or a magic-link URL.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function mark_uncached(): void {
		if ( ( ! self::is_uncached_path() && ! self::is_access_request() ) || defined( 'DONOTCACHEPAGE' ) ) {
			return;
		}

		define( 'DONOTCACHEPAGE', true );
	}

	/**
	 * Cache headers for / and /access/. The query is not ready yet, so this uses the path.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function send_cache_headers(): void {
		if ( headers_sent() ) {
			return;
		}

		$access = self::is_access_request();

		if ( ! self::is_uncached_path() && ! $access ) {
			return;
		}

		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );

		// The magic-link redirect exits before the later robots hook, so the
		// token URL itself has to carry these headers.
		if ( $access ) {
			header( 'Referrer-Policy: no-referrer' );
			header( 'X-Robots-Tag: noindex, nofollow' );
		}
	}

	/**
	 * Send logged-out readers of public content back to the sign-in page.
	 *
	 * Runs late so Defender can block or serve its own requests first.
	 * The REST API exits before this hook, so those routes are handled in redirect_rest().
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function redirect_public(): void {
		if ( is_user_logged_in() || is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( self::is_left_alone() || ! self::is_public_content() ) {
			return;
		}

		self::go_home();
	}

	/**
	 * Noindex the sign-in page and the magic-link page.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public static function send_robots_header(): void {
		if ( ! self::is_private_gate() || headers_sent() ) {
			return;
		}

		header( 'X-Robots-Tag: noindex, nofollow' );
	}

	/**
	 * Matching robots meta tag. Printed by core wp_robots() from wp_head.
	 *
	 * @since 1.3.0
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( ! self::is_private_gate() ) {
			return $robots;
		}

		unset( $robots['index'], $robots['follow'] );
		$robots['noindex']  = true;
		$robots['nofollow'] = true;

		return $robots;
	}

	/**
	 * Logged-out requests for users or posts never reach template_redirect.
	 *
	 * WordPress serves the REST API during parse_request and then exits.
	 * A previous callback that already prepared a response is left as-is.
	 *
	 * @since 1.3.0
	 * @param mixed           $result  Response from an earlier callback.
	 * @param WP_REST_Server  $server  Server instance.
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public static function redirect_rest( $result, $server, $request ) {
		if ( null !== $result || is_user_logged_in() || ! $request instanceof WP_REST_Request ) {
			return $result;
		}

		$route = $request->get_route();

		if ( ! preg_match( '#^/wp/v2/(?:posts|users)(?:/|$)#', $route ) ) {
			return $result;
		}

		self::go_home();
	}

	/**
	 * @since 1.3.0
	 * @return void
	 */
	private static function go_home(): void {
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		}

		wp_safe_redirect( home_url( '/' ), 302 );
		exit;
	}

	/**
	 * Posts, pages, archives, search, and feeds. The front page itself stays.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	private static function is_public_content(): bool {
		if ( is_feed() || is_search() ) {
			return true;
		}

		if ( is_front_page() && ! is_paged() ) {
			return false;
		}

		return is_singular() || is_archive() || ( is_home() && is_paged() );
	}

	/**
	 * Requests this class must not redirect.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	private static function is_left_alone(): bool {
		if ( '' !== Lidev_Routing::screen() ) {
			return true;
		}

		$path = self::request_path();

		if ( self::is_static_exception( $path ) || self::is_masked_login( $path ) || self::defender_bot_trap() ) {
			return true;
		}

		$code = http_response_code();

		return in_array( $code, array( 401, 403, 429 ), true );
	}

	/**
	 * Sign-in page for a logged-out visitor, or the magic-link screen.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	private static function is_private_gate(): bool {
		if ( 'access' === Lidev_Routing::screen() ) {
			return true;
		}

		return ! is_user_logged_in() && is_front_page() && ! is_paged() && ! is_feed();
	}

	/**
	 * Magic-link screen, including a token on any path.
	 *
	 * The token value is never read into a log.
	 *
	 * @since 1.7.0
	 * @return bool
	 */
	private static function is_access_request(): bool {
		if ( isset( $_GET['token'] ) && is_scalar( $_GET['token'] ) && '' !== (string) $_GET['token'] ) {
			return true;
		}

		if ( 'access' === self::request_path() ) {
			return true;
		}

		return 'access' === sanitize_key( (string) get_query_var( 'lidev_screen' ) );
	}

	/**
	 * @since 1.3.0
	 * @return bool
	 */
	private static function is_uncached_path(): bool {
		$path = self::request_path();

		return '' === $path || 'index.php' === $path || 'access' === $path;
	}

	/**
	 * @since 1.3.0
	 * @param string $path Path with no leading slash.
	 * @return bool
	 */
	private static function is_static_exception( string $path ): bool {
		if ( in_array( $path, array( 'robots.txt', 'wp-login.php', 'wp-admin/admin-post.php', 'wp-admin/admin-ajax.php' ), true ) ) {
			return true;
		}

		if ( 'wp-sitemap.xml' === $path || str_starts_with( $path, 'wp-sitemap-' ) ) {
			return true;
		}

		$sitemap = get_query_var( 'sitemap' );
		$style   = get_query_var( 'sitemap-stylesheet' );

		return ( is_string( $sitemap ) && '' !== $sitemap ) || ( is_string( $style ) && '' !== $style ) || is_robots();
	}

	/**
	 * Defender's masked login slug from its settings. The current value is /panel.
	 *
	 * @since 1.3.0
	 * @param string $path Path with no leading slash.
	 * @return bool
	 */
	private static function is_masked_login( string $path ): bool {
		$raw  = get_option( 'wd_masking_login_settings' );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : $raw;

		if ( ! is_array( $data ) || empty( $data['enabled'] ) ) {
			return false;
		}

		$slug = strtolower( trim( (string) ( $data['mask_url'] ?? '' ), '/' ) );

		return '' !== $slug && strtolower( $path ) === $slug;
	}

	/**
	 * True when Defender's bot trap already owns this request.
	 *
	 * The trap path rotates. Only Defender's query var is checked, never a copied path.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	private static function defender_bot_trap(): bool {
		if ( ! class_exists( '\WP_Defender\Component\Malicious_Bot' ) ) {
			return false;
		}

		$value = get_query_var( \WP_Defender\Component\Malicious_Bot::URL_QUERY );

		return is_string( $value ) && '' !== $value;
	}

	/**
	 * Path relative to the site root, without a query string.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	private static function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = wp_parse_url( $uri, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$path = rawurldecode( $path );
		$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );

		if ( is_string( $home ) && '/' !== $home && '/' !== rtrim( $home, '/' ) && str_starts_with( $path, $home ) ) {
			$path = substr( $path, strlen( untrailingslashit( $home ) ) );
		}

		return trim( $path, '/' );
	}
}
