<?php
/**
 * Shared sign-off helpers.
 *
 * @package LIDevTheme
 * @since   1.1.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Custom table name. Only the three sign-off tables are allowed.
 *
 * @since 1.1.0
 * @param string $suffix phases, signoffs, activity, or magic.
 * @return string
 */
function lidev_table( string $suffix ): string {
	$allowed = array( 'phases', 'signoffs', 'activity', 'magic' );

	if ( ! in_array( $suffix, $allowed, true ) ) {
		return '';
	}

	global $wpdb;

	return $wpdb->prefix . 'lidev_' . $suffix;
}

/**
 * Phase slugs in product order.
 *
 * @since 1.1.0
 * @return list<string>
 */
function lidev_phase_types(): array {
	return array( 'content', 'design', 'launch' );
}

/**
 * Human label for a phase slug.
 *
 * @since 1.1.0
 * @param string $type Phase slug.
 * @return string
 */
function lidev_phase_label( string $type ): string {
	$labels = array(
		'content' => __( 'Wireframe', 'li-dev-theme' ),
		'design'  => __( 'Figma', 'li-dev-theme' ),
		'launch'  => __( 'Pre-launch review', 'li-dev-theme' ),
	);

	return $labels[ $type ] ?? $type;
}

/**
 * Field label for the link a phase sends to the client.
 *
 * @since 1.2.0
 * @param string $type Phase slug.
 * @return string
 */
function lidev_phase_link_label( string $type ): string {
	$labels = array(
		'content' => __( 'Wireframe link', 'li-dev-theme' ),
		'design'  => __( 'Figma link', 'li-dev-theme' ),
		'launch'  => __( 'Staging site link', 'li-dev-theme' ),
	);

	return $labels[ $type ] ?? __( 'Link', 'li-dev-theme' );
}

/**
 * Placeholders a phase message can insert. Replaced per client when the email is built.
 *
 * @since 1.7.0
 * @return array<string, string> Token key and a short description.
 */
function lidev_message_tokens(): array {
	return array(
		'client_name'  => __( 'Client name', 'li-dev-theme' ),
		'project_name' => __( 'Project name', 'li-dev-theme' ),
		'phase_name'   => __( 'Phase name', 'li-dev-theme' ),
		'link'         => __( 'Wireframe, Figma, or staging link', 'li-dev-theme' ),
		'domain'       => __( 'Domain', 'li-dev-theme' ),
		'site_name'    => __( 'Site name', 'li-dev-theme' ),
	);
}

/**
 * Values that fill the placeholders for one recipient.
 *
 * @since 1.7.0
 * @param string $client_name  Recipient display name.
 * @param string $project_name Project title.
 * @param string $phase_name   Phase title.
 * @param string $link         External link for this phase.
 * @param string $domain       Project domain.
 * @return array<string, string>
 */
function lidev_phase_message_values( string $client_name, string $project_name, string $phase_name, string $link, string $domain ): array {
	return array(
		'client_name'  => $client_name,
		'project_name' => $project_name,
		'phase_name'   => $phase_name,
		'link'         => $link,
		'domain'       => $domain,
		'site_name'    => wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ),
	);
}

/**
 * Default phase message plus the optional note, with placeholders filled.
 *
 * An empty value leaves its placeholder in place.
 *
 * @since 1.7.0
 * @param string               $type   Phase slug.
 * @param string               $extra  Optional note typed on the project.
 * @param array<string, string> $values Token values.
 * @return string
 */
function lidev_compose_phase_message( string $type, string $extra, array $values ): string {
	$settings = Lidev_Settings::get();
	$key      = 'message_' . $type;
	$base     = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';
	$extra = trim( lidev_sanitize_email_html( $extra ) );
	$text  = trim( lidev_sanitize_email_html( $base ) );

	if ( '' !== $extra ) {
		$text = '' === $text ? $extra : $text . "\n\n" . $extra;
	}

	$has_html = (bool) preg_match( '/<[a-z]/i', $text );
	$map      = array();

	foreach ( array_keys( lidev_message_tokens() ) as $token ) {
		$value = isset( $values[ $token ] ) ? trim( wp_strip_all_tags( (string) $values[ $token ] ) ) : '';

		if ( '' === $value ) {
			$map[ '{' . $token . '}' ] = '{' . $token . '}';
			continue;
		}

		$map[ '{' . $token . '}' ] = $has_html ? esc_html( $value ) : $value;
	}

	return trim( strtr( $text, $map ) );
}

/**
 * Tags a phase email message may contain.
 *
 * @since 1.7.0
 * @return array<string, array<string, bool>>
 */
