<?php
/**
 * The settings screen.
 *
 * One page, read top to bottom: what is working, who came, what to do about
 * it. The switches sit next to the evidence on purpose -- deciding whether to
 * turn GPTBot away is a different question once you can see it was here two
 * hours ago.
 *
 * @package AumCrawl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin screen.
 */
class AumCrawl_Admin {

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	protected $screen = '';

	/**
	 * Hook up.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_post_aumcrawl_save', array( $this, 'save' ) );
		add_action( 'admin_post_aumcrawl_clear', array( $this, 'clear' ) );
		add_filter(
			'plugin_action_links_' . plugin_basename( AUMCRAWL_FILE ),
			array( $this, 'action_links' )
		);
	}

	/**
	 * Register the page.
	 *
	 * @return void
	 */
	public function add_page() {
		$this->screen = (string) add_options_page(
			__( 'AumCrawl', 'aumcrawl' ),
			__( 'AI Crawlers', 'aumcrawl' ),
			'manage_options',
			'aumcrawl',
			array( $this, 'render' )
		);
	}

	/**
	 * Settings link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = menu_page_url( 'aumcrawl', false );

		if ( $url ) {
			array_unshift(
				$links,
				sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'aumcrawl' ) )
			);
		}

		return $links;
	}

	/**
	 * Stylesheet, on this screen only.
	 *
	 * @param string $hook Current screen hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( '' === $this->screen || $hook !== $this->screen ) {
			return;
		}

		$rel  = 'admin/assets/admin.css';
		$path = AUMCRAWL_DIR . $rel;

		wp_enqueue_style(
			'aumcrawl-admin',
			plugins_url( $rel, AUMCRAWL_FILE ),
			array( 'dashicons' ),
			file_exists( $path ) ? (string) filemtime( $path ) : AUMCRAWL_VERSION
		);
	}

	// ------------------------------------
	// Data
	// ------------------------------------

	/**
	 * Start of the reporting window.
	 *
	 * @return string Y-m-d
	 */
	public function window_start() {
		$settings = aumcrawl_get_settings();
		$days     = max( 7, min( 365, (int) $settings['retain_days'] ) );

		return gmdate( 'Y-m-d', time() - ( $days * DAY_IN_SECONDS ) );
	}

	/**
	 * Per-crawler totals for the window.
	 *
	 * @return array slug => array{hits:int,verified:int,unverified:int,last:string}
	 */
	public function bot_stats() {
		global $wpdb;

		$table = AumCrawl_Install::table( 'daily' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix; the date is prepared.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot, SUM(hits) AS hits, SUM(verified) AS verified,
				        SUM(unverified) AS unverified, MAX(hit_date) AS last
				 FROM {$table} WHERE hit_date >= %s GROUP BY bot",
				$this->window_start()
			),
			ARRAY_A
		);

		$out = array();

		foreach ( (array) $rows as $row ) {
			$out[ $row['bot'] ] = array(
				'hits'       => (int) $row['hits'],
				'verified'   => (int) $row['verified'],
				'unverified' => (int) $row['unverified'],
				'last'       => (string) $row['last'],
			);
		}

