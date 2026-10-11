<?php
/**
 * Ausgabe der Angebote als Karten per Shortcode, inkl. schema.org-Daten.
 *
 * @package wp-mlab-offer
 */

defined( 'ABSPATH' ) || exit;

final class MLab_Offer_Cards {

	/** Karten-CSS, direkt in die Seite geschrieben (kein Aufblitzen ohne Stil). */
	const CSS = '
.afa{max-width:1360px;margin:0 auto;padding:96px clamp(16px,4vw,48px) 0;color:#0E2430}
.afa-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-end;gap:16px;margin-bottom:32px}
.afa-head>div{display:flex;flex-direction:column;gap:8px}
.afa-eyebrow{color:#0072A3;font-weight:600;text-transform:uppercase;letter-spacing:.08em;font-size:14px}
.afa-h2{font-size:clamp(34px,5vw,52px)}
.afa-intro{margin:0;max-width:420px;color:#5B6B74}
.afa-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:24px}
.afa-grid.afa-n2{grid-template-columns:repeat(2,minmax(0,1fr))}
.afa-grid.afa-n1{grid-template-columns:1fr}
.afa-card{display:flex;flex-direction:column;background:#FFFFFF;border-radius:20px;overflow:hidden;box-shadow:0 1px 0 #DCE5EA;min-width:0}
.afa-img{position:relative;height:200px;background:#2B3D47}
.afa-img img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
.afa-n2 .afa-img{height:260px}
.afa-n1 .afa-card{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,1fr)}
.afa-n1 .afa-img{height:auto;min-height:380px}
.afa-body{display:flex;flex-direction:column;gap:12px;padding:24px 28px 28px;flex:1}
.afa-n1 .afa-body{padding:36px 40px}
.afa-h3{font-size:28px}
.afa-n1 .afa-h3{font-size:40px}
.afa-badge{align-self:flex-start;padding:4px 10px;border-radius:999px;background:#00AEEF;color:#0E2430;font-size:13px;font-weight:600;letter-spacing:.02em}
.afa-text{margin:0;color:#3D525E}
.afa-dl{margin:0;display:grid;grid-template-columns:auto 1fr;gap:4px 14px;font-size:15px}
.afa-dl dt{color:#5B6B74}.afa-dl dd{margin:0}
.afa-price{margin:0;font-size:18px}.afa-price span{color:#5B6B74;font-size:15px}
.afa-chips{display:flex;flex-wrap:wrap;gap:8px}
.afa-chip{display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;background:#EEF6FA;color:#0E2430;font-size:15px;font-weight:600}
.afa .afa-btn{margin-top:auto;align-self:flex-start;background:#00AEEF;color:#0E2430;display:inline-flex;align-items:center;justify-content:center;min-height:48px;padding:0 24px;border-radius:999px;font-weight:600;text-decoration:none;font-size:16px}
.afa .afa-btn-dark{background:#0E2430;color:#FFFFFF}
.afa-note{color:#5B6B74;font-size:14px}
@media (max-width:600px){.afa{padding-top:56px}.afa-body,.afa-n1 .afa-body{padding:20px 20px 24px!important}.afa-img{height:220px}.afa-dl{grid-template-columns:1fr;gap:0}.afa-dl dd{margin-bottom:6px}}
@media (max-width:900px){.afa-grid,.afa-grid.afa-n2{grid-template-columns:1fr}.afa-n1 .afa-card{display:flex}.afa-n1 .afa-img{min-height:0;height:240px}.afa-n1 .afa-body{padding:24px 28px 28px}.afa-n1 .afa-h3{font-size:28px}}
';

	public static function init() {
		add_shortcode( 'mlab_angebote', array( __CLASS__, 'shortcode' ) );
		// Alter Shortcode des Vorgängers, damit bestehende Seiten weiter funktionieren.
		add_shortcode( 'alpine_flow_angebote', array( __CLASS__, 'shortcode' ) );
	}

	public static function shortcode( $atts, $content = '', $tag = 'mlab_angebote' ) {
		$atts   = shortcode_atts(
			array(
				'eyebrow' => 'Termine & News',
				'titel'   => 'Neu im Programm',
				'intro'   => 'Kurse mit festen Daten, neue Angebote und die Preise der Saison. Vergangene Termine verschwinden automatisch.',
				'max'     => 0,
			),
			$atts,
			$tag
		);
		$offers = MLab_Offer::visible_offers();
		if ( (int) $atts['max'] > 0 ) {
			$offers = array_slice( $offers, 0, (int) $atts['max'] );
		}
		if ( ! $offers ) {
			return ''; // Keine Angebote: Section verschwindet ganz.
		}
		$n = min( count( $offers ), 3 );

		ob_start();
		self::css();
		?>
		<section id="termine" class="afa" aria-labelledby="afa-titel">
			<div class="afa-head">
				<div>
					<span class="afa-eyebrow"><?php echo esc_html( $atts['eyebrow'] ); ?></span>
					<h2 id="afa-titel" class="d afa-h2"><?php echo esc_html( $atts['titel'] ); ?></h2>
				</div>
				<?php if ( $atts['intro'] ) : ?><p class="afa-intro"><?php echo esc_html( $atts['intro'] ); ?></p><?php endif; ?>
			</div>
			<div class="afa-grid afa-n<?php echo (int) $n; ?>">
				<?php
				foreach ( $offers as $p ) {
					self::card( $p, 1 === $n );
				}
				?>
			</div>
		</section>
		<?php
		self::jsonld( $offers );
		return ob_get_clean();
	}

	/** «Bezeichnung: Wert»-Zeilen als Paare. */
	private static function details( $text ) {
		$details = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$parts     = array_map( 'trim', explode( ':', $line, 2 ) );
				$details[] = 2 === count( $parts ) ? $parts : array( '', $parts[0] );
			}
		}
		return $details;
	}

	private static function card( $p, $single ) {
		$id       = $p->ID;
		$g        = function ( $k ) use ( $id ) {
			return MLab_Offer::get( $id, $k );
		};
		$title    = get_the_title( $id );
		$upcoming = MLab_Offer::upcoming( $id );
		$tags     = array_filter( array_map( 'trim', explode( ',', $g( 'tags' ) ) ) );
		$details  = self::details( $g( 'details' ) );
		$btn_url  = $g( 'btn_url' ) ?: '#anfrage';
		$btn_text = $g( 'btn_text' ) ?: 'Anfragen';
		$external = 0 === strpos( $btn_url, 'http' ) && false === strpos( $btn_url, wp_parse_url( home_url(), PHP_URL_HOST ) );
		$focus    = MLab_Offer::FOCUS[ $g( 'focus' ) ] ?? MLab_Offer::FOCUS[''];
		$attrs    = $external ? ' rel="noopener" target="_blank"' : '';
		$attrs   .= '#anfrage' === $btn_url ? ' data-anfrage="' . esc_attr( $title ) . '"' : '';
		?>
		<article class="afa-card">
			<div class="afa-img">
				<?php
				echo get_the_post_thumbnail(
					$id,
					'large',
					array(
						'alt'      => $title,
						'loading'  => 'lazy',
						'decoding' => 'async',
						'sizes'    => $single ? '(max-width: 900px) 100vw, 55vw' : '(max-width: 900px) 100vw, 33vw',
						'style'    => 'object-position:' . $focus[1],
					)
				);
				?>
			</div>
			<div class="afa-body">
				<?php if ( $g( 'badge' ) ) : ?><span class="afa-badge"><?php echo esc_html( $g( 'badge' ) ); ?></span><?php endif; ?>
				<h3 class="d afa-h3"><?php echo esc_html( $title ); ?></h3>
				<?php if ( $g( 'text' ) ) : ?><p class="afa-text"><?php echo nl2br( esc_html( $g( 'text' ) ) ); ?></p><?php endif; ?>
				<?php if ( $details ) : ?>
					<dl class="afa-dl">
						<?php foreach ( $details as $d ) : ?>
							<dt><?php echo esc_html( $d[0] ); ?></dt><dd><?php echo esc_html( $d[1] ); ?></dd>
						<?php endforeach; ?>
					</dl>
				<?php endif; ?>
				<?php if ( $g( 'price' ) ) : ?>
					<p class="afa-price"><strong><?php echo esc_html( $g( 'price' ) ); ?></strong><?php if ( $g( 'price_note' ) ) : ?> <span><?php echo esc_html( $g( 'price_note' ) ); ?></span><?php endif; ?></p>
				<?php endif; ?>
				<?php if ( $upcoming || $tags ) : ?>
					<div class="afa-chips">
						<?php foreach ( $upcoming as $d ) : ?>
							<span class="afa-chip"><time datetime="<?php echo esc_attr( $d ); ?>"><?php echo esc_html( MLab_Offer::format_date( $d ) ); ?></time></span>
						<?php endforeach; ?>
						<?php foreach ( $tags as $t ) : ?>
							<span class="afa-chip"><?php echo esc_html( $t ); ?></span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<a class="btn afa-btn<?php echo $external ? ' afa-btn-dark' : ''; ?>" href="<?php echo esc_url( $btn_url ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput ?>><?php echo esc_html( $btn_text . ( $external ? ' ↗' : '' ) ); ?></a>
				<?php if ( $g( 'note' ) ) : ?><span class="afa-note"><?php echo esc_html( $g( 'note' ) ); ?></span><?php endif; ?>
			</div>
		</article>
		<?php
	}

	/** Strukturierte Daten: Termine als Event, Preise als Offer. Anpassbar per Filter «mlab_offer_event». */
	private static function jsonld( $offers ) {
		$shared = array(
			'@type'               => 'Event',
			'eventStatus'         => 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
			'location'            => array(
				'@type'   => 'Place',
				'name'    => 'Grindelwald',
				'address' => array(
					'@type'           => 'PostalAddress',
					'addressLocality' => 'Grindelwald',
					'postalCode'      => '3818',
					'addressCountry'  => 'CH',
				),
			),
			'organizer'           => array(
				'@type' => 'Organization',
				'name'  => get_bloginfo( 'name' ),
				'url'   => home_url( '/' ),
			),
		);
		$items  = array();
		foreach ( $offers as $p ) {
			$event = array_filter(
				array(
					'name'        => get_the_title( $p->ID ),
					'description' => MLab_Offer::get( $p->ID, 'text' ),
					'image'       => get_the_post_thumbnail_url( $p->ID, 'large' ),
					'offers'      => self::jsonld_offer( $p->ID ),
				)
			) + $shared;
			foreach ( MLab_Offer::upcoming( $p->ID ) as $d ) {
				$items[] = apply_filters( 'mlab_offer_event', $event + array( 'startDate' => $d ), $p );
			}
		}
		if ( $items ) {
			echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $items ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
		}
	}

	/** Preis als schema.org Offer, falls im Preisfeld eine Zahl steht. */
	private static function jsonld_offer( $id ) {
		if ( ! preg_match( '/(\d+(?:[.,]\d{1,2})?)/', MLab_Offer::get( $id, 'price' ), $m ) ) {
			return null;
		}
		return array(
			'@type'         => 'Offer',
			'price'         => str_replace( ',', '.', $m[1] ),
			'priceCurrency' => 'CHF',
			'url'           => MLab_Offer::get( $id, 'btn_url' ) ?: home_url( '/' ),
			'availability'  => 'https://schema.org/InStock',
		);
	}

	/** Karten-CSS einmal pro Seite direkt ausgeben (kein Aufblitzen ohne Stil). */
	private static function css() {
		static $done = false;
		if ( ! $done ) {
			$done = true;
			echo '<style>' . self::CSS . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
