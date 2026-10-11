<?php
/**
 * Automatische Updates aus den GitHub-Releases von momentumlab-ch/wp-mlab-offer.
 *
 * Nutzt den WordPress-Mechanismus für «Update URI» (Plugin-Header): WordPress fragt
 * über den Filter update_plugins_github.com nach einer neuen Version. Das Update-ZIP
 * ist das Release-Asset «wp-mlab-offer.zip», das der GitHub-Workflow baut.
 *
 * @package wp-mlab-offer
 */

defined( 'ABSPATH' ) || exit;

final class MLab_Offer_Updater {

	const REPO  = 'momentumlab-ch/wp-mlab-offer';
	const SLUG  = 'wp-mlab-offer';
	const CACHE = 'mlab_offer_release';

	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'details' ), 20, 3 );
	}

	/** Nach dem Aktivieren sofort nach Updates suchen, damit «Automatische Aktualisierungen» gleich erscheint. */
	public static function activate() {
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Neueste Version für WordPress melden; den Versionsvergleich macht WordPress selbst.
	 * Ohne Antwort von GitHub meldet es die installierte Version, damit der Schalter
	 * für automatische Aktualisierungen trotzdem sichtbar bleibt.
	 */
	public static function check( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( MLAB_OFFER_FILE ) !== $plugin_file ) {
			return $update;
		}
		$release = self::release() ?: array(
			'version' => $plugin_data['Version'],
			'package' => '',
		);
		return array(
			'slug'         => self::SLUG,
			'version'      => $release['version'],
			'url'          => $plugin_data['PluginURI'],
			'package'      => $release['package'],
			'icons'        => self::icons(),
			'requires_php' => $plugin_data['RequiresPHP'],
		);
	}

	/** Inhalt für «Details ansehen» im Update-Dialog. */
	public static function details( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || self::SLUG !== ( $args->slug ?? '' ) ) {
			return $result;
		}
		$release = self::release();
		if ( ! $release ) {
			return $result;
		}
		$plugin = get_plugin_data( MLAB_OFFER_FILE );
		return (object) array(
			'name'          => $plugin['Name'],
			'slug'          => self::SLUG,
			'version'       => $release['version'],
			'author'        => $plugin['Author'],
			'homepage'      => $plugin['PluginURI'],
			'download_link' => $release['package'],
			'last_updated'  => $release['date'],
			'requires'      => $plugin['RequiresWP'],
			'requires_php'  => $plugin['RequiresPHP'],
			'icons'         => self::icons(),
			'sections'      => array( 'changelog' => wpautop( esc_html( $release['notes'] ) ) ),
		);
	}

	private static function icons() {
		return array(
			'svg' => MLAB_OFFER_URL . 'assets/icon.svg',
			'1x'  => MLAB_OFFER_URL . 'assets/icon-128x128.png',
			'2x'  => MLAB_OFFER_URL . 'assets/icon-256x256.png',
		);
	}

	/**
	 * Neuestes Release von GitHub, 6 Stunden zwischengespeichert («Erneut prüfen» unter
	 * Dashboard > Aktualisierungen holt es sofort neu). Leeres Array, wenn nichts gefunden.
	 */
	private static function release() {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification -- löscht nur den Cache.
			delete_site_transient( self::CACHE );
		}
		$release = get_site_transient( self::CACHE );
		if ( is_array( $release ) ) {
			return $release;
		}
		$release = self::fetch();
		set_site_transient( self::CACHE, $release, $release ? 6 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $release;
	}

	private static function fetch() {
		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);
		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return array();
		}
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		foreach ( $data['assets'] ?? array() as $asset ) {
			if ( self::SLUG . '.zip' === $asset['name'] ) {
				return array(
					'version' => ltrim( $data['tag_name'], 'v' ),
					'package' => $asset['browser_download_url'],
					'notes'   => (string) $data['body'],
					'date'    => $data['published_at'],
				);
			}
		}
		return array();
	}
}