function lidev_email_html_allowed(): array {
	return array(
		'p'      => array(),
		'br'     => array(),
		'strong' => array(),
		'em'     => array(),
		'ul'     => array(),
		'ol'     => array(),
		'li'     => array(),
		'a'      => array(
			'href' => true,
			'rel'  => true,
		),
	);
}

/**
 * Keep bold, italic, lists, and links. Anything else is removed.
 *
 * Plain text is left as typed, including line breaks.
 *
 * @since 1.7.0
 * @param string $html Message from the editor or from storage.
 * @return string
 */
function lidev_sanitize_email_html( string $html ): string {
	$html = str_replace( array( "\r\n", "\r" ), "\n", $html );
	$html = preg_replace( '/<b\b[^>]*>/i', '<strong>', $html ) ?? $html;
	$html = preg_replace( '/<\/b>/i', '</strong>', $html ) ?? $html;
	$html = preg_replace( '/<i\b[^>]*>/i', '<em>', $html ) ?? $html;
	$html = preg_replace( '/<\/i>/i', '</em>', $html ) ?? $html;
	$html = preg_replace( '/<div\b[^>]*>/i', '<p>', $html ) ?? $html;
	$html = preg_replace( '/<\/div>/i', '</p>', $html ) ?? $html;
	$html = preg_replace( '/<\/?span\b[^>]*>/i', '', $html ) ?? $html;

	$clean = wp_kses( $html, lidev_email_html_allowed() );

	$clean = preg_replace_callback(
		'/<a\b[^>]*>/i',
		static function ( array $match ): string {
			if ( ! preg_match( '/href=(["\'])(.*?)\1/i', $match[0], $href ) ) {
				return '';
			}

			$url = esc_url( html_entity_decode( $href[2], ENT_QUOTES, 'UTF-8' ), array( 'http', 'https', 'mailto' ) );

			if ( '' === $url ) {
				return '';
			}

			return '<a href="' . esc_url( $url ) . '" rel="noopener noreferrer">';
		},
		$clean
	) ?? $clean;

	return trim( $clean );
}

/**
 * Message HTML safe to print. Plain text becomes paragraphs.
 *
 * @since 1.7.0
 * @param string $html   Stored or composed message.
 * @param bool   $inline Add email-client styles on the tags.
 * @return string
 */
function lidev_format_email_html( string $html, bool $inline = false ): string {
	$html = lidev_sanitize_email_html( $html );

	if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
		return '';
	}

	if ( ! preg_match( '/<(p|ul|ol|br|strong|em|a)\b/i', $html ) ) {
		$parts = preg_split( "/\n{2,}/", $html ) ?: array( $html );
		$built = '';

		foreach ( $parts as $part ) {
			$part = trim( $part );

			if ( '' === $part ) {
				continue;
			}

			$built .= '<p>' . nl2br( esc_html( $part ), false ) . '</p>';
		}

		$html = $built;
	} elseif ( ! preg_match( '/<(p|ul|ol)\b/i', $html ) ) {
		$html = '<p>' . $html . '</p>';
	}

	if ( ! $inline ) {
		return $html;
	}

	$styled = array(
		'<p>'  => '<p style="margin:0 0 12px;color:#1e1e21;">',
		'<ul>' => '<ul style="margin:0 0 12px;padding-left:20px;color:#1e1e21;">',
		'<ol>' => '<ol style="margin:0 0 12px;padding-left:20px;color:#1e1e21;">',
		'<a '  => '<a style="color:#1e1e21;" ',
	);

	return str_replace( array_keys( $styled ), array_values( $styled ), $html );
}

/**
 * Plain-text version of an email message.
 *
 * @since 1.7.0
 * @param string $html Message HTML or plain text.
 * @return string
 */
function lidev_email_plain( string $html ): string {
	$html = preg_replace( '/<li\b[^>]*>/i', "\n- ", $html ) ?? $html;
	$html = preg_replace( '/<br\s*\/?>/i', "\n", $html ) ?? $html;
	$html = preg_replace( '/<\/(p|ul|ol|li)>/i', "\n", $html ) ?? $html;
	$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( "/[ \t]+\n/", "\n", $text ) ?? $text;
	$text = preg_replace( "/\n{3,}/", "\n\n", $text ) ?? $text;

	return trim( $text );
}

/**
 * Buttons that insert a placeholder into a textarea.
 *
 * @since 1.7.0
 * @param string $target_id Textarea element ID.
 * @return void
 */
function lidev_message_token_controls( string $target_id ): void {
	echo '<div class="lidev-signoffs__tokens">';

	foreach ( lidev_message_tokens() as $key => $label ) {
		$token = '{' . $key . '}';
		printf(
			'<button type="button" class="lidev-signoffs__token" data-lidev-insert="%1$s" data-lidev-target="%2$s" title="%3$s">%4$s</button>',
			esc_attr( $token ),
			esc_attr( $target_id ),
			esc_attr( $label ),
			esc_html( $token )
		);
	}

	echo '</div>';
}

