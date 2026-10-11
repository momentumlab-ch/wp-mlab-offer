<?php
/**
 * Mobile Erfassung unter /angebote-erfassen: eigene, schlanke Seite ohne WordPress-Menü
 * und ohne Theme. Angemeldet wird über die normale WordPress-Anmeldung, damit
 * Login-Schutz (Sperren, Captcha, 2FA) unverändert greift.
 *
 * @package wp-mlab-offer
 */

defined( 'ABSPATH' ) || exit;

final class MLab_Offer_Capture {

	const QUERY_VAR = 'mlab_offer_capture';

	/** Meldungen nach dem Speichern (per Redirect, damit Neuladen nichts doppelt speichert). */
	const MESSAGES = array(
		'saved'   => array( 'ok', 'Gespeichert.' ),
		'trashed' => array( 'ok', 'Angebot gelöscht.' ),
		'image'   => array( 'error', 'Gespeichert, aber das Bild konnte nicht hochgeladen werden. Bitte ein kleineres Foto versuchen.' ),
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'rewrite' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'route' ) );
	}

	/** Pfad der Erfassungsseite, anpassbar per Filter «mlab_offer_capture_slug». */
	private static function slug() {
		return trim( (string) apply_filters( 'mlab_offer_capture_slug', 'angebote-erfassen' ), '/' );
	}

	public static function url( array $args = array() ) {
		return add_query_arg( $args, home_url( '/' . self::slug() . '/' ) );
	}

	public static function rewrite() {
		$slug = self::slug();
		add_rewrite_rule( '^' . preg_quote( $slug, '/' ) . '/?$', 'index.php?' . self::QUERY_VAR . '=1', 'top' );
		// Regeln nur neu schreiben, wenn sich der Pfad geändert hat (auch nach Plugin-Updates).
		if ( get_option( 'mlab_offer_capture_slug' ) !== $slug ) {
			update_option( 'mlab_offer_capture_slug', $slug );
			flush_rewrite_rules( false );
		}
	}

	public static function activate() {
		delete_option( 'mlab_offer_capture_slug' );
	}

	public static function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/* ------------------------------------------------------------------ */
	/* Ablauf                                                              */
	/* ------------------------------------------------------------------ */

	public static function route() {
		if ( ! get_query_var( self::QUERY_VAR ) ) {
			return;
		}
		nocache_headers();
		send_frame_options_header();
		header( 'X-Robots-Tag: noindex, nofollow' );

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( self::url() ) );
			exit;
		}
		$action = isset( $_POST['mlab_action'] ) ? sanitize_key( $_POST['mlab_action'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- Nonce wird je Aktion geprüft.
		if ( ! MLab_Offer::can( 'edit_posts' ) ) {
			self::page( 'Kein Zugriff', array( __CLASS__, 'view_denied' ) );
		} elseif ( 'save' === $action ) {
			self::save();
		} elseif ( 'trash' === $action ) {
			self::trash();
		} elseif ( isset( $_GET['angebot'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			self::page( 'Angebot', array( __CLASS__, 'view_form' ) );
		} else {
			self::page( 'Angebote', array( __CLASS__, 'view_list' ) );
		}
		exit;
	}

	private static function save() {
		check_admin_referer( MLab_Offer::NONCE, MLab_Offer::NONCE_FIELD );
		$id = self::posted_id();
		if ( $id ? ! self::can_edit( $id ) : ! MLab_Offer::can( 'create_posts' ) ) {
			wp_die( 'Keine Berechtigung.', 403 );
		}
		// phpcs:disable WordPress.Security.NonceVerification -- oben geprüft.
		$title  = isset( $_POST['post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['post_title'] ) ) : '';
		$status = 'pending'; // Ohne Recht zum Veröffentlichen gibt eine verantwortliche Person frei.
		if ( MLab_Offer::can( 'publish_posts' ) ) {
			$status = empty( $_POST['draft'] ) ? 'publish' : 'draft';
		}
		// phpcs:enable
		// Die Felder speichert MLab_Offer::save() über den Hook save_post.
		$data = array(
			'ID'          => $id,
			'post_type'   => MLab_Offer::CPT,
			'post_title'  => $title,
			'post_status' => $status,
		);
		$id   = $id ? wp_update_post( $data, true ) : wp_insert_post( $data, true );
		if ( is_wp_error( $id ) ) {
			wp_die( esc_html( $id->get_error_message() ) );
		}
		self::redirect( self::save_image( $id ) ? 'saved' : 'image' );
	}

	/** Neues Foto hochladen oder Bild entfernen. Gibt false zurück, wenn der Upload scheitert. */
	private static function save_image( $id ) {
		// phpcs:disable WordPress.Security.NonceVerification -- in save() geprüft.
		if ( ! empty( $_POST['remove_image'] ) ) {
			delete_post_thumbnail( $id );
		}
		if ( empty( $_FILES['image']['name'] ) || ! current_user_can( 'upload_files' ) ) {
			return true;
		}
		// phpcs:enable
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		$attachment = media_handle_upload( 'image', $id );
		if ( is_wp_error( $attachment ) ) {
			return false;
		}
		set_post_thumbnail( $id, $attachment );
		return true;
	}

	private static function trash() {
		$id = self::posted_id();
		check_admin_referer( 'mlab_offer_trash_' . $id );
		if ( MLab_Offer::CPT !== get_post_type( $id ) || ! current_user_can( 'delete_post', $id ) ) {
			wp_die( 'Keine Berechtigung.', 403 );
		}
		wp_trash_post( $id );
		self::redirect( 'trashed' );
	}

	private static function can_edit( $id ) {
		return MLab_Offer::CPT === get_post_type( $id ) && current_user_can( 'edit_post', $id );
	}

	private static function posted_id() {
		return isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	}

	private static function redirect( $msg = '' ) {
		wp_safe_redirect( self::url( $msg ? array( 'msg' => $msg ) : array() ) );
		exit;
	}

	/* ------------------------------------------------------------------ */
	/* Ansichten                                                           */
	/* ------------------------------------------------------------------ */

	/** Eigenständiges HTML-Gerüst ohne Theme und ohne Admin-Leiste. */
	private static function page( $title, callable $view ) {
		$icon = MLAB_OFFER_URL . 'assets/icon-256x256.png';
		MLab_Offer::enqueue_form_assets();
		wp_enqueue_style( 'mlab-offer-capture', MLAB_OFFER_URL . 'assets/capture.css', array( 'mlab-offer-form' ), MLAB_OFFER_VERSION );
		?>
<!doctype html>
<html lang="de-CH">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#0E2430">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Angebote">
<title><?php echo esc_html( $title . ' · ' . get_bloginfo( 'name' ) ); ?></title>
<link rel="icon" href="<?php echo esc_url( $icon ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( $icon ); ?>">
		<?php wp_print_styles( 'mlab-offer-capture' ); ?>
</head>
<body>
<header class="cap-bar">
	<a class="cap-home" href="<?php echo esc_url( self::url() ); ?>"><img src="<?php echo esc_url( $icon ); ?>" alt="" width="32" height="32"> Angebote</a>
	<a class="cap-logout" href="<?php echo esc_url( wp_logout_url( self::url() ) ); ?>">Abmelden</a>
</header>
<main class="cap">
		<?php self::notice(); ?>
		<?php call_user_func( $view ); ?>
</main>
		<?php wp_print_scripts( 'mlab-offer-form' ); ?>
</body>
</html>
		<?php
	}

	private static function notice() {
		$key = isset( $_GET['msg'] ) ? sanitize_key( $_GET['msg'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		list( $type, $text ) = self::MESSAGES[ $key ] ?? array( '', '' );
		if ( $text ) {
			printf( '<p class="cap-msg cap-%s" role="status">%s</p>', esc_attr( $type ), esc_html( $text ) );
		}
	}

	private static function view_denied() {
		echo '<div class="cap-card"><h1>Kein Zugriff</h1><p>Dieses Benutzerkonto darf keine Angebote erfassen. Bitte melde dich mit einem anderen Konto an.</p></div>';
	}

	private static function view_list() {
		$args = array(
			'post_type'   => MLab_Offer::CPT,
			'post_status' => array( 'publish', 'draft', 'pending', 'future' ),
			'numberposts' => 100,
			'orderby'     => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		);
		if ( ! MLab_Offer::can( 'edit_others_posts' ) ) {
			$args['author'] = get_current_user_id();
		}
		$offers = get_posts( $args );
		MLab_Offer::prime_thumbnails( $offers );
		?>
		<a class="cap-btn cap-new" href="<?php echo esc_url( self::url( array( 'angebot' => 'neu' ) ) ); ?>">+ Neues Angebot</a>
		<?php if ( ! $offers ) : ?>
			<p class="cap-empty">Noch keine Angebote. Tippe auf «Neues Angebot».</p>
		<?php endif; ?>
		<ul class="cap-list">
			<?php foreach ( $offers as $p ) : ?>
				<li><a href="<?php echo esc_url( self::url( array( 'angebot' => $p->ID ) ) ); ?>">
					<span class="cap-thumb"><?php echo get_the_post_thumbnail( $p->ID, 'thumbnail', array( 'alt' => '' ) ); ?></span>
					<span class="cap-meta"><strong><?php echo esc_html( get_the_title( $p ) ?: '(ohne Titel)' ); ?></strong><small><?php echo esc_html( self::status_label( $p ) ); ?></small></span>
				</a></li>
			<?php endforeach; ?>
		</ul>
		<?php
	}

	private static function status_label( $p ) {
		if ( 'publish' !== $p->post_status ) {
			return 'pending' === $p->post_status ? 'Wartet auf Freigabe' : 'Entwurf';
		}
		if ( ! MLab_Offer::is_visible( $p->ID ) ) {
			return 'Abgelaufen, nicht mehr sichtbar';
		}
		$next = MLab_Offer::next_date( $p->ID );
		return $next ? 'Sichtbar · nächster Termin ' . MLab_Offer::format_date( $next ) : 'Sichtbar';
	}

	private static function view_form() {
		$id   = absint( $_GET['angebot'] ); // phpcs:ignore WordPress.Security.NonceVerification
		$post = $id ? get_post( $id ) : null;
		if ( $id && ! self::can_edit( $id ) ) {
			echo '<div class="cap-card"><p>Dieses Angebot gibt es nicht (mehr).</p></div>';
			return;
		}
		?>
		<h1><?php echo $post ? 'Angebot bearbeiten' : 'Neues Angebot'; ?></h1>
		<form class="cap-card" method="post" enctype="multipart/form-data" action="<?php echo esc_url( self::url() ); ?>">
			<input type="hidden" name="mlab_action" value="save">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<label class="mlab-f"><span>Titel <em>z. B. Tiefschnee-Technikkurs Ski</em></span><input type="text" name="post_title" value="<?php echo esc_attr( $post ? $post->post_title : '' ); ?>" required></label>
			<?php self::image_field( $id ); ?>
			<?php MLab_Offer::render_fields( $id ); ?>
			<?php if ( MLab_Offer::can( 'publish_posts' ) ) : ?>
				<label class="cap-check"><input type="checkbox" name="draft" value="1"<?php checked( $post && 'publish' !== $post->post_status ); ?>> Noch nicht veröffentlichen (Entwurf)</label>
			<?php else : ?>
				<p class="cap-hint">Das Angebot wird nach dem Speichern von einer verantwortlichen Person freigegeben.</p>
			<?php endif; ?>
			<button class="cap-btn" type="submit">Speichern</button>
		</form>
		<?php
		if ( $post && current_user_can( 'delete_post', $id ) ) {
			self::trash_form( $id );
		}
	}

	private static function image_field( $id ) {
		$thumb = $id ? get_the_post_thumbnail( $id, 'medium', array( 'alt' => '' ) ) : '';
		?>
		<div class="mlab-f"><span>Bild <em>Foto machen oder aus der Galerie wählen</em></span>
			<?php if ( $thumb ) : ?>
				<div class="cap-preview"><?php echo $thumb; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
				<label class="cap-check"><input type="checkbox" name="remove_image" value="1"> Bild entfernen</label>
			<?php endif; ?>
			<?php if ( current_user_can( 'upload_files' ) ) : ?>
				<input type="file" name="image" accept="image/*">
			<?php endif; ?>
		</div>
		<?php
	}

	private static function trash_form( $id ) {
		?>
		<form method="post" action="<?php echo esc_url( self::url() ); ?>" onsubmit="return confirm('Dieses Angebot wirklich löschen?');">
			<?php wp_nonce_field( 'mlab_offer_trash_' . $id ); ?>
			<input type="hidden" name="mlab_action" value="trash">
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>">
			<button class="cap-btn cap-danger" type="submit">Angebot löschen</button>
		</form>
		<?php
	}
}
