<?php
/**
 * One-time import of crawler settings and history left behind by AumViso.
 *
 * AumViso used to carry the same crawler module. It stands down when this
 * plugin is active, but its blocking rules live in its own option and its
 * counts in its own tables, so without this import a site that blocked GPTBot
 * in AumViso would quietly stop blocking it the moment this plugin arrived --
 * the owner believing a rule is in force that is not.
 *
 * It reads one option name and three table names. Nothing here calls AumViso
 * or requires it to be installed: on a site that never had it, every check
 * below simply finds nothing and the import does not run. That also makes the
 * import survive AumViso being deleted first, which is the order many people
 * will take once the crawler feature has moved here for good.
 *
 * @package AumCrawl
 */

defined( 'ABSPATH' ) || exit;

/**
 * Brings a site's old AumViso crawler data across, once.
 */
class AumCrawl_Import {

	/** Set when the import has run. Never cleared -- see maybe_import(). */
	const DONE_OPTION = 'aumcrawl_imported_from_aumviso';

	/** Set when the site owner undid the import. Never cleared either. */
	const REVERTED_OPTION = 'aumcrawl_import_reverted';

	/** The bot slugs this import added, so undo can remove only those. */
	const SLUGS_OPTION = 'aumcrawl_imported_slugs';

	/** Where AumViso keeps the settings we read. */
	const SOURCE_OPTION = 'aumviso_crawl_settings';

	/** Prefix of AumViso's three tables, after $wpdb->prefix. */
	const SOURCE_TABLE_PREFIX = 'aumviso_crawl_';

	/**
	 * Hook up.
	 *
	 * Activation covers the ordinary case. The admin_init pass covers sites
	 * where both plugins were already active before this version existed --
	 * their activation hook fired long ago and will not fire again.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'maybe_import' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notice' ) );
		add_action( 'admin_post_aumcrawl_undo_import', array( __CLASS__, 'handle_undo' ) );
	}

	/**
	 * Import, if this site has something to import and nobody has said no.
	 *
	 * @return void
	 */
	public static function maybe_import() {
		if ( get_option( self::DONE_OPTION ) ) {
			return;
		}

		/*
		 * "Has the owner configured this plugin?", not "does the option row
		 * exist?". Activation does not write settings today, so the two
		 * questions happen to have the same answer -- but that is a
		 * coincidence in the current code, not a promise. The day anything
		 * seeds defaults on activation, a row-exists test would answer "yes"
		 * for every fresh install and the import would silently never run,
		 * which looks exactly like having nothing to import.
		 */
		$existing = get_option( AUMCRAWL_OPTION );
		if ( is_array( $existing ) && ! empty( $existing['configured'] ) ) {
			return;
		}

		$settings_imported = self::import_settings();
		$rows_imported     = self::import_history();

		if ( ! $settings_imported && ! $rows_imported ) {
			return;
		}

		update_option( self::DONE_OPTION, time() );
		set_transient( 'aumcrawl_import_just_ran', array(
			'bots' => count( (array) get_option( self::SLUGS_OPTION, array() ) ),
			'rows' => $rows_imported,
		), DAY_IN_SECONDS );
	}

	/**
	 * Copy the blocking rules across.
	 *
	 * Settings go first: if the history copy below fails a column check and is
	 * skipped, the site is still blocking what its owner asked it to block.
	 * Missing rows are a loss; a missing rule is a site doing the opposite of
	 * what its owner believes.
	 *
	 * @return bool Whether anything was imported.
	 */
	private static function import_settings() {
		$source = get_option( self::SOURCE_OPTION );

		if ( ! is_array( $source ) ) {
			return false;
		}

		$blocked = isset( $source['blocked'] ) ? array_values( array_filter( (array) $source['blocked'], 'is_string' ) ) : array();

		if ( empty( $blocked ) ) {
			return false;
		}

		// Only slugs this plugin recognises; the two registries match today,
		// and if they ever stop matching an unknown slug would sit in the
		// blocked list doing nothing while looking like it worked.
		$known   = array_keys( AumCrawl_Bots::all() );
		$blocked = array_values( array_intersect( $blocked, $known ) );

		if ( empty( $blocked ) ) {
			return false;
		}

		$settings            = aumcrawl_get_settings();
		$settings['blocked'] = array_values( array_unique( array_merge( (array) $settings['blocked'], $blocked ) ) );

		foreach ( array( 'send_noai_header', 'hard_block', 'verify_bots', 'retain_days' ) as $key ) {
			if ( isset( $source[ $key ] ) ) {
				$settings[ $key ] = $source[ $key ];
			}
		}

		update_option( AUMCRAWL_OPTION, $settings );
		update_option( self::SLUGS_OPTION, $blocked );
		delete_transient( 'aumcrawl_robots_status' );

		return true;
	}

