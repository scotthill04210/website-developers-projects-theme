<?php
/**
 * Send wp_mail() through Microsoft Graph as a shared mailbox.
 *
 * Credentials stay in wp-config.php. When those constants are missing, this
 * class does nothing and WordPress sends the message itself.
 *
 * Graph accepts one body. HTML mail is sent as HTML. The plain-text
 * alternative stays in the theme mailer for the normal WordPress path.
 *
 * @package LIDevTheme
 * @since   1.9.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * App-only Microsoft 365 mail for the sign-off system.
 *
 * @since 1.9.0
 */
final class Lidev_Graph_Mailer {

	private const TOKEN_KEY = 'lidev_graph_token';

	private const MAX_ATTACHMENT_BYTES = 3145728;

	/**
	 * True when every required constant is present and the sender is an email address.
	 *
	 * @since 1.9.0
	 * @return bool
	 */
	public static function is_configured(): bool {
		if ( ! defined( 'LIDEV_GRAPH_TENANT_ID' ) || ! defined( 'LIDEV_GRAPH_CLIENT_ID' ) || ! defined( 'LIDEV_GRAPH_CLIENT_SECRET' ) || ! defined( 'LIDEV_GRAPH_SENDER' ) ) {
			return false;
		}

		foreach ( array( 'LIDEV_GRAPH_TENANT_ID', 'LIDEV_GRAPH_CLIENT_ID', 'LIDEV_GRAPH_CLIENT_SECRET' ) as $name ) {
			$value = constant( $name );

			if ( ! is_string( $value ) || '' === trim( $value ) ) {
				return false;
			}
		}

		return '' !== self::sender();
	}

