<?php
/**
 * Private PDF copies of sign-off records.
 *
 * The PDF is rendered from the stored snapshot and signature only.
 * A later rename of the project does not change a file that already exists,
 * and a missing file is rebuilt from that same record.
 *
 * @package LIDevTheme
 * @since   1.5.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Build, store, and stream approval PDFs.
 *
 * @since 1.5.0
 */
final class Lidev_Pdf {

	/**
	 * Write the PDF after a phase is signed. A failure here does not undo the signature.
	 *
	 * @since 1.5.0
	 * @param int $signoff_id Sign-off row ID.
	 * @return void
	 */
	public static function on_signed( int $signoff_id ): void {
		self::ensure( $signoff_id );
	}

	/**
	 * Stream a PDF for a Team member, or for a client assigned to that project.
	 *
	 * @since 1.5.0
	 * @return void
	 */
	public static function download(): void {
		$signoff_id = isset( $_GET['signoff_id'] ) ? absint( $_GET['signoff_id'] ) : 0;

		if ( ! is_user_logged_in() || $signoff_id < 1 ) {
			self::deny();
		}

		check_admin_referer( 'lidev_pdf_' . $signoff_id );

		$row = Lidev_Signoff_Repository::get( $signoff_id );

		if ( ! is_array( $row ) || ! self::user_can_download( $row ) ) {
			self::deny();
		}

		$path = self::ensure( $signoff_id );
		$pdf  = is_string( $path ) ? self::payload( $path ) : '';

		if ( is_wp_error( $path ) || '' === $pdf ) {
			wp_die(
				esc_html__( 'The approval record could not be prepared.', 'li-dev-theme' ),
				esc_html__( 'Approval record', 'li-dev-theme' ),
				array( 'response' => 500 )
			);
		}

		$phase_id = (int) ( $row['phase_id'] ?? 0 );

		lidev_log(
			'pdf_downloaded',
			(int) ( $row['project_id'] ?? 0 ),
			$phase_id > 0 ? $phase_id : null,
			array( 'signoff_id' => $signoff_id )
		);

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Content-Disposition: attachment; filename="approval-record-' . $signoff_id . '.pdf"' );
		header( 'Content-Length: ' . (string) strlen( $pdf ) );
		echo $pdf;
		exit;
	}

