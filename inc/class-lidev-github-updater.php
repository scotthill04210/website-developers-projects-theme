<?php
/**
 * Offer theme updates from the public GitHub repository.
 *
 * Administrators install the update in wp-admin. The package is the main branch.
 *
 * @package LIDevTheme
 * @since   1.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compares the installed theme with GitHub and keeps the update inside this directory.
 *
 * @since 1.9.0
 */
final class Lidev_Github_Updater {

	private const THEME = 'li-dev-theme';

	private const REPO = 'scotthill04210/website-developers-projects-theme';

	private const BRANCH = 'main';

	private const COMMIT_OPTION = 'lidev_github_commit';

	private const PENDING_OPTION = 'lidev_github_pending_commit';

	/**
	 * @since 1.9.0
	 * @param object $transient Theme update transient.
	 * @return object
	 */
	public static function check( $transient ) {
		if ( ! is_object( $transient ) || empty( $transient->checked[ self::THEME ] ) ) {
			return $transient;
		}

		$release = self::remote_release();

		if ( null === $release ) {
			return $transient;
		}

		$theme         = wp_get_theme( self::THEME );
		$local_version = (string) $theme->get( 'Version' );
		$installed     = (string) get_option( self::COMMIT_OPTION, '' );
		$sha_changed   = self::sha_changed( $installed, $release['sha'] );
		$version_newer = version_compare( $release['version'], $local_version, '>' );

		unset( $transient->response[ self::THEME ] );

		if ( '' === $installed && ! $version_newer ) {
			update_option( self::COMMIT_OPTION, $release['sha'], false );
			$transient->no_update[ self::THEME ] = self::info( $local_version, '' );
			return $transient;
		}

		if ( ! $sha_changed && ! $version_newer ) {
			$transient->no_update[ self::THEME ] = self::info( $local_version, '' );
			return $transient;
		}

		// A commit hash is not a higher version to version_compare(). A numeric suffix is.
		$new_version = $version_newer ? $release['version'] : $local_version . '.1';

		update_option( self::PENDING_OPTION, $release['sha'], false );
		$transient->response[ self::THEME ] = self::info( $new_version, self::package_url( $release['sha'] ) );

		return $transient;
	}

	/**
	 * GitHub archives use the repository name. WordPress installs that folder name, so rename it first.
	 *
	 * @since 1.9.0
	 * @param string|WP_Error $source        Selected package directory.
	 * @param string          $remote_source Working directory.
	 * @param WP_Upgrader     $upgrader      Upgrader instance.
	 * @param array           $extra         Theme being installed.
	 * @return string|WP_Error
	 */
	public static function rename_source( $source, $remote_source, $upgrader, $extra ) {
		unset( $upgrader );

		if ( is_wp_error( $source ) || empty( $extra['theme'] ) || self::THEME !== $extra['theme'] ) {
			return $source;
		}

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			return $source;
		}

		$style = $wp_filesystem->get_contents( trailingslashit( $source ) . 'style.css' );

		if ( ! is_string( $style ) || ! preg_match( '/^Theme Name:\s*LI Dev Theme\s*$/mi', $style ) ) {
			return new WP_Error(
				'lidev_unexpected_package',
				__( 'The update package is not LI Dev Theme.', 'li-dev-theme' )
			);
		}

		$from    = untrailingslashit( $source );
		$desired = trailingslashit( $remote_source ) . self::THEME;

		if ( trailingslashit( $from ) === trailingslashit( $desired ) ) {
			return trailingslashit( $desired );
		}

		if ( $wp_filesystem->exists( $desired ) ) {
			$wp_filesystem->delete( $desired, true );
		}

		if ( ! $wp_filesystem->move( $from, $desired ) ) {
			return new WP_Error(
				'lidev_rename_failed',
				__( 'Could not prepare the theme update.', 'li-dev-theme' )
			);
		}

