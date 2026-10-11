<?php
/**
 * Datenmodell, Erfassungsfelder und Sichtbarkeitslogik der Angebote.
 *
 * @package wp-mlab-offer
 */

defined( 'ABSPATH' ) || exit;

final class MLab_Offer {

	// Post-Type und Meta-Präfix bleiben wie im Vorgänger «Alpine Flow Angebote», damit bestehende Angebote erhalten bleiben.
	const CPT         = 'af_angebot';
	const META        = '_afa_';
	const NONCE       = 'mlab_offer_save';
	const NONCE_FIELD = '_mlab_offer_nonce';

	/** Bildausschnitt: Schlüssel => [Label, CSS object-position]. */
	const FOCUS = array(
		''       => array( 'Mitte', '50% 50%' ),
		'top'    => array( 'Oben', '50% 15%' ),
		'bottom' => array( 'Unten', '50% 95%' ),
		'left'   => array( 'Links', '25% 50%' ),
		'right'  => array( 'Rechts', '75% 50%' ),
	);

	/** Erfassungsfelder: Schlüssel => [Label, Hinweis, Typ, halbe Breite]. Die Termine folgen nach «text». */
	const FIELDS = array(
		'badge'      => array( 'Badge', 'kleines Etikett, z. B. «Neu» oder «Frauenkurs»', 'text', false ),
		'text'       => array( 'Kurzbeschreibung', '2–3 Sätze', 'textarea', false ),
		'tags'       => array( 'Stichworte', 'mit Komma trennen, z. B. «5 Std., Mai bis Oktober»', 'text', false ),
		'details'    => array( 'Details', 'eine Zeile pro Angabe, Format «Bezeichnung: Wert», z. B. «Treffpunkt: 08:20 Uhr, Bahnhof»', 'textarea', false ),
		'price'      => array( 'Preis', 'z. B. «ab CHF 450.–» oder leer', 'text', true ),
		'price_note' => array( 'Preis-Zusatz', 'z. B. «pro Person»', 'text', true ),
		'note'       => array( 'Hinweis', 'klein unter dem Button, z. B. Anmeldeschluss', 'text', false ),
		'btn_text'   => array( 'Button-Text', 'leer = «Anfragen»', 'text', true ),
		'btn_url'    => array( 'Button-Link', 'leer = Anfrageformular', 'url', true ),
		'focus'      => array( 'Bildausschnitt', 'welcher Teil des Fotos sichtbar bleibt, wenn es zugeschnitten wird', 'focus', false ),
		'until'      => array( 'Anzeigen bis', 'optional, für Angebote ohne Termin', 'date', false ),
	);

	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		// Speichert die Felder für den Admin-Editor und die mobile Erfassung (beide senden dasselbe Formular).
		add_action( 'save_post_' . self::CPT, array( __CLASS__, 'save' ) );
	}

	public static function register() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'                  => 'Angebote',
					'singular_name'         => 'Angebot',
					'menu_name'             => 'Angebote',
					'add_new'               => 'Neues Angebot',
					'add_new_item'          => 'Neues Angebot',
					'edit_item'             => 'Angebot bearbeiten',
					'all_items'             => 'Alle Angebote',
					'search_items'          => 'Angebote suchen',
					'not_found'             => 'Noch keine Angebote.',
					'not_found_in_trash'    => 'Keine Angebote im Papierkorb.',
					'featured_image'        => 'Bild',
					'set_featured_image'    => 'Bild auswählen oder Foto machen',
					'remove_featured_image' => 'Bild entfernen',
				),
				// Nur im Admin sichtbar: keine eigenen öffentlichen URLs, nichts für Google.
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'show_in_rest'    => false,
				'menu_position'   => 3,
				'menu_icon'       => is_admin() ? 'data:image/svg+xml;base64,' . base64_encode( (string) file_get_contents( MLAB_OFFER_DIR . 'assets/menu-icon.svg' ) ) : '', // phpcs:ignore WordPress.WP.AlternativeFunctions
				'supports'        => array( 'title', 'thumbnail', 'page-attributes' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/** Darf der aktuelle Benutzer das bei Angeboten, z. B. can( 'publish_posts' )? */
	public static function can( $cap ) {
		return current_user_can( get_post_type_object( self::CPT )->cap->$cap );
	}

	/* ------------------------------------------------------------------ */
	/* Daten lesen                                                         */
	/* ------------------------------------------------------------------ */

	public static function get( $id, $key ) {
		return (string) get_post_meta( $id, self::META . $key, true );
	}

	/** Termine als sortierte Liste von Y-m-d. */
	public static function dates( $id ) {
		$dates = get_post_meta( $id, self::META . 'dates', true );
		return self::clean_dates( is_array( $dates ) ? $dates : array() );
	}

	private static function clean_dates( array $dates ) {
		$dates = array_unique( array_filter( $dates, array( __CLASS__, 'is_date' ) ) );
		sort( $dates );
		return $dates;
	}

	private static function is_date( $d ) {
		return is_string( $d ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d );
	}

	public static function upcoming( $id ) {
		$today = current_time( 'Y-m-d' );
		return array_values(
			array_filter(
				self::dates( $id ),
				function ( $d ) use ( $today ) {
					return $d >= $today;
				}
			)
		);
	}

	public static function next_date( $id ) {
		$up = self::upcoming( $id );
		return $up ? $up[0] : '';
	}

	/** Sichtbar, solange ein Termin in der Zukunft liegt bzw. «Anzeigen bis» nicht vorbei ist. */
	public static function is_visible( $id ) {
		$today = current_time( 'Y-m-d' );
		$dates = self::dates( $id );
		if ( $dates && end( $dates ) < $today ) {
			return false;
		}
		$until = self::get( $id, 'until' );
		return ! ( $until && $until < $today );
	}

	/** Datum für die Anzeige, z. B. «Sa, 14.03.2026». */
	public static function format_date( $d ) {
		return wp_date( 'D, d.m.Y', strtotime( $d . ' 12:00' ) );
	}

	/** Veröffentlichte, sichtbare Angebote: mit Termin zuerst (nach nächstem Termin), danach nach Reihenfolge. */
	public static function visible_offers() {
		$posts = get_posts(
			array(
				'post_type'        => self::CPT,
				'post_status'      => 'publish',
				'numberposts'      => 50,
				'orderby'          => array(
					'menu_order' => 'ASC',
					'date'       => 'DESC',
				),
				'suppress_filters' => false,
			)
		);
		$posts = array_values(
			array_filter(
				$posts,
				function ( $p ) {
					return self::is_visible( $p->ID );
				}
			)
		);
		$next = array();
		foreach ( $posts as $p ) {
			$next[ $p->ID ] = self::next_date( $p->ID );
		}
		usort(
			$posts,
			function ( $a, $b ) use ( $next ) {
				$na = $next[ $a->ID ];
				$nb = $next[ $b->ID ];
				if ( $na && $nb ) {
					return strcmp( $na, $nb );
				}
				if ( $na || $nb ) {
					return $na ? -1 : 1;
				}
				return $a->menu_order <=> $b->menu_order;
			}
		);
		self::prime_thumbnails( $posts );
		return $posts;
	}

	/** Beitragsbilder aller Angebote mit einer Abfrage laden statt einzeln pro Karte. */
	public static function prime_thumbnails( array $posts ) {
		_prime_post_caches( array_filter( array_map( 'get_post_thumbnail_id', $posts ) ) );
	}

	/* ------------------------------------------------------------------ */
	/* Erfassungsfelder (Admin-Metabox und mobile Erfassung)               */
	/* ------------------------------------------------------------------ */

	public static function enqueue_form_assets() {
		wp_enqueue_style( 'mlab-offer-form', MLAB_OFFER_URL . 'assets/form.css', array(), MLAB_OFFER_VERSION );
		wp_enqueue_script( 'mlab-offer-form', MLAB_OFFER_URL . 'assets/form.js', array(), MLAB_OFFER_VERSION, true );
	}

	public static function render_fields( $post_id ) {
		wp_nonce_field( self::NONCE, self::NONCE_FIELD );
		echo '<div class="mlab-form">';
		foreach ( self::FIELDS as $key => $field ) {
			self::render_field( $key, $field, self::get( $post_id, $key ) );
			if ( 'text' === $key ) {
				self::render_dates( self::dates( $post_id ) );
			}
		}
		echo '</div>';
	}

	private static function render_field( $key, array $field, $value ) {
		list( $label, $hint, $type, $half ) = $field;
		$name = 'mlab_offer[' . $key . ']';
		printf( '<label class="mlab-f%s"><span>%s <em>%s</em></span>', $half ? ' mlab-half' : '', esc_html( $label ), esc_html( $hint ) );
		if ( 'textarea' === $type ) {
			printf( '<textarea name="%s" rows="4">%s</textarea>', esc_attr( $name ), esc_textarea( $value ) );
		} elseif ( 'focus' === $type ) {
			printf( '<select name="%s">', esc_attr( $name ) );
			foreach ( self::FOCUS as $option => $focus ) {
				printf( '<option value="%s"%s>%s</option>', esc_attr( $option ), selected( $value, $option, false ), esc_html( $focus[0] ) );
			}
			echo '</select>';
		} else {
			$extra = 'url' === $type ? ' inputmode="url" placeholder="https://"' : '';
			printf( '<input type="%s" name="%s" value="%s"%s>', esc_attr( $type ), esc_attr( $name ), esc_attr( $value ), $extra ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</label>';
	}

	private static function render_dates( array $dates ) {
		$row = '<div class="mlab-date"><input type="date" name="mlab_offer_dates[]" value="%s"><button type="button" class="button mlab-del" aria-label="Termin entfernen">✕</button></div>';
		echo '<div class="mlab-f"><span>Termine <em>leer lassen, wenn es kein fixes Datum gibt. Vergangene Termine verschwinden automatisch.</em></span><div class="mlab-dates">';
		foreach ( $dates ? $dates : array( '' ) as $d ) {
			printf( $row, esc_attr( $d ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div><button type="button" class="button mlab-add">+ Termin</button></div>';
	}

	/* ------------------------------------------------------------------ */
	/* Speichern                                                           */
	/* ------------------------------------------------------------------ */

	public static function save( $post_id ) {
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput -- wird pro Feld in sanitize() bereinigt.
		if ( ! isset( $_POST[ self::NONCE_FIELD ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$in    = isset( $_POST['mlab_offer'] ) && is_array( $_POST['mlab_offer'] ) ? wp_unslash( $_POST['mlab_offer'] ) : array();
		$dates = isset( $_POST['mlab_offer_dates'] ) && is_array( $_POST['mlab_offer_dates'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['mlab_offer_dates'] ) ) : array();
		// phpcs:enable

		foreach ( self::FIELDS as $key => $field ) {
			$val = self::sanitize( $field[2], isset( $in[ $key ] ) ? (string) $in[ $key ] : '' );
			if ( '' === $val ) {
				delete_post_meta( $post_id, self::META . $key );
			} else {
				update_post_meta( $post_id, self::META . $key, $val );
			}
		}
		update_post_meta( $post_id, self::META . 'dates', self::clean_dates( $dates ) );
	}

	private static function sanitize( $type, $val ) {
		$val = trim( $val );
		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( $val );
			case 'url':
				// Anker wie «#anfrage» sind erlaubt, sonst nur echte URLs.
				return 0 === strpos( $val, '#' ) ? sanitize_text_field( $val ) : esc_url_raw( $val );
			case 'focus':
				return isset( self::FOCUS[ $val ] ) ? $val : '';
			case 'date':
				return self::is_date( $val ) ? $val : '';
			default:
				return sanitize_text_field( $val );
		}
	}
}