/**
 * The sentence the client agrees to when signing a phase.
 *
 * @since 1.4.0
 * @param string $type Phase slug.
 * @return string
 */
function lidev_phase_statement( string $type ): string {
	$statements = array(
		'content' => __( 'I approve the content and structure shown in the wireframe.', 'li-dev-theme' ),
		'design'  => __( 'I approve the design shown in the Figma file.', 'li-dev-theme' ),
		'launch'  => __( 'I approve this website to launch live.', 'li-dev-theme' ),
	);

	return $statements[ $type ] ?? '';
}

/**
 * Short status label for chips and filters.
 *
 * @since 1.2.0
 * @param string $status Phase status.
 * @return string
 */
function lidev_phase_status_label( string $status ): string {
	$labels = array(
		'not_sent' => __( 'Not sent', 'li-dev-theme' ),
		'awaiting' => __( 'Awaiting', 'li-dev-theme' ),
		'signed'   => __( 'Signed', 'li-dev-theme' ),
	);

	return $labels[ $status ] ?? $status;
}

/**
 * Require an http or https URL.
 *
 * @since 1.2.0
 * @param string $url Raw URL.
 * @return string Empty when the value is not a usable URL.
 */
function lidev_sanitize_external_url( string $url ): string {
	$url = trim( $url );

	if ( ! preg_match( '#^https?://#i', $url ) ) {
		return '';
	}

	$clean = esc_url_raw( $url );

	return filter_var( $clean, FILTER_VALIDATE_URL ) ? $clean : '';
}

/**
 * Allowed phase statuses, in transition order.
 *
 * @since 1.1.0
 * @return list<string>
 */
function lidev_phase_statuses(): array {
	return array( 'not_sent', 'awaiting', 'signed' );
}

/**
 * Client user IDs stored on a project.
 *
 * @since 1.1.0
 * @param int $project_id Project post ID.
 * @return list<int>
 */
function lidev_project_client_ids( int $project_id ): array {
	$ids = get_post_meta( $project_id, '_lidev_client_ids', true );

	if ( ! is_array( $ids ) ) {
		return array();
	}

	$clean = array();

	foreach ( $ids as $id ) {
		$id = absint( $id );

		if ( $id > 0 ) {
			$clean[] = $id;
		}
	}

	return array_values( array_unique( $clean ) );
}

/**
 * Keep only IDs of users that exist.
 *
 * @since 1.1.0
 * @param mixed $ids Raw IDs.
 * @return list<int>
 */
function lidev_sanitize_user_ids( $ids ): array {
	if ( ! is_array( $ids ) ) {
		return array();
	}

	$clean = array();

	foreach ( $ids as $id ) {
		$id = absint( $id );

		if ( $id > 0 && get_userdata( $id ) ) {
			$clean[] = $id;
		}
	}

	return array_values( array_unique( $clean ) );
}

/**
 * Whether a user may see a project. Team capability wins; clients must be assigned.
 *
 * @since 1.1.0
 * @param int $project_id Project post ID.
 * @param int $user_id    User ID. 0 uses the current user.
 * @return bool
 */
function lidev_user_can_view_project( int $project_id, int $user_id = 0 ): bool {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id < 1 || 'lidev_project' !== get_post_type( $project_id ) ) {
		return false;
	}

	if ( user_can( $user_id, 'lidev_view_all_projects' ) ) {
		return true;
	}

	if ( ! user_can( $user_id, 'lidev_view_own_projects' ) ) {
		return false;
	}

	return in_array( $user_id, lidev_project_client_ids( $project_id ), true );
}

/**
 * A client account with no team or admin capability.
 *
 * @since 1.1.0
 * @param int $user_id User ID. 0 uses the current user.
 * @return bool
 */
function lidev_user_is_client_only( int $user_id = 0 ): bool {
	$user_id = $user_id > 0 ? $user_id : get_current_user_id();

	if ( $user_id < 1 || ! user_can( $user_id, 'lidev_view_own_projects' ) ) {
		return false;
	}

	$staff_caps = array( 'lidev_view_all_projects', 'lidev_manage_projects', 'manage_options' );

	foreach ( $staff_caps as $cap ) {
		if ( user_can( $user_id, $cap ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Remote address for the activity log. Not taken from forwarded headers.
 *
 * @since 1.1.0
 * @return string
 */
function lidev_request_ip(): string {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : '';

	return substr( $ip, 0, 45 );
}

/**
 * Browser string for a sign-off record.
 *
 * @since 1.1.0
 * @return string
 */
function lidev_request_user_agent(): string {
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';

	return substr( $agent, 0, 255 );
}
