<?php
/**
 * Plugin Name: MomentumLab Angebote
 * Plugin URI: https://github.com/momentumlab-ch/wp-mlab-offer
 * Description: Angebote, Kurse und Termine einfach erfassen (auch vom Smartphone unter /angebote-erfassen) und mit dem Shortcode [mlab_angebote] als Karten anzeigen.
 * Version: 2.0.3
 * Author: MomentumLab
 * Author URI: https://momentumlab.ch
 * License: GPL-2.0-or-later
 * Text Domain: wp-mlab-offer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI: https://github.com/momentumlab-ch/wp-mlab-offer
 */

defined( 'ABSPATH' ) || exit;

// Gleiche Version wie im Header oben; der Release-Workflow liest den Header.
define( 'MLAB_OFFER_VERSION', '2.0.3' );
define( 'MLAB_OFFER_FILE', __FILE__ );
define( 'MLAB_OFFER_DIR', plugin_dir_path( __FILE__ ) );
define( 'MLAB_OFFER_URL', plugin_dir_url( __FILE__ ) );

// Vorgänger «Alpine Flow Angebote» nutzt dieselben Daten: nie beide gleichzeitig laufen lassen,
// sondern den Vorgänger deaktivieren. Die Angebote bleiben erhalten.
if ( class_exists( 'Alpine_Flow_Angebote' ) ) {
	add_action(
		'admin_init',
		function () {
			deactivate_plugins( 'alpine-flow-angebote/alpine-flow-angebote.php' );
		}
	);
	return;
}

require_once MLAB_OFFER_DIR . 'includes/class-mlab-offer.php';
require_once MLAB_OFFER_DIR . 'includes/class-mlab-offer-admin.php';
require_once MLAB_OFFER_DIR . 'includes/class-mlab-offer-cards.php';
require_once MLAB_OFFER_DIR . 'includes/class-mlab-offer-capture.php';
require_once MLAB_OFFER_DIR . 'includes/class-mlab-offer-updater.php';

MLab_Offer::init();
MLab_Offer_Admin::init();
MLab_Offer_Cards::init();
MLab_Offer_Capture::init();
MLab_Offer_Updater::init();

register_activation_hook(
	__FILE__,
	function () {
		MLab_Offer_Capture::activate();
		MLab_Offer_Updater::activate();
	}
);