		return $out;
	}

	/**
	 * Most crawled paths.
	 *
	 * @param int $limit Rows to return.
	 * @return array
	 */
	public function top_pages( $limit = 10 ) {
		global $wpdb;

		$table = AumCrawl_Install::table( 'pages' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix; values prepared.
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT url, SUM(hits) AS hits FROM {$table}
				 WHERE hit_date >= %s GROUP BY url ORDER BY hits DESC LIMIT %d",
				$this->window_start(),
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Network ranges that claimed to be a crawler and failed the check.
	 *
	 * @param int $limit Rows to return.
	 * @return array
	 */
	public function suspects( $limit = 10 ) {
		global $wpdb;

		$table = AumCrawl_Install::table( 'suspect' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name from $wpdb->prefix; values prepared.
		return (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bot, net, SUM(hits) AS hits FROM {$table}
				 WHERE hit_date >= %s GROUP BY bot, net ORDER BY hits DESC LIMIT %d",
				$this->window_start(),
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Whether the identity check is actually producing verdicts.
	 *
	 * The check itself is subject to the same rule as everything else here:
	 * do not assume a mechanism works because it is switched on. Behind a CDN
	 * or a reverse proxy the connecting address belongs to that hop, so no
	 * verdict is reachable and the feature quietly does nothing.
	 *
	 * @param array $stats Per-crawler totals.
	 * @return string off|no-data|no-results|working
	 */
	public function verification_status( $stats ) {
		$settings = aumcrawl_get_settings();

		if ( empty( $settings['verify_bots'] ) ) {
			return 'off';
		}

		$hits    = 0;
		$verdict = 0;

		foreach ( $stats as $row ) {
			$hits    += $row['hits'];
			$verdict += $row['verified'] + $row['unverified'];
		}

		if ( 0 === $hits ) {
			return 'no-data';
		}

		return $verdict > 0 ? 'working' : 'no-results';
	}

	/**
	 * Which page cache, if any, is intercepting requests before PHP runs.
	 *
	 * Needed because those requests never reach the logger, so the totals on
	 * this screen would otherwise look complete when they are not.
	 *
	 * @return string Plugin name, or ''.
	 */
	public function detect_page_cache() {
		$known = array(
			'WP_ROCKET_VERSION'   => 'WP Rocket',
			'W3TC'                => 'W3 Total Cache',
			'WPCACHEHOME'         => 'WP Super Cache',
			'LSCWP_V'             => 'LiteSpeed Cache',
			'WPFC_MAIN_PATH'      => 'WP Fastest Cache',
		);

		foreach ( $known as $constant => $label ) {
			if ( defined( $constant ) ) {
				return $label;
			}
		}

		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'endurance-page-cache/endurance-page-cache.php' ) ) {
			return 'Endurance Page Cache';
		}

		return '';
	}

	// ------------------------------------
	// Save
	// ------------------------------------

	/**
	 * Handle the form.
	 *
	 * @return void
	 */
	public function save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aumcrawl' ) );
		}

		check_admin_referer( 'aumcrawl_save' );

		$settings = aumcrawl_get_settings();
		$posted   = isset( $_POST['aumcrawl_blocked'] )
			? array_map( 'sanitize_key', (array) wp_unslash( $_POST['aumcrawl_blocked'] ) )
			: array();

		$blocked = array();

		foreach ( $posted as $slug ) {
			$slug = sanitize_key( $slug );

			// Only crawlers the plugin offers a switch for can ever land here,
			// whatever the form said.
			if ( AumCrawl_Bots::is_switchable( $slug ) ) {
				$blocked[] = $slug;
			}
		}

		$settings['blocked']          = array_values( array_unique( $blocked ) );
		$settings['send_noai_header'] = isset( $_POST['aumcrawl_noai'] ) ? 1 : 0;
		$settings['hard_block']       = isset( $_POST['aumcrawl_hard'] ) ? 1 : 0;
		$settings['verify_bots']      = isset( $_POST['aumcrawl_verify'] ) ? 1 : 0;
		$retain                       = isset( $_POST['aumcrawl_retain'] ) ? absint( wp_unslash( $_POST['aumcrawl_retain'] ) ) : 30;
		$settings['retain_days']      = max( 7, min( 365, $retain ) );

		update_option( AUMCRAWL_OPTION, $settings );

		// robots.txt just changed, so the cached view of the live file is stale.
		delete_transient( 'aumcrawl_robots_status' );

		wp_safe_redirect( add_query_arg( 'updated', '1', menu_page_url( 'aumcrawl', false ) ) );
		exit;
	}

	/**
	 * Empty the log.
	 *
	 * @return void
	 */
	public function clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'aumcrawl' ) );
		}

		check_admin_referer( 'aumcrawl_clear' );

		global $wpdb;

		foreach ( array( 'daily', 'pages', 'suspect' ) as $which ) {
			$table = AumCrawl_Install::table( $which );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name built from $wpdb->prefix.
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}

		delete_transient( AumCrawl_Logger::CAP_KEY );

		wp_safe_redirect( add_query_arg( 'cleared', '1', menu_page_url( 'aumcrawl', false ) ) );
		exit;
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		require AUMCRAWL_DIR . 'admin/view-settings.php';
	}
}