	/**
	 * Copy the three history tables across.
	 *
	 * Both plugins built these tables from the same code, so the columns match
	 * today. They are compared anyway: if either side ever gains a column the
	 * copy is skipped rather than attempted, because a partial history is
	 * worse than an absent one -- it looks complete.
	 *
	 * @return int Rows copied.
	 */
	private static function import_history() {
		global $wpdb;

		$copied = 0;

		$columns = array(
			'daily'   => array( 'hit_date', 'bot', 'hits', 'verified', 'unverified' ),
			'pages'   => array( 'hit_date', 'bot', 'url_hash', 'url', 'hits' ),
			'suspect' => array( 'hit_date', 'bot', 'net', 'hits' ),
		);

		foreach ( $columns as $which => $cols ) {
			$from = $wpdb->prefix . self::SOURCE_TABLE_PREFIX . $which;
			$to   = $wpdb->prefix . 'aumcrawl_' . $which;

			if ( ! self::table_has_columns( $from, $cols ) || ! self::table_has_columns( $to, $cols ) ) {
				continue;
			}

			$list = '`' . implode( '`,`', $cols ) . '`';

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table and column names are the literals above, checked against the live schema first; there are no values to prepare.
			/*
			 * The no-op assignment means "leave any row that is already here
			 * alone" -- this is a one-way import, not a merge. It has to name
			 * the destination table: in an INSERT ... SELECT both tables carry
			 * a `hits` column, and an unqualified one is rejected as ambiguous
			 * rather than guessed at.
			 */
			$result = $wpdb->query( "INSERT INTO `{$to}` ({$list}) SELECT {$list} FROM `{$from}` ON DUPLICATE KEY UPDATE `{$to}`.`hits` = `{$to}`.`hits`" );

			if ( is_int( $result ) ) {
				$copied += $result;
			}
		}

		return $copied;
	}

	/**
	 * Does this table exist with exactly the columns we are about to copy?
	 *
	 * @param string   $table Full table name.
	 * @param string[] $cols  Column names that must all be present.
	 * @return bool
	 */
	private static function table_has_columns( $table, $cols ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is built from $wpdb->prefix and a literal.
		$found = $wpdb->get_col( "SHOW COLUMNS FROM `{$table}`" );

		if ( ! is_array( $found ) || empty( $found ) ) {
			return false;
		}

		return ! array_diff( $cols, $found );
	}

	/**
	 * Tell the owner what was imported, and offer to undo it.
	 *
	 * Silence would be the wrong choice in both directions: a site that gains
	 * blocking rules nobody set here is as much of a surprise as one that
	 * loses them.
	 *
	 * @return void
	 */
	public static function notice() {
		$just = get_transient( 'aumcrawl_import_just_ran' );

		if ( ! $just || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$undo = wp_nonce_url( admin_url( 'admin-post.php?action=aumcrawl_undo_import' ), 'aumcrawl_undo_import' );

		echo '<div class="notice notice-info is-dismissible"><p>';
		printf(
			/* translators: 1: number of crawlers, 2: number of history rows */
			esc_html__( 'AumCrawl imported your crawler settings from AumViso: %1$d blocked crawlers and %2$d days of history. Your rules are in force again.', 'aumcrawl' ),
			(int) $just['bots'],
			(int) $just['rows']
		);
		echo ' <a href="' . esc_url( $undo ) . '">' . esc_html__( 'Undo the imported rules', 'aumcrawl' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Undo: remove the imported rules, keep the history.
	 *
	 * Only the slugs this import added are removed. Anything the owner blocked
	 * himself afterwards stays blocked -- undoing an import must not quietly
	 * unblock a crawler he chose.
	 *
	 * History is left alone. It is a record of what happened, it changes
	 * nothing about how the site behaves, and it cannot be got back.
	 *
	 * @return void
	 */
	public static function handle_undo() {
		if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'aumcrawl_undo_import' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'aumcrawl' ) );
		}

		$imported = (array) get_option( self::SLUGS_OPTION, array() );
		$settings = aumcrawl_get_settings();

		$settings['blocked'] = array_values( array_diff( (array) $settings['blocked'], $imported ) );

		update_option( AUMCRAWL_OPTION, $settings );
		update_option( self::REVERTED_OPTION, time() );
		delete_option( self::SLUGS_OPTION );
		delete_transient( 'aumcrawl_import_just_ran' );
		delete_transient( 'aumcrawl_robots_status' );

		// DONE_OPTION deliberately stays set: the owner has said no to these
		// rules, and deactivating and reactivating the plugin must not put
		// them back.
		wp_safe_redirect( admin_url( 'admin.php?page=aumcrawl&aumcrawl_reverted=1' ) );
		exit;
	}
}