	/**
	 * Download address for one sign-off. The nonce is tied to the current user.
	 *
	 * @since 1.5.0
	 * @param int $signoff_id Sign-off row ID.
	 * @return string
	 */
	public static function url( int $signoff_id ): string {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'lidev_download_pdf',
					'signoff_id' => $signoff_id,
				),
				admin_url( 'admin-post.php' )
			),
			'lidev_pdf_' . $signoff_id
		);
	}

	/**
	 * Path of the stored PDF, creating it from the sign-off row when it is missing.
	 *
	 * @since 1.5.0
	 * @param int $signoff_id Sign-off row ID.
	 * @return string|WP_Error Absolute path.
	 */
	public static function ensure( int $signoff_id ) {
		$row = Lidev_Signoff_Repository::get( $signoff_id );

		if ( ! is_array( $row ) ) {
			return new WP_Error( 'lidev_pdf_missing', __( 'That sign-off does not exist.', 'li-dev-theme' ) );
		}

		$dir = self::directory();

		if ( is_wp_error( $dir ) ) {
			return $dir;
		}

		$path = $dir . '/' . self::filename( $signoff_id );

		if ( '' !== self::payload( $path ) ) {
			return $path;
		}

		$binary = self::render( $row );

		if ( is_wp_error( $binary ) ) {
			return $binary;
		}

		$temp  = $path . '.tmp';
		$wrote = file_put_contents( $temp, self::guard() . $binary );

		if ( false === $wrote || $wrote < 1 ) {
			wp_delete_file( $temp );
			return new WP_Error( 'lidev_pdf_write', __( 'The approval record could not be stored.', 'li-dev-theme' ) );
		}

		if ( ! rename( $temp, $path ) ) {
			wp_delete_file( $temp );
			return new WP_Error( 'lidev_pdf_write', __( 'The approval record could not be stored.', 'li-dev-theme' ) );
		}

		$phase_id = (int) ( $row['phase_id'] ?? 0 );

		lidev_log(
			'pdf_generated',
			(int) ( $row['project_id'] ?? 0 ),
			$phase_id > 0 ? $phase_id : null,
			array( 'signoff_id' => $signoff_id )
		);

		return $path;
	}

	/**
	 * A normal PDF file for an email attachment. The caller deletes it.
	 *
	 * @since 1.6.0
	 * @param int $signoff_id Sign-off row ID.
	 * @return string|WP_Error Absolute path.
	 */
	public static function mail_copy( int $signoff_id ) {
		$path = self::ensure( $signoff_id );

		if ( is_wp_error( $path ) ) {
			return $path;
		}

		$pdf = self::payload( $path );

		if ( '' === $pdf ) {
			return new WP_Error( 'lidev_pdf_missing', __( 'The approval record could not be prepared.', 'li-dev-theme' ) );
		}

		$temp = wp_tempnam( 'lidev-approval' );

		if ( ! is_string( $temp ) || '' === $temp ) {
			return new WP_Error( 'lidev_pdf_write', __( 'The approval record could not be prepared.', 'li-dev-theme' ) );
		}

		$dest = $temp . '.pdf';

		if ( false === file_put_contents( $dest, $pdf ) ) {
			wp_delete_file( $temp );
			return new WP_Error( 'lidev_pdf_write', __( 'The approval record could not be prepared.', 'li-dev-theme' ) );
		}

		wp_delete_file( $temp );

		return $dest;
	}

	/**
	 * Team may download any record. A client may download records for assigned projects.
	 *
	 * @since 1.5.0
	 * @param array<string, mixed> $row Sign-off row.
	 * @return bool
	 */
	private static function user_can_download( array $row ): bool {
		if ( current_user_can( 'lidev_view_all_projects' ) ) {
			return true;
		}

		if ( ! current_user_can( 'lidev_view_own_projects' ) ) {
			return false;
		}

		return in_array( get_current_user_id(), lidev_project_client_ids( (int) ( $row['project_id'] ?? 0 ) ), true );
	}

	/**
	 * @since 1.5.0
	 * @return void
	 */
	private static function deny(): void {
		wp_die(
			esc_html__( 'You cannot download this record.', 'li-dev-theme' ),
			esc_html__( 'Forbidden', 'li-dev-theme' ),
			array( 'response' => 403 )
		);
	}

	/**
	 * Uploads folder. The file is a PHP wrapper, because this nginx host serves a
	 * bare PDF and blocks PHP in uploads. Downloads go through this class.
	 *
	 * @since 1.5.0
	 * @return string|WP_Error
	 */
	private static function directory() {
		$uploads = wp_upload_dir( null, false );

		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'lidev_pdf_dir', (string) $uploads['error'] );
		}

		$dir = wp_normalize_path( trailingslashit( (string) $uploads['basedir'] ) . 'lidev-signoffs' );

		if ( ! wp_mkdir_p( $dir ) || ! wp_mkdir_p( $dir . '/fonts' ) ) {
			return new WP_Error( 'lidev_pdf_dir', __( 'The approval record folder could not be created.', 'li-dev-theme' ) );
		}

		$htaccess = $dir . '/.htaccess';

		if ( ! is_file( $htaccess ) ) {
			file_put_contents(
				$htaccess,
				"<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tDeny from all\n</IfModule>\nOptions -Indexes\n"
			);
		}

		$index = $dir . '/index.php';

		if ( ! is_file( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		return $dir;
	}

	/**
	 * Scratch space for Dompdf and drawn signatures. It must sit outside the web root.
	 *
	 * The stored record stays in uploads, wrapped so nginx will not serve the PDF.
	 * The directory above the web root is not writable by PHP on this host.
	 *
	 * @since 1.7.0
	 * @return string|WP_Error
	 */
	private static function work_dir() {
		$dir = wp_normalize_path( trailingslashit( get_temp_dir() ) . 'lidev-signoffs' );

		if ( ! wp_mkdir_p( $dir ) || ! wp_mkdir_p( $dir . '/fonts' ) || ! is_writable( $dir ) ) {
			return new WP_Error( 'lidev_pdf_dir', __( 'The approval record could not be prepared.', 'li-dev-theme' ) );
		}

		$real = realpath( $dir );
		$root = realpath( ABSPATH );
		$dir  = wp_normalize_path( is_string( $real ) ? $real : $dir );
		$web  = is_string( $root ) ? wp_normalize_path( $root ) : '';

		if ( '' !== $web && ( $dir === $web || str_starts_with( $dir, $web . '/' ) ) ) {
			return new WP_Error( 'lidev_pdf_dir', __( 'The approval record could not be prepared.', 'li-dev-theme' ) );
		}

		return $dir;
	}

	/**
	 * Stable unguessable name. It is not stored on the sign-off row, so the hash stays unchanged.
	 *
	 * @since 1.5.0
	 * @param int $signoff_id Sign-off row ID.
	 * @return string
	 */
	private static function filename( int $signoff_id ): string {
		return hash_hmac( 'sha256', (string) $signoff_id, wp_salt( 'auth' ) ) . '.php';
	}

	/**
	 * Prefix that stops the file from printing a PDF when PHP runs it.
	 *
	 * @since 1.5.0
	 * @return string
	 */
	private static function guard(): string {
		return "<?php\nhttp_response_code( 403 );\nexit;\n?>\n";
	}

	/**
	 * PDF bytes stored after the guard. Empty when the file is missing or wrong.
	 *
	 * @since 1.5.0
	 * @param string $path Stored file.
	 * @return string
	 */
	private static function payload( string $path ): string {
		if ( ! is_readable( $path ) ) {
			return '';
		}

		$raw   = file_get_contents( $path );
		$guard = self::guard();

		if ( ! is_string( $raw ) || ! str_starts_with( $raw, $guard ) ) {
			return '';
		}

		$pdf = substr( $raw, strlen( $guard ) );

		return str_starts_with( $pdf, '%PDF' ) ? $pdf : '';
	}

	/**
	 * @since 1.5.0
	 * @param array<string, mixed> $row Sign-off row.
	 * @return string|WP_Error PDF bytes.
	 */
	private static function render( array $row ) {
		if ( PHP_VERSION_ID < 80100 ) {
			return new WP_Error( 'lidev_pdf_php', __( 'PDF records require PHP 8.1 or newer.', 'li-dev-theme' ) );
		}

		$autoload = LIDEV_PATH . 'vendor/autoload.php';

		if ( ! is_readable( $autoload ) ) {
			return new WP_Error( 'lidev_pdf_library', __( 'The PDF library is not installed.', 'li-dev-theme' ) );
		}

		require_once $autoload;

		$work = self::work_dir();

		if ( is_wp_error( $work ) ) {
			return $work;
		}

		$signature_file = self::signature_file( $row, $work );

		try {
			$options = new Dompdf\Options();
			$options->setIsRemoteEnabled( false );
			$options->setIsPhpEnabled( false );
			$options->setIsJavascriptEnabled( false );
			$options->setAllowedRemoteHosts( array() );
			$options->setAllowedProtocols( array( 'file://', 'data://' ) );
			$options->setIsHtml5ParserEnabled( true );
			$options->setDefaultFont( 'DejaVu Sans' );
			$options->setChroot( array( wp_normalize_path( LIDEV_PATH ), $work ) );
			$options->setTempDir( $work );
			$options->setFontCache( $work . '/fonts' );

			$dompdf = new Dompdf\Dompdf( $options );
			$dompdf->loadHtml( self::html( $row, is_string( $signature_file ) ? $signature_file : '' ), 'UTF-8' );
			$dompdf->setPaper( 'letter', 'portrait' );
			$dompdf->render();

			$canvas = $dompdf->getCanvas();

			if ( method_exists( $canvas, 'get_cpdf' ) ) {
				$pdf  = $canvas->get_cpdf();
				$when = self::pdf_date( (string) ( $row['signed_at'] ?? '' ) );
				$pdf->addInfo( 'CreationDate', $when );
				$pdf->addInfo( 'ModDate', $when );
				$pdf->fileIdentifier = hash( 'sha256', (string) ( $row['record_hash'] ?? '' ) . '|' . (string) ( $row['id'] ?? '' ) );
			}

			return $dompdf->output();
		} catch ( Throwable $error ) {
			unset( $error );
			return new WP_Error( 'lidev_pdf_render', __( 'The approval record could not be prepared.', 'li-dev-theme' ) );
		} finally {
			if ( is_string( $signature_file ) && is_file( $signature_file ) ) {
				wp_delete_file( $signature_file );
			}
		}
	}

	/**
	 * @since 1.5.0
	 * @param array<string, mixed> $row Sign-off row.
	 * @param string               $dir Private directory.
	 * @return string|null PNG path for a drawn signature.
	 */
	private static function signature_file( array $row, string $dir ): ?string {
		if ( 'drawn' !== (string) ( $row['signature_type'] ?? '' ) ) {
			return null;
		}

		$data = Lidev_Signoff_Repository::accept_signature( 'drawn', (string) ( $row['signature_data'] ?? '' ) );
		$prefix = 'data:image/png;base64,';

		if ( '' === $data || ! str_starts_with( $data, $prefix ) ) {
			return null;
		}

		$binary = base64_decode( substr( $data, strlen( $prefix ) ), true );

		if ( ! is_string( $binary ) || '' === $binary ) {
			return null;
		}

		$path = $dir . '/sig-' . bin2hex( random_bytes( 8 ) ) . '.png';

		if ( ! is_file( $path ) ) {
			file_put_contents( $path, $binary );
		}

		return is_file( $path ) ? $path : null;
	}

	/**
	 * @since 1.5.0
	 * @param array<string, mixed> $row Sign-off row.
	 * @param string               $signature_file Drawn PNG, or empty for a typed signature.
	 * @return string
	 */
	private static function html( array $row, string $signature_file ): string {
		$snap     = lidev_signoff_snapshot( $row );
		$logo     = wp_normalize_path( LIDEV_PATH . 'assets/images/LI_MainLogo_DarkJungle.png' );
		$font     = wp_normalize_path( LIDEV_PATH . 'assets/fonts/GreatVibes-Regular.ttf' );
		$name     = '' !== $snap['signer_name'] ? $snap['signer_name'] : (string) ( $row['signer_name'] ?? '' );
		$typed    = 'typed' === (string) ( $row['signature_type'] ?? '' );
		$signed   = self::signed_label( (string) ( $row['signed_at'] ?? '' ) );
		$hash     = strtolower( (string) ( $row['record_hash'] ?? '' ) );
		$id       = (int) ( $row['id'] ?? 0 );
		$rows     = array(
			__( 'Project', 'li-dev-theme' )   => $snap['project_name'],
			__( 'Domain', 'li-dev-theme' )    => $snap['domain'],
			__( 'Phase', 'li-dev-theme' )     => $snap['phase_label'],
			__( 'Reviewed', 'li-dev-theme' )  => $snap['external_link'],
			__( 'Signed by', 'li-dev-theme' ) => $name,
			__( 'Email', 'li-dev-theme' )     => $snap['signer_email'],
			__( 'Signed', 'li-dev-theme' )    => $signed,
			__( 'Client IP address', 'li-dev-theme' ) => (string) ( $row['ip_address'] ?? '' ),
		);

		$facts = '';

		foreach ( $rows as $label => $value ) {
			if ( '' === $value ) {
				continue;
			}

			$facts .= '<tr><th>' . esc_html( $label ) . '</th><td>' . esc_html( $value ) . '</td></tr>';
		}

		$logo_html = is_readable( $logo ) ? '<img class="logo" src="' . esc_attr( $logo ) . '" alt="Local Image">' : '';
		$font_css  = is_readable( $font ) ? '@font-face{font-family:"Lidev Signature";src:url("' . esc_attr( $font ) . '") format("truetype");}' : '';

		if ( $typed ) {
			$mark = '<p class="script">' . esc_html( $name ) . '</p>';
		} elseif ( '' !== $signature_file ) {
			$mark = '<img class="drawn" src="' . esc_attr( $signature_file ) . '" alt="' . esc_attr( $name ) . '">';
		} else {
			$mark = '';
		}

		$plain = trim( (string) preg_replace( '/\s+/u', ' ', lidev_email_plain( (string) $snap['team_message'] ) ) );
		$fine  = '';

		if ( '' !== $plain ) {
			$fine = '<p class="fine">' . esc_html__( 'Message from the team', 'li-dev-theme' ) . ' ' . esc_html( $plain ) . '</p>';
		}

		$statement = '' !== $snap['statement']
			? '<h2>' . esc_html__( 'Approval', 'li-dev-theme' ) . '</h2><p class="statement">' . esc_html( $snap['statement'] ) . '</p>'
			: '';

		return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>
			' . $font_css . '
			@page { margin: 46px 48px 62px; }
			body { font-family: "DejaVu Sans", sans-serif; color: #1e1e21; font-size: 12px; line-height: 1.45; }
			.bar { height: 6px; background: #bfd100; margin: 0 0 18px; }
			.logo { height: 34px; }
			h1 { font-size: 22px; margin: 14px 0 16px; }
			h2 { font-size: 13px; margin: 18px 0 6px; }
			table { width: 100%; border-collapse: collapse; }
			th, td { text-align: left; vertical-align: top; padding: 5px 8px 5px 0; border-bottom: 1px solid #d8d8d8; }
			th { width: 110px; color: #545454; font-weight: normal; }
			.statement { font-size: 13px; }
			.approval { margin-top: 18px; }
			.approval h2 { margin: 0 0 2px; }
			.mark { display: block; padding-top: 16px; }
			.script { display: block; font-family: "Lidev Signature", "DejaVu Sans", serif; font-size: 32px; line-height: 48px; margin: 0; color: #1e1e21; }
			.drawn { display: block; height: 64px; width: auto; margin: 0; }
			.fine { margin: 22px 0 0; color: #545454; font-size: 9px; line-height: 1.35; }
			.hash { font-family: "DejaVu Sans Mono", monospace; font-size: 9px; word-wrap: break-word; }
			footer { position: fixed; left: 0; right: 0; bottom: -36px; color: #545454; font-size: 9px; }
		</style></head><body>
			<div class="bar"></div>
			' . $logo_html . '
			<h1>' . esc_html__( 'Approval Record', 'li-dev-theme' ) . '</h1>
			<table>' . $facts . '</table>
			' . $statement . '
			<div class="approval"><h2>' . esc_html__( 'Approval Signature', 'li-dev-theme' ) . '</h2><div class="mark">' . $mark . '</div></div>
			<p class="hash">' . esc_html__( 'Record hash', 'li-dev-theme' ) . '<br>' . esc_html( $hash ) . '</p>
			' . $fine . '
			<footer>' . esc_html(
				sprintf(
					/* translators: %d: sign-off record ID. */
					__( 'Sign-off %d. This record is stored and can be verified.', 'li-dev-theme' ),
					$id
				)
			) . '</footer>
		</body></html>';
	}

	/**
	 * Clock time as stored, with the site timezone name.
	 *
	 * @since 1.5.0
	 * @param string $mysql MySQL datetime from the sign-off row.
	 * @return string
	 */
	private static function signed_label( string $mysql ): string {
		$zone = wp_timezone();
		$when = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql, $zone );

		if ( ! $when instanceof DateTimeImmutable ) {
			return $mysql;
		}

		return $when->format( 'F j, Y g:i a' ) . ' ' . wp_timezone_string();
	}

	/**
	 * PDF date stamp taken from the stored signing time, not from now.
	 *
	 * @since 1.5.0
	 * @param string $mysql MySQL datetime.
	 * @return string
	 */
	private static function pdf_date( string $mysql ): string {
		$zone = wp_timezone();
		$when = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $mysql, $zone );

		if ( ! $when instanceof DateTimeImmutable ) {
			$when = new DateTimeImmutable( '1970-01-01 00:00:00', $zone );
		}

		$stamp = 'D:' . $when->format( 'YmdHisO' );

		return substr_replace( $stamp, "'", -2, 0 ) . "'";
	}
}

/**
 * Snapshot fields copied onto the sign-off. Missing keys are empty strings.
 *
 * @since 1.5.0
 * @param array<string, mixed> $row Sign-off row.
 * @return array<string, string>
 */
function lidev_signoff_snapshot( array $row ): array {
	$decoded = json_decode( (string) ( $row['snapshot'] ?? '' ), true );
	$values  = array();

	foreach ( Lidev_Signoff::SNAPSHOT_KEYS as $key ) {
		$values[ $key ] = is_array( $decoded ) && isset( $decoded[ $key ] ) && is_string( $decoded[ $key ] ) ? $decoded[ $key ] : '';
	}

	return $values;
}

/**
 * @since 1.5.0
 * @param int $signoff_id Sign-off row ID.
 * @return string
 */
function lidev_pdf_url( int $signoff_id ): string {
	return Lidev_Pdf::url( $signoff_id );
}
