<?php
/**
 * Erfassung im WordPress-Admin: Metabox und Spalten der Angebotsliste.
 *
 * @package wp-mlab-offer
 */

defined( 'ABSPATH' ) || exit;

final class MLab_Offer_Admin {

	public static function init() {
		$cpt = MLab_Offer::CPT;
		add_action( 'add_meta_boxes_' . $cpt, array( __CLASS__, 'meta_box' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( "manage_{$cpt}_posts_columns", array( __CLASS__, 'columns' ) );
		add_action( "manage_{$cpt}_posts_custom_column", array( __CLASS__, 'column' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'no_block_editor' ), 10, 2 );
		add_action( 'admin_notices', array( __CLASS__, 'capture_hint' ) );
	}

	public static function meta_box() {
		add_meta_box(
			'mlab_offer_main',
			'Angebot',
			function ( $post ) {
				echo '<p class="mlab-help">Titel oben eingeben, Bild rechts bzw. unten unter «Bild» wählen. Alle Felder sind freiwillig, leere Felder werden nicht angezeigt.</p>';
				MLab_Offer::render_fields( $post->ID );
			},
			MLab_Offer::CPT,
			'normal',
			'high'
		);
	}

	public static function assets() {
		$screen = get_current_screen();
		if ( $screen && MLab_Offer::CPT === $screen->post_type && 'post' === $screen->base ) {
			MLab_Offer::enqueue_form_assets();
		}
	}

	public static function no_block_editor( $use, $post_type ) {
		return MLab_Offer::CPT === $post_type ? false : $use;
	}

	public static function title_placeholder( $text, $post ) {
		return MLab_Offer::CPT === $post->post_type ? 'Titel, z. B. Tiefschnee-Technikkurs Ski' : $text;
	}

	/** Hinweis auf die mobile Erfassung über der Angebotsliste. */
	public static function capture_hint() {
		$screen = get_current_screen();
		if ( $screen && 'edit-' . MLab_Offer::CPT === $screen->id ) {
			printf( '<div class="notice notice-info"><p>📱 Vom Smartphone erfassen: <a href="%1$s">%1$s</a></p></div>', esc_url( MLab_Offer_Capture::url() ) );
		}
	}

	public static function columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $label ) {
			$new[ $k ] = $label;
			if ( 'title' === $k ) {
				$new['mlab_next']  = 'Nächster Termin';
				$new['mlab_price'] = 'Preis';
				$new['mlab_live']  = 'Sichtbar';
			}
		}
		unset( $new['date'] );
		return $new;
	}

	public static function column( $col, $id ) {
		if ( 'mlab_next' === $col ) {
			$next = MLab_Offer::next_date( $id );
			echo esc_html( $next ? MLab_Offer::format_date( $next ) : '–' );
		} elseif ( 'mlab_price' === $col ) {
			echo esc_html( MLab_Offer::get( $id, 'price' ) ?: '–' );
		} elseif ( 'mlab_live' === $col ) {
			echo MLab_Offer::is_visible( $id ) && 'publish' === get_post_status( $id ) ? '✅' : '—';
		}
	}
}
