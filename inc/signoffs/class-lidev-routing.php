<?php
/**
 * Front-end routes for sign-off screens.
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
		add_rewrite_tag( '%lidev_phase_id%', '([0-9]+)' );

		add_rewrite_rule( '^project/new/?$', 'index.php?lidev_screen=new', 'top' );
		add_rewrite_rule( '^project/([0-9]+)/edit/?$', 'index.php?lidev_screen=edit&lidev_project_id=$matches[1]', 'top' );
		add_rewrite_rule( '^project/([0-9]+)/phase/([0-9]+)/?$', 'index.php?lidev_screen=sign&lidev_project_id=$matches[1]&lidev_phase_id=$matches[2]', 'top' );
		add_rewrite_rule( '^project/([0-9]+)/?$', 'index.php?lidev_screen=project&lidev_project_id=$matches[1]', 'top' );
		add_rewrite_rule( '^signoff-settings/?$', 'index.php?lidev_screen=settings', 'top' );
		add_rewrite_rule( '^documentation/?$', 'index.php?lidev_screen=docs', 'top' );
		add_rewrite_rule( '^activity/?$', 'index.php?lidev_screen=activity', 'top' );
		add_rewrite_rule( '^archived/?$', 'index.php?lidev_screen=archive', 'top' );
		add_rewrite_rule( '^access/?$', 'index.php?lidev_screen=access', 'top' );
	}

	/**
	 * Store the documentation route once, after other plugins have registered theirs.
	 *
	 * @since 1.7.0
	 * @return void
	 */
	public static function maybe_flush_docs(): void {
		if ( '1' === get_option( 'lidev_docs_rewrite' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'lidev_docs_rewrite', '1', false );
	}

	/**
	 * Store the archive route once, after other plugins have registered theirs.
	 *
	 * @since 1.8.0
	 * @return void
	 */
	public static function maybe_flush_archive(): void {
		if ( '1' === get_option( 'lidev_archive_rewrite' ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( 'lidev_archive_rewrite', '1', false );
	}

	/**
	 * @since 1.2.0
	 * @param list<string> $vars Query vars.
	 * @return list<string>
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'lidev_screen';
		$vars[] = 'lidev_project_id';
		$vars[] = 'lidev_phase_id';

		return $vars;
	}

	/**
	 * @since 1.2.0
	 * @return string
	 */
	public static function screen(): string {
		$screen = sanitize_key( (string) get_query_var( 'lidev_screen' ) );

		return in_array( $screen, array( 'project', 'new', 'edit', 'settings', 'access', 'sign', 'activity', 'docs', 'archive' ), true ) ? $screen : '';
	}

	/**
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
	 * Phase addressed by the current sign-off URL.
	 *
	 * @since 1.3.0
	 * @return array<string, mixed>|null
	 */
	public static function phase(): ?array {
		$phase = Lidev_Phase_Repository::get( absint( get_query_var( 'lidev_phase_id' ) ) );
		$project = self::project();

		if ( ! $phase || ! $project || (int) $phase['project_id'] !== (int) $project->ID ) {
			return null;
		}

		return $phase;
	}

	/**
	 * Block Client and logged-out visitors from Team screens.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public static function guard(): void {
		$screen = self::screen();

		if ( '' === $screen ) {
			return;
		}

		if ( 'access' === $screen || 'docs' === $screen ) {
			status_header( 200 );
			return;
		}

		if ( 'sign' === $screen ) {
			self::guard_sign();
			return;
		}

		if ( ! current_user_can( 'lidev_view_all_projects' ) ) {
			self::force_404();
			return;
		}

		if ( 'activity' === $screen && ! current_user_can( 'lidev_view_activity' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( 'settings' === $screen && ! current_user_can( 'lidev_manage_settings' ) ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( in_array( $screen, array( 'project', 'edit' ), true ) && ! self::project() ) {
			self::force_404();
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

		if ( in_array( $screen, array( 'access', 'sign', 'docs' ), true ) ) {
			$query->is_404 = false;
			return true;
		}

		if ( ! current_user_can( 'lidev_view_all_projects' ) ) {
			return false;
		}

		if ( in_array( $screen, array( 'project', 'edit' ), true ) && ! self::project() ) {
			return false;
		}

		$query->is_404 = false;

		return true;
	}

	/**
	 * @since 1.2.0
	 * @param string $template Current template path.
	 * @return string
	 */
	public static function template( string $template ): string {
		if ( is_404() ) {
			$missing = get_404_template();

			return $missing ? $missing : $template;
		}

		$screen = self::screen();

		if ( 'access' === $screen ) {
			return LIDEV_PATH . 'template-parts/signoffs/access.php';
		}

		if ( 'sign' === $screen ) {
			return LIDEV_PATH . 'template-parts/signoffs/phase.php';
		}

		if ( '' !== $screen ) {
			if ( in_array( $screen, array( 'project', 'edit' ), true ) && ! self::project() ) {
				$missing = get_404_template();

				return $missing ? $missing : $template;
			}

			return LIDEV_PATH . 'template-parts/signoffs/screen.php';
		}

		if ( ! is_front_page() ) {
			return $template;
		}

		if ( current_user_can( 'lidev_view_all_projects' ) ) {
			return LIDEV_PATH . 'template-parts/signoffs/dashboard.php';
		}

		if ( lidev_user_is_client_only() ) {
			return LIDEV_PATH . 'template-parts/signoffs/client-dashboard.php';
		}

		if ( ! is_user_logged_in() ) {
			return LIDEV_PATH . 'template-parts/signoffs/sign-in.php';
		}

		return $template;
	}

	/**
	 * @since 1.2.0
	 * @return void
	 */
	public static function enqueue(): void {
		$screen = self::screen();
		$on_home = is_front_page() && (
			! is_user_logged_in()
			|| current_user_can( 'lidev_view_all_projects' )
			|| lidev_user_is_client_only()
		);

		if ( '' === $screen && ! $on_home ) {
			return;
		}

		if ( is_404() ) {
			return;
		}

		wp_enqueue_style(
			'lidev-signoffs',
			lidev_asset_uri( 'assets/css/signoffs.css' ),
			array( 'lidev-style' ),
			lidev_asset_version( 'assets/css/signoffs.css' )
		);

		if ( 'sign' === $screen ) {
			wp_enqueue_script(
				'lidev-signoff',
				lidev_asset_uri( 'assets/js/signoff.js' ),
				array(),
				lidev_asset_version( 'assets/js/signoff.js' ),
				true
			);
		}

		if ( 'settings' === $screen || ( 'project' === $screen && current_user_can( 'lidev_send_phases' ) ) ) {
			wp_enqueue_script(
				'lidev-phase-message',
				lidev_asset_uri( 'assets/js/phase-message.js' ),
				array(),
				lidev_asset_version( 'assets/js/phase-message.js' ),
				true
			);
		}
	}

	/**
	 * @since 1.2.0
	 * @param array<string, string> $parts Title parts.
	 * @return array<string, string>
	 */
	public static function title( array $parts ): array {
		$screen = self::screen();
		$titles = array(
			'new'      => __( 'Add project', 'li-dev-theme' ),
			'settings' => __( 'Sign-off settings', 'li-dev-theme' ),
			'docs'     => __( 'Documentation', 'li-dev-theme' ),
			'activity' => __( 'Activity', 'li-dev-theme' ),
			'archive'  => __( 'Archive', 'li-dev-theme' ),
			'access'   => __( 'Sign in', 'li-dev-theme' ),
		);

		if ( isset( $titles[ $screen ] ) ) {
			$parts['title'] = $titles[ $screen ];
			return $parts;
		}

		$project = self::project();

		if ( $project && in_array( $screen, array( 'project', 'sign' ), true ) ) {
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

		if ( is_front_page() && ! is_user_logged_in() ) {
			$parts['title'] = __( 'Sign in', 'li-dev-theme' );
			return $parts;
		}

		if ( is_front_page() && ( current_user_can( 'lidev_view_all_projects' ) || lidev_user_is_client_only() ) ) {
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
	public static function settings_url( string $tab = '' ): string {
		$url = home_url( user_trailingslashit( 'signoff-settings' ) );
		$tab = sanitize_key( $tab );

		if ( in_array( $tab, array( 'email', 'reminders', 'messages' ), true ) ) {
			return add_query_arg( 'tab', $tab, $url );
		}

		return $url;
	}

	/**
	 * @since 1.7.0
	 */
	public static function docs_url(): string {
		return home_url( user_trailingslashit( 'documentation' ) );
	}

	/**
	 * @since 1.6.0
	 */
	public static function activity_url(): string {
		return home_url( user_trailingslashit( 'activity' ) );
	}

	/**
	 * @since 1.8.0
	 */
	public static function archive_url(): string {
		return home_url( user_trailingslashit( 'archived' ) );
	}

	/**
	 * Address of the phase review screen. Stage 04 fills in the signature form.
	 *
	 * @since 1.3.0
	 */
	public static function phase_url( int $project_id, int $phase_id ): string {
		return home_url( user_trailingslashit( 'project/' . $project_id . '/phase/' . $phase_id ) );
	}

	/**
	 * An assigned client may open an awaiting or signed phase. Everyone else does not.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private static function guard_sign(): void {
		$project = self::project();
		$phase   = self::phase();

		if ( $project && $phase && self::client_may_open_phase( (int) $project->ID, (string) $phase['status'] ) ) {
			status_header( 200 );
			return;
		}

		if ( $project && current_user_can( 'lidev_view_all_projects' ) ) {
			wp_safe_redirect( self::project_url( (int) $project->ID ) );
			exit;
		}

		self::force_404();
	}

	/**
	 * @since 1.3.0
	 * @param int    $project_id Project ID.
	 * @param string $status     Phase status.
	 * @return bool
	 */
	private static function client_may_open_phase( int $project_id, string $status ): bool {
		if ( ! is_user_logged_in() || ! lidev_user_is_client_only() ) {
			return false;
		}

		if ( lidev_project_is_archived( $project_id ) ) {
			return false;
		}

		if ( ! in_array( $status, array( 'awaiting', 'signed' ), true ) ) {
			return false;
		}

		return in_array( get_current_user_id(), lidev_project_client_ids( $project_id ), true );
	}

	/**
	 * @since 1.3.0
	 * @return void
	 */
	private static function force_404(): void {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}