		return trailingslashit( $desired );
	}

	/**
	 * Remember the commit that was just installed.
	 *
	 * @since 1.9.0
	 * @param WP_Upgrader $upgrader Upgrader instance.
	 * @param array       $extra    Install details.
	 * @return void
	 */
	public static function record_install( $upgrader, $extra ): void {
		unset( $upgrader );

		$slugs = array();

		if ( isset( $extra['theme'] ) && is_string( $extra['theme'] ) ) {
			$slugs[] = $extra['theme'];
		}

		if ( isset( $extra['themes'] ) && is_array( $extra['themes'] ) ) {
			foreach ( $extra['themes'] as $slug ) {
				if ( is_string( $slug ) ) {
					$slugs[] = $slug;
				}
			}
		}

		if ( ! in_array( self::THEME, $slugs, true ) ) {
			return;
		}

		$pending = (string) get_option( self::PENDING_OPTION, '' );

		if ( ! preg_match( '/^[a-f0-9]{40}$/', $pending ) ) {
			return;
		}

		update_option( self::COMMIT_OPTION, $pending, false );
		delete_option( self::PENDING_OPTION );
	}

	/**
	 * A manual “Check again” should look at GitHub immediately.
	 *
	 * @since 1.9.0
	 * @return void
	 */
	public static function forget_remote(): void {
		delete_site_transient( 'lidev_github_remote' );
	}

	/**
	 * @since 1.9.0
	 * @param string $version Version shown in wp-admin.
	 * @param string $package Zip URL, empty when no update is offered.
	 * @return array{theme: string, new_version: string, url: string, package: string, requires: string, requires_php: string, tested: string}
	 */
	private static function info( string $version, string $package ): array {
		$theme = wp_get_theme( self::THEME );

		return array(
			'theme'        => self::THEME,
			'new_version'  => $version,
			'url'          => 'https://github.com/' . self::REPO,
			'package'      => $package,
			'requires'     => (string) $theme->get( 'RequiresWP' ),
			'requires_php' => (string) $theme->get( 'RequiresPHP' ),
			'tested'       => (string) $theme->get( 'Tested up to' ),
		);
	}

	/**
	 * @since 1.9.0
	 * @param string $installed Saved commit.
	 * @param string $remote    Commit on main.
	 * @return bool
	 */
	private static function sha_changed( string $installed, string $remote ): bool {
		if ( ! preg_match( '/^[a-f0-9]{40}$/', $installed ) || ! preg_match( '/^[a-f0-9]{40}$/', $remote ) ) {
			return false;
		}

		return ! hash_equals( $installed, $remote );
	}

	/**
	 * @since 1.9.0
	 * @return array{sha: string, version: string}|null
	 */
	private static function remote_release(): ?array {
		$cached = get_site_transient( 'lidev_github_remote' );

		if ( is_array( $cached ) && isset( $cached['sha'], $cached['version'] ) && is_string( $cached['sha'] ) && is_string( $cached['version'] ) ) {
			return array(
				'sha'     => $cached['sha'],
				'version' => $cached['version'],
			);
		}

		$sha     = self::remote_sha();
		$version = self::remote_version();

		if ( null === $sha || null === $version ) {
			return null;
		}

		$release = array(
			'sha'     => $sha,
			'version' => $version,
		);
		set_site_transient( 'lidev_github_remote', $release, 30 * MINUTE_IN_SECONDS );

		return $release;
	}

	/**
	 * @since 1.9.0
	 * @return string|null
	 */
	private static function remote_sha(): ?string {
		$response = self::get( 'https://api.github.com/repos/' . self::REPO . '/commits/' . self::BRANCH );

		if ( null === $response ) {
			return null;
		}

		$data = json_decode( $response, true );
		$sha  = is_array( $data ) ? (string) ( $data['sha'] ?? '' ) : '';

		if ( ! preg_match( '/^[a-f0-9]{40}$/', $sha ) ) {
			return null;
		}

		return $sha;
	}

	/**
	 * @since 1.9.0
	 * @return string|null
	 */
	private static function remote_version(): ?string {
		$body = self::get( 'https://raw.githubusercontent.com/' . self::REPO . '/' . self::BRANCH . '/style.css' );

		if ( null === $body || ! preg_match( '/^Version:\s*(\S+)/mi', $body, $found ) ) {
			return null;
		}

		return $found[1];
	}

	/**
	 * @since 1.9.0
	 * @param string $sha Commit to download.
	 * @return string
	 */
	private static function package_url( string $sha ): string {
		return 'https://github.com/' . self::REPO . '/archive/' . $sha . '.zip';
	}

	/**
	 * @since 1.9.0
	 * @param string $url GitHub URL.
	 * @return string|null
	 */
	private static function get( string $url ): ?string {
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 8,
				'headers' => array(
					'Accept'     => 'application/vnd.github+json',
					'User-Agent' => 'LI-Dev-Theme',
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );

		return is_string( $body ) && '' !== $body ? $body : null;
	}
}

add_filter( 'pre_set_site_transient_update_themes', array( 'Lidev_Github_Updater', 'check' ) );
add_filter( 'upgrader_source_selection', array( 'Lidev_Github_Updater', 'rename_source' ), 10, 4 );
add_action( 'upgrader_process_complete', array( 'Lidev_Github_Updater', 'record_install' ), 10, 2 );
add_action( 'delete_site_transient_update_themes', array( 'Lidev_Github_Updater', 'forget_remote' ) );