	/**
	 * Shared mailbox address, or an empty string when it is not configured.
	 *
	 * @since 1.9.0
	 * @return string
	 */
	public static function sender(): string {
		if ( ! defined( 'LIDEV_GRAPH_SENDER' ) ) {
			return '';
		}

		$email = sanitize_email( (string) LIDEV_GRAPH_SENDER );

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Read-only transport line for the settings screen.
	 *
	 * @since 1.9.0
	 * @return string
	 */
	public static function status_label(): string {
		if ( ! self::is_configured() ) {
			return __( 'Mail transport: WordPress default', 'li-dev-theme' );
		}

		return sprintf(
			/* translators: %s: shared mailbox address. */
			__( 'Mail transport: Microsoft 365 (%s)', 'li-dev-theme' ),
			self::sender()
		);
	}

	/**
	 * Replace wp_mail() when Graph is configured. A null result leaves WordPress in charge.
	 *
	 * @since 1.9.0
	 * @param mixed                $short_circuit Result from an earlier callback.
	 * @param array<string, mixed> $atts          Mail arguments.
	 * @return bool|null
	 */
	public static function pre_wp_mail( $short_circuit, $atts ) {
		if ( null !== $short_circuit ) {
			return is_bool( $short_circuit ) ? $short_circuit : null;
		}

		if ( ! self::is_configured() || ! is_array( $atts ) ) {
			return null;
		}

		$payload = self::payload( $atts );

		if ( is_wp_error( $payload ) ) {
			self::reject( $payload );
			return false;
		}

		$result = self::post_mail( $payload );

		if ( is_wp_error( $result ) ) {
			self::reject( $result );
			return false;
		}

		return true;
	}

	/**
	 * @since 1.9.0
	 * @param array<string, mixed> $atts Mail arguments from wp_mail().
	 * @return array<string, mixed>|WP_Error
	 */
	private static function payload( array $atts ) {
		$to = self::addresses( $atts['to'] ?? '' );

		if ( ! $to ) {
			return new WP_Error( 'wp_mail_failed', __( 'A recipient is required.', 'li-dev-theme' ) );
		}

		$subject = trim( wp_strip_all_tags( (string) ( $atts['subject'] ?? '' ) ) );

		if ( '' === $subject ) {
			return new WP_Error( 'wp_mail_failed', __( 'A subject is required.', 'li-dev-theme' ) );
		}

		$headers     = self::headers( $atts['headers'] ?? array() );
		$attachments = self::attachments( $atts['attachments'] ?? array() );

		if ( is_wp_error( $attachments ) ) {
			return $attachments;
		}

		$from = array(
			'address' => self::sender(),
		);
		$name = sanitize_text_field( (string) Lidev_Settings::get()['from_name'] );

		if ( '' !== $name ) {
			$from['name'] = $name;
		}

		$message = array(
			'subject'      => $subject,
			'body'         => array(
				'contentType' => false !== stripos( $headers['content_type'], 'text/html' ) ? 'HTML' : 'Text',
				'content'     => (string) ( $atts['message'] ?? '' ),
			),
			'toRecipients' => self::graph_recipients( $to ),
			'from'         => array(
				'emailAddress' => $from,
			),
		);

		if ( $headers['cc'] ) {
			$message['ccRecipients'] = self::graph_recipients( $headers['cc'] );
		}

		if ( $headers['bcc'] ) {
			$message['bccRecipients'] = self::graph_recipients( $headers['bcc'] );
		}

		if ( $headers['reply_to'] ) {
			$message['replyTo'] = self::graph_recipients( $headers['reply_to'] );
		}

		if ( $attachments ) {
			$message['attachments'] = $attachments;
		}

		return array(
			'message'         => $message,
			'saveToSentItems' => self::save_to_sent(),
		);
	}

	/**
	 * @since 1.9.0
	 * @param mixed $headers Header string or list.
	 * @return array{content_type: string, cc: list<array{name: string, email: string}>, bcc: list<array{name: string, email: string}>, reply_to: list<array{name: string, email: string}>}
	 */
	private static function headers( $headers ): array {
		$parsed = array(
			'content_type' => '',
			'cc'           => array(),
			'bcc'          => array(),
			'reply_to'     => array(),
		);

		foreach ( self::lines( $headers ) as $line ) {
			if ( ! str_contains( $line, ':' ) ) {
				continue;
			}

			list( $name, $value ) = explode( ':', $line, 2 );
			$name                 = strtolower( trim( $name ) );
			$value                = trim( $value );

			if ( 'content-type' === $name ) {
				$parsed['content_type'] = $value;
			} elseif ( 'cc' === $name ) {
				$parsed['cc'] = array_merge( $parsed['cc'], self::addresses( $value ) );
			} elseif ( 'bcc' === $name ) {
				$parsed['bcc'] = array_merge( $parsed['bcc'], self::addresses( $value ) );
			} elseif ( 'reply-to' === $name ) {
				$parsed['reply_to'] = array_merge( $parsed['reply_to'], self::addresses( $value ) );
			}
		}

		return $parsed;
	}

	/**
	 * @since 1.9.0
	 * @param mixed $value Address string or list.
	 * @return list<array{name: string, email: string}>
	 */
	private static function addresses( $value ): array {
		$items  = is_array( $value ) ? $value : preg_split( '/,(?=(?:[^"]*"[^"]*")*[^"]*$)/', (string) $value );
		$people = array();

		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$item = trim( (string) $item );

			if ( '' === $item ) {
				continue;
			}

			$name  = '';
			$email = $item;

			if ( preg_match( '/^(.*)<([^>]+)>$/', $item, $found ) ) {
				$name  = trim( $found[1], " \t\"'" );
				$email = trim( $found[2] );
			}

			$email = sanitize_email( $email );

			if ( ! is_email( $email ) ) {
				continue;
			}

			$people[] = array(
				'name'  => sanitize_text_field( $name ),
				'email' => $email,
			);
		}

		return $people;
	}

	/**
	 * @since 1.9.0
	 * @param list<array{name: string, email: string}> $people Recipients.
	 * @return list<array{emailAddress: array{address: string, name?: string}}>
	 */
	private static function graph_recipients( array $people ): array {
		$rows = array();

		foreach ( $people as $person ) {
			$address = array(
				'address' => $person['email'],
			);

			if ( '' !== $person['name'] ) {
				$address['name'] = $person['name'];
			}

			$rows[] = array(
				'emailAddress' => $address,
			);
		}

		return $rows;
	}

	/**
	 * @since 1.9.0
	 * @param mixed $attachments File path or list of paths.
	 * @return list<array<string, string>>|WP_Error
	 */
	private static function attachments( $attachments ) {
		$files = array();
		$total = 0;

		foreach ( self::lines( $attachments ) as $path ) {
			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				return new WP_Error( 'wp_mail_failed', __( 'Could not read an attachment.', 'li-dev-theme' ) );
			}

			$size = filesize( $path );

			if ( false === $size ) {
				return new WP_Error( 'wp_mail_failed', __( 'Could not read an attachment.', 'li-dev-theme' ) );
			}

			$total += $size;

			if ( $total > self::MAX_ATTACHMENT_BYTES ) {
				return new WP_Error(
					'wp_mail_failed',
					__( 'The attachments are larger than 3 MB, so they were not sent through Microsoft 365.', 'li-dev-theme' )
				);
			}

			$raw = file_get_contents( $path );

			if ( ! is_string( $raw ) ) {
				return new WP_Error( 'wp_mail_failed', __( 'Could not read an attachment.', 'li-dev-theme' ) );
			}

			$name = sanitize_file_name( wp_basename( $path ) );
			$type = wp_check_filetype( $name );
			$mime = is_string( $type['type'] ?? null ) && '' !== $type['type'] ? $type['type'] : 'application/octet-stream';

			$files[] = array(
				'@odata.type'  => '#microsoft.graph.fileAttachment',
				'name'         => '' !== $name ? $name : 'attachment',
				'contentType'  => $mime,
				'contentBytes' => base64_encode( $raw ),
			);
		}

