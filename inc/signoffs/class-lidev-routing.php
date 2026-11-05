<?php
/**
 * Front-end routes for Team screens.
 *
 * @package LIDevTheme
 * @since   1.2.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites, access checks, and the sign-off stylesheet.
 *
 * @since 1.2.0
 */
final class Lidev_Routing {

	/**
	 * Register query vars and pretty URLs.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function register(): void {
		add_rewrite_tag( '%lidev_screen%', '([^&]+)' );
		add_rewrite_tag( '%lidev_project_id%', '([0-9]+)' );

		add_rewrite_rule( '^project/new/?$', 'index.php?lidev_screen=new', 'top' );
		add_rewrite_rule( '^project/([0-9]+)/edit/?$', 'index.php?lidev_screen=edit&lidev_project_id=$matches[1]', 'top' );
		add_rewrite_rule( '^project/([0-9]+)/?$', 'index.php?lidev_screen=project&lidev_project_id=$matches[1]', 'top' );
		add_rewrite_rule( '^signoff-settings/?$', 'index.php?lidev_screen=settings', 'top' );
	}

	/**
	 * Keep these URLs out of the public query vars list from being stripped.
	 *
	 * @since 1.2.0
	 * @param list<string> $vars Query vars.
	 * @return list<string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'lidev_screen';
		$vars[] = 'lidev_project_id';

		return $vars;
	}

	/**
	 * Current screen slug, or an empty string.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public static function screen(): string {
		$screen = sanitize_key( (string) get_query_var( 'lidev_screen' ) );

		return in_array( $screen, array( 'project', 'new', 'edit', 'settings' ), true ) ? $screen : '';
	}

	/**
	 * Project addressed by the current URL.
	 *
	 * @since 1.2.0
	 * @return WP_Post|null
	 */
	public static function project(): ?WP_Post {
		$post = get_post( absint( get_query_var( 'lidev_project_id' ) ) );

		if ( $post instanceof WP_Post && 'lidev_project' === $post->post_type ) {
			return $post;
		}

		return null;
	}

	/**
	 * Block Client and logged-out visitors. Invalid projects fall through to the 404.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function guard(): void {
		$screen = self::screen();

		if ( '' === $screen ) {
			return;
		}

		if ( ! current_user_can( 'lidev_view_all_projects' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( 'settings' === $screen && ! current_user_can( 'lidev_manage_settings' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( in_array( $screen, array( 'project', 'edit' ), true ) && ! self::project() ) {
			global $wp_query;

			$wp_query->set_404();
			status_header( 404 );
			nocache_headers();
			return;
		}

		if ( in_array( $screen, array( 'new', 'edit' ), true ) && ! current_user_can( 'lidev_manage_projects' ) ) {
			$project = self::project();
			$target  = ( 'edit' === $screen && $project ) ? self::project_url( (int) $project->ID ) : home_url( '/' );
			wp_safe_redirect( $target );
			exit;
		}

		status_header( 200 );
	}

	/**
	 * Do not 404 a real Team screen.
	 *
	 * @since 1.2.0
	 * @param bool     $preempt Whether 404 handling was preempted.
	 * @param WP_Query $query   Main query.
	 * @return bool
	 */
	public static function prevent_404( $preempt, $query ): bool {
		if ( true === $preempt || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return (bool) $preempt;
		}

		$screen = self::screen();

		if ( '' === $screen ) {
			return false;
		}

		if ( in_array( $screen, array( 'project', 'edit' ), true ) && ! self::project() ) {
			return false;
		}

		$query->is_404 = false;

		return true;
	}

	/**
	 * Swap in a sign-off template without editing the theme's existing templates.
	 *
	 * @since 1.2.0
	 * @param string $template Current template path.
	 * @return string
	 */
	public static function template( string $template ): string {
		$screen = self::screen();

		if ( '' !== $screen ) {
		if ( in_array( $screen, array( 'project', 'edit' ), true ) && ! self::project() ) {
			$missing = get_404_template();

			return $missing ? $missing : $template;
		}

		return LIDEV_PATH . 'template-parts/signoffs/screen.php';
		}

		if ( is_front_page() && current_user_can( 'lidev_view_all_projects' ) ) {
			return LIDEV_PATH . 'template-parts/signoffs/dashboard.php';
		}

		return $template;
	}

	/**
	 * Load sign-off CSS only on these screens.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function enqueue(): void {
		$screen  = self::screen();
		$on_home = is_front_page() && current_user_can( 'lidev_view_all_projects' );

		if ( '' === $screen && ! $on_home ) {
			return;
		}

		wp_enqueue_style(
			'lidev-signoffs',
			lidev_asset_uri( 'assets/css/signoffs.css' ),
			array( 'lidev-style' ),
			lidev_asset_version( 'assets/css/signoffs.css' )
		);
	}

	/**
	 * Browser title for Team screens.
	 *
	 * @since 1.2.0
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public static function title( array $parts ): array {
		$screen = self::screen();
		$titles = array(
			'new'      => __( 'Add project', 'li-dev-theme' ),
			'settings' => __( 'Sign-off settings', 'li-dev-theme' ),
		);

		if ( isset( $titles[ $screen ] ) ) {
			$parts['title'] = $titles[ $screen ];
			return $parts;
		}

		$project = self::project();

		if ( $project && 'project' === $screen ) {
			$parts['title'] = get_the_title( $project );
			return $parts;
		}

		if ( $project && 'edit' === $screen ) {
			$parts['title'] = sprintf(
				/* translators: %s: project name. */
				__( 'Edit %s', 'li-dev-theme' ),
				get_the_title( $project )
			);
			return $parts;
		}

		if ( is_front_page() && current_user_can( 'lidev_view_all_projects' ) ) {
			$parts['title'] = __( 'Projects', 'li-dev-theme' );
		}

		return $parts;
	}

	/**
	 * @since 1.2.0
	 */
	public static function new_url(): string {
		return home_url( user_trailingslashit( 'project/new' ) );
	}

	/**
	 * @since 1.2.0
	 */
	public static function project_url( int $project_id ): string {
		return home_url( user_trailingslashit( 'project/' . $project_id ) );
	}

	/**
	 * @since 1.2.0
	 */
	public static function edit_url( int $project_id ): string {
		return home_url( user_trailingslashit( 'project/' . $project_id . '/edit' ) );
	}

	/**
	 * @since 1.2.0
	 */
	public static function settings_url(): string {
		return home_url( user_trailingslashit( 'signoff-settings' ) );
	}
}
