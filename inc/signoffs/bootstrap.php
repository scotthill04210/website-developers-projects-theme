<?php
/**
 * Sign-off data layer bootstrap.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIDEV_SCHEMA_VERSION', '5' );

require_once LIDEV_PATH . 'inc/signoffs/helpers.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-activity-repository.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-signoff-repository.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-phase-repository.php';
require_once LIDEV_PATH . 'inc/signoffs/log.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-install.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-roles.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-project.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-settings.php';
require_once LIDEV_PATH . 'inc/signoffs/clients.php';
require_once LIDEV_PATH . 'inc/signoffs/mail.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-routing.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-team-actions.php';
require_once LIDEV_PATH . 'inc/signoffs/dashboard.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-magic-link.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-front-auth.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-privacy.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-signoff.php';
require_once LIDEV_PATH . 'inc/signoffs/class-lidev-pdf.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once LIDEV_PATH . 'inc/signoffs/class-lidev-cli.php';
}

add_action( 'after_switch_theme', array( 'Lidev_Install', 'run' ) );
add_action( 'init', array( 'Lidev_Routing', 'register' ), 4 );
add_action( 'init', array( 'Lidev_Routing', 'maybe_flush_docs' ), 99 );
add_action( 'init', array( 'Lidev_Routing', 'maybe_flush_archive' ), 99 );
add_action( 'init', array( 'Lidev_Install', 'maybe_run' ), 5 );
add_action( 'init', array( 'Lidev_Project', 'register' ) );
add_filter( 'query_vars', array( 'Lidev_Routing', 'query_vars' ) );
add_action( 'template_redirect', array( 'Lidev_Routing', 'guard' ) );
add_filter( 'pre_handle_404', array( 'Lidev_Routing', 'prevent_404' ), 10, 2 );
add_filter( 'template_include', array( 'Lidev_Routing', 'template' ) );
add_filter( 'document_title_parts', array( 'Lidev_Routing', 'title' ) );
add_action( 'wp_enqueue_scripts', array( 'Lidev_Routing', 'enqueue' ) );
add_action( 'admin_init', array( 'Lidev_Roles', 'block_client_admin' ) );
add_filter( 'show_admin_bar', array( 'Lidev_Roles', 'filter_admin_bar' ) );
add_filter( 'login_redirect', array( 'Lidev_Roles', 'filter_login_redirect' ), 10, 3 );
add_action( 'admin_post_lidev_create_project', array( 'Lidev_Team_Actions', 'create_project' ) );
add_action( 'admin_post_lidev_update_project', array( 'Lidev_Team_Actions', 'update_project' ) );
add_action( 'admin_post_lidev_add_client', array( 'Lidev_Team_Actions', 'add_client' ) );
add_action( 'admin_post_lidev_send_phase', array( 'Lidev_Team_Actions', 'send_phase' ) );
add_action( 'wp_ajax_lidev_preview_phase_email', array( 'Lidev_Team_Actions', 'preview_phase_email' ) );
add_action( 'admin_post_lidev_save_settings', array( 'Lidev_Team_Actions', 'save_settings' ) );
add_action( 'wp_ajax_lidev_create_project', array( 'Lidev_Team_Actions', 'create_project' ) );
add_action( 'wp_ajax_lidev_update_project', array( 'Lidev_Team_Actions', 'update_project' ) );
add_action( 'wp_ajax_lidev_add_client', array( 'Lidev_Team_Actions', 'add_client' ) );
add_action( 'wp_ajax_lidev_send_phase', array( 'Lidev_Team_Actions', 'send_phase' ) );
add_action( 'wp_ajax_lidev_save_settings', array( 'Lidev_Team_Actions', 'save_settings' ) );
add_action( 'admin_post_lidev_team_login', array( 'Lidev_Front_Auth', 'team_login' ) );
add_action( 'admin_post_nopriv_lidev_team_login', array( 'Lidev_Front_Auth', 'team_login' ) );
add_action( 'admin_post_lidev_request_magic_link', array( 'Lidev_Front_Auth', 'request_magic_link' ) );
add_action( 'admin_post_nopriv_lidev_request_magic_link', array( 'Lidev_Front_Auth', 'request_magic_link' ) );
add_action( 'admin_post_lidev_lost_password', array( 'Lidev_Front_Auth', 'lost_password' ) );
add_action( 'admin_post_nopriv_lidev_lost_password', array( 'Lidev_Front_Auth', 'lost_password' ) );
add_filter( 'retrieve_password_notification_email', array( 'Lidev_Mail', 'brand_password_reset' ), 10, 4 );
add_filter( 'wp_new_user_notification_email', array( 'Lidev_Mail', 'brand_new_user' ), 10, 3 );
add_filter( 'password_change_email', array( 'Lidev_Mail', 'brand_password_changed' ), 10, 3 );
add_action( 'admin_post_lidev_sign_phase', array( 'Lidev_Signoff', 'submit' ) );
add_action( 'lidev_phase_signed', array( 'Lidev_Pdf', 'on_signed' ) );
add_action( 'lidev_phase_signed', array( 'Lidev_Mail', 'on_signed' ), 20 );
add_action( 'init', array( 'Lidev_Mail', 'schedule' ), 12 );
add_action( 'lidev_send_reminders', array( 'Lidev_Mail', 'send_due_reminders' ) );
add_action( 'admin_post_lidev_pause_reminders', array( 'Lidev_Team_Actions', 'pause_reminders' ) );
add_action( 'admin_post_lidev_archive_project', array( 'Lidev_Team_Actions', 'archive_project' ) );
add_action( 'admin_post_lidev_run_reminders', array( 'Lidev_Team_Actions', 'run_reminders' ) );
add_action( 'admin_post_lidev_download_pdf', array( 'Lidev_Pdf', 'download' ) );
add_action( 'template_redirect', array( 'Lidev_Magic_Link', 'maybe_redeem' ), 1 );
add_action( 'init', array( 'Lidev_Privacy', 'mark_uncached' ), 0 );
add_action( 'send_headers', array( 'Lidev_Privacy', 'send_cache_headers' ) );
add_action( 'template_redirect', array( 'Lidev_Privacy', 'redirect_public' ), 100 );
add_action( 'template_redirect', array( 'Lidev_Privacy', 'send_robots_header' ), 100 );
add_filter( 'wp_robots', array( 'Lidev_Privacy', 'robots' ) );
add_filter( 'rest_pre_dispatch', array( 'Lidev_Roles', 'block_rest' ), 5, 3 );
add_filter( 'rest_pre_dispatch', array( 'Lidev_Privacy', 'redirect_rest' ), 100, 3 );
add_filter( 'auth_cookie_expiration', array( 'Lidev_Magic_Link', 'cookie_expiration' ), 10, 3 );