		return $files;
	}

	/**
	 * @since 1.9.0
	 * @param array<string, mixed> $payload Graph sendMail body.
	 * @param bool                 $refresh  Whether a rejected token may be replaced once.
	 * @return true|WP_Error
	 */
	private static function post_mail( array $payload, bool $refresh = true ) {
		$token = self::access_token();

		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$body = wp_json_encode( $payload );

		if ( ! is_string( $body ) ) {
			return new WP_Error( 'wp_mail_failed', __( 'The message could not be prepared for Microsoft 365.', 'li-dev-theme' ) );
		}

		$url      = 'https://graph.microsoft.com/v1.0/users/' . rawurlencode( self::sender() ) . '/sendMail';
		$response = self::remote_post(
			$url,
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => $body,
			)
		);
		$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $status && $refresh ) {
			delete_transient( self::TOKEN_KEY );
			return self::post_mail( $payload, false );
		}

		if ( 202 === $status || 200 === $status ) {
			return true;
		}

		return self::error_from_response( $response, __( 'Microsoft 365 mail failed', 'li-dev-theme' ) );
	}

	/**
	 * @since 1.9.0
	 * @return string|WP_Error
	 */
	private static function access_token() {
		$cached = get_transient( self::TOKEN_KEY );

		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$response = self::remote_post(
			'https://login.microsoftonline.com/' . rawurlencode( trim( (string) LIDEV_GRAPH_TENANT_ID ) ) . '/oauth2/v2.0/token',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type' => 'application/x-www-form-urlencoded',
				),
				'body'    => array(
					'client_id'     => trim( (string) LIDEV_GRAPH_CLIENT_ID ),
					'client_secret' => (string) LIDEV_GRAPH_CLIENT_SECRET,
					'scope'         => 'https://graph.microsoft.com/.default',
					'grant_type'    => 'client_credentials',
				),
			)
		);
		$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $status ) {
			return self::error_from_response( $response, __( 'Microsoft 365 sign-in failed', 'li-dev-theme' ) );
		}

		$data  = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$token = is_array( $data ) ? (string) ( $data['access_token'] ?? '' ) : '';

		if ( '' === $token ) {
			return new WP_Error( 'wp_mail_failed', __( 'Microsoft 365 sign-in failed: no access token was returned.', 'li-dev-theme' ) );
		}

		$expires = is_array( $data ) ? (int) ( $data['expires_in'] ?? 3600 ) : 3600;
		set_transient( self::TOKEN_KEY, $token, max( 60, $expires - 120 ) );

		return $token;
	}

	/**
	 * Post once, and a second time after Retry-After when Microsoft is busy.
	 *
	 * @since 1.9.0
	 * @param string               $url  Endpoint.
	 * @param array<string, mixed> $args Request arguments.
	 * @return array<string, mixed>|WP_Error
	 */
	private static function remote_post( string $url, array $args ) {
		$response = wp_remote_post( $url, $args );

		if ( ! self::should_retry( $response ) ) {
			return $response;
		}

		sleep( self::retry_delay( $response ) );

		return wp_remote_post( $url, $args );
	}

	/**
	 * @since 1.9.0
	 * @param array<string, mixed>|WP_Error $response HTTP response.
	 * @return bool
	 */
	private static function should_retry( $response ): bool {
		if ( is_wp_error( $response ) ) {
			return false;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );

		return 429 === $status || $status >= 500;
	}

	/**
	 * Honor Retry-After, but do not hold a web request for more than 30 seconds.
	 *
	 * @since 1.9.0
	 * @param array<string, mixed>|WP_Error $response HTTP response.
	 * @return int
	 */
	private static function retry_delay( $response ): int {
		if ( is_wp_error( $response ) ) {
			return 2;
		}

		$header = wp_remote_retrieve_header( $response, 'retry-after' );
		$header = is_array( $header ) ? (string) reset( $header ) : (string) $header;
		$header = trim( $header );

		if ( '' === $header ) {
			return 2;
		}

		if ( ctype_digit( $header ) ) {
			return min( 30, max( 1, (int) $header ) );
		}

		$when = strtotime( $header );

		if ( false === $when ) {
			return 2;
		}

		return min( 30, max( 1, $when - time() ) );
	}

	/**
	 * @since 1.9.0
	 * @param array<string, mixed>|WP_Error $response HTTP response.
	 * @param string                         $context  What was being attempted.
	 * @return WP_Error
	 */
	private static function error_from_response( $response, string $context ): WP_Error {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wp_mail_failed', self::scrub( $context . ': ' . $response->get_error_message() ) );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$data   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$code   = '';
		$detail = '';

		if ( is_array( $data ) && isset( $data['error'] ) && is_array( $data['error'] ) ) {
			$code   = (string) ( $data['error']['code'] ?? '' );
			$detail = (string) ( $data['error']['message'] ?? '' );
		} elseif ( is_array( $data ) && isset( $data['error'] ) && is_string( $data['error'] ) ) {
			$code   = $data['error'];
			$detail = (string) ( $data['error_description'] ?? '' );
		}

		$code   = self::scrub( $code );
		$detail = self::scrub( $detail );
		$text   = $context . ' (HTTP ' . $status . ( '' !== $code ? ', ' . $code : '' ) . ')';

		if ( '' !== $detail ) {
			$text .= ': ' . $detail;
		}

		return new WP_Error(
			'wp_mail_failed',
			$text,
			array(
				'status'     => $status,
				'graph_code' => $code,
			)
		);
	}

	/**
	 * @since 1.9.0
	 * @param WP_Error $error Failure that can be shown and logged.
	 * @return void
	 */
	private static function reject( WP_Error $error ): void {
		$data   = $error->get_error_data();
		$status = is_array( $data ) ? (int) ( $data['status'] ?? 0 ) : 0;
		$code   = is_array( $data ) ? self::scrub( (string) ( $data['graph_code'] ?? '' ) ) : '';

		do_action(
			'wp_mail_failed',
			new WP_Error(
				'wp_mail_failed',
				self::scrub( $error->get_error_message() ),
				array(
					'status'     => $status,
					'graph_code' => $code,
				)
			)
		);
	}

	/**
	 * @since 1.9.0
	 * @param string $text Message that may contain a secret or a sign-in token.
	 * @return string
	 */
	private static function scrub( string $text ): string {
		if ( defined( 'LIDEV_GRAPH_CLIENT_SECRET' ) && is_string( LIDEV_GRAPH_CLIENT_SECRET ) && '' !== LIDEV_GRAPH_CLIENT_SECRET ) {
			$text = str_replace( LIDEV_GRAPH_CLIENT_SECRET, '[redacted]', $text );
		}

		$replaced = preg_replace( '/eyJ[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}\.[A-Za-z0-9_\-]{8,}/', '[redacted]', $text );
		$text     = is_string( $replaced ) ? $replaced : $text;
		$replaced = preg_replace( '/((?:access_token|refresh_token|client_secret|Bearer)\s*[:=]?\s*)\S+/i', '$1[redacted]', $text );
		$text     = is_string( $replaced ) ? $replaced : $text;
		$replaced = preg_replace( '/token=[A-Za-z0-9._\-]+/i', 'token=[redacted]', $text );

		return is_string( $replaced ) ? $replaced : $text;
	}

	/**
	 * @since 1.9.0
	 * @return bool
	 */
	private static function save_to_sent(): bool {
		if ( ! defined( 'LIDEV_GRAPH_SAVE_TO_SENT' ) ) {
			return true;
		}

		$value = LIDEV_GRAPH_SAVE_TO_SENT;

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return ! in_array( strtolower( trim( $value ) ), array( '0', 'false', 'no', 'off' ), true );
		}

		return (bool) $value;
	}

	/**
	 * @since 1.9.0
	 * @param mixed $value Multiline string or list.
	 * @return list<string>
	 */
	private static function lines( $value ): array {
		$items = is_array( $value ) ? $value : preg_split( "/\r\n|\n|\r/", (string) $value );
		$lines = array();

		foreach ( is_array( $items ) ? $items : array() as $item ) {
			$item = trim( (string) $item );

			if ( '' !== $item ) {
				$lines[] = $item;
			}
		}

		return $lines;
	}
}

add_filter( 'pre_wp_mail', array( 'Lidev_Graph_Mailer', 'pre_wp_mail' ), PHP_INT_MAX, 2 );
