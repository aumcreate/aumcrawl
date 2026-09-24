<?php
/**
 * Plugin Name:       AumCrawl – AI Crawler Control: See and Block AI Bots
 * Plugin URI:       https://aumcreate.com/plugins/aumcrawl
 * Description:       See which AI crawlers read your site, and block the ones that never send traffic back. Logs every known crawler, verifies who they claim to be, and writes robots.txt rules alongside your SEO plugin instead of fighting it.
 * Version:           1.0.4
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            AumCreate
 * Author URI:        https://aumcreate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       aumcrawl
 *
 * @package AumCrawl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AUMCRAWL_VERSION', '1.0.4' );
define( 'AUMCRAWL_FILE', __FILE__ );
define( 'AUMCRAWL_DIR', plugin_dir_path( __FILE__ ) );
define( 'AUMCRAWL_OPTION', 'aumcrawl_settings' );

require_once AUMCRAWL_DIR . 'includes/class-aumcrawl-bots.php';
require_once AUMCRAWL_DIR . 'includes/class-aumcrawl-install.php';
require_once AUMCRAWL_DIR . 'includes/class-aumcrawl-logger.php';
require_once AUMCRAWL_DIR . 'includes/class-aumcrawl-robots.php';
require_once AUMCRAWL_DIR . 'includes/class-aumcrawl-headers.php';
require_once AUMCRAWL_DIR . 'includes/class-aumcrawl-blocker.php';
require_once AUMCRAWL_DIR . 'includes/class-aumcrawl-verify.php';
require_once AUMCRAWL_DIR . 'includes/class-aumcrawl-privacy.php';
require_once AUMCRAWL_DIR . 'includes/class-aumcrawl-import.php';

/**
 * Default settings.
 *
 * Only bots in the "extractive" group ship blocked-by-default candidates, and
 * even those start allowed: a plugin that changes what crawlers see the moment
 * it is activated would be a nasty surprise.
 *
 * @return array
 */
function aumcrawl_defaults() {
	return array(
		'blocked'          => array(),
		'send_noai_header' => 0,
		'hard_block'       => 0,
		'verify_bots'      => 1,
		'retain_days'      => 30,
		'configured'       => 0,
	);
}

/**
 * Read settings merged over defaults.
 *
 * @return array
 */
function aumcrawl_get_settings() {
	$saved = get_option( AUMCRAWL_OPTION, array() );

	if ( ! is_array( $saved ) ) {
		$saved = array();
	}

	return wp_parse_args( $saved, aumcrawl_defaults() );
}

/**
 * Is this bot blocked by the site owner?
 *
 * @param string $slug Bot slug from the registry.
 * @return bool
 */
function aumcrawl_is_blocked( $slug ) {
	$settings = aumcrawl_get_settings();

	return in_array( $slug, (array) $settings['blocked'], true );
}

register_activation_hook(
	__FILE__,
	static function () {
		AumCrawl_Install::activate();
		AumCrawl_Import::maybe_import();
	}
);
register_deactivation_hook( __FILE__, array( 'AumCrawl_Install', 'deactivate' ) );

new AumCrawl_Logger();
new AumCrawl_Robots();
new AumCrawl_Headers();
new AumCrawl_Blocker();
new AumCrawl_Verify();
new AumCrawl_Privacy();

AumCrawl_Import::register();

if ( is_admin() ) {
	require_once AUMCRAWL_DIR . 'admin/class-aumcrawl-admin.php';
	new AumCrawl_Admin();
}
