<?php
/**
 * Settings screen markup.
 *
 * Rendered by AumCrawl_Admin::render(), which has already checked the
 * capability, so $this is the admin object throughout.
 *
 * @package AumCrawl
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$aumcrawl_settings = aumcrawl_get_settings();
$aumcrawl_stats    = $this->bot_stats();
$aumcrawl_pages    = $this->top_pages();
$aumcrawl_suspects = $this->suspects();
$aumcrawl_cache    = $this->detect_page_cache();
$aumcrawl_verify   = $this->verification_status( $aumcrawl_stats );
$aumcrawl_robots   = AumCrawl_Robots::live_status();
$aumcrawl_deferred = (array) get_option( AumCrawl_Robots::DEFERRED, array() );

$aumcrawl_total    = 0;
$aumcrawl_ai_seen  = 0;

foreach ( AumCrawl_Bots::all() as $aumcrawl_slug => $aumcrawl_bot ) {
	if ( empty( $aumcrawl_stats[ $aumcrawl_slug ]['hits'] ) ) {
		continue;
	}

	$aumcrawl_total += $aumcrawl_stats[ $aumcrawl_slug ]['hits'];

	if ( AumCrawl_Bots::GROUP_ESSENTIAL !== $aumcrawl_bot['group'] ) {
		$aumcrawl_ai_seen++;
	}
}

/**
 * One row of the crawler table.
 *
 * @param string $slug     Bot slug.
 * @param array  $bot      Registry entry.
 * @param array  $stats    Totals keyed by slug.
 * @param array  $deferred Slugs another plugin already writes rules for.
 * @return void
 */
function aumcrawl_render_row( $slug, $bot, $stats, $deferred ) {
	$row      = isset( $stats[ $slug ] ) ? $stats[ $slug ] : array( 'hits' => 0, 'last' => '', 'unverified' => 0 );
	$blocked  = aumcrawl_is_blocked( $slug );
	$switch   = AumCrawl_Bots::is_switchable( $slug );
	$is_defer = in_array( $slug, $deferred, true );
	?>
	<tr>
		<td class="aumcrawl-bot">
			<strong><?php echo esc_html( $bot['label'] ); ?></strong>
			<span class="aumcrawl-vendor"><?php echo esc_html( $bot['vendor'] ); ?></span>
			<p class="aumcrawl-note"><?php echo esc_html( $bot['note'] ); ?></p>
			<?php if ( $is_defer ) : ?>
				<p class="aumcrawl-defer">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					<?php esc_html_e( 'Another plugin already writes a robots.txt rule for this crawler, so this one stands aside. Manage it there.', 'aumcrawl' ); ?>
				</p>
			<?php endif; ?>
		</td>
		<td class="aumcrawl-num">
			<?php if ( $row['hits'] > 0 ) : ?>
				<span class="aumcrawl-hits"><?php echo esc_html( number_format_i18n( $row['hits'] ) ); ?></span>
				<span class="aumcrawl-when">
					<?php
					printf(
						/* translators: %s: human-readable time difference, e.g. "2 hours". */
						esc_html__( '%s ago', 'aumcrawl' ),
						esc_html( human_time_diff( strtotime( $row['last'] . ' 00:00:00 UTC' ) ) )
					);
					?>
				</span>
				<?php if ( ! empty( $row['unverified'] ) ) : ?>
					<span class="aumcrawl-pill aumcrawl-pill-warn">
						<?php
						printf(
							/* translators: %s: number of requests that failed verification. */
							esc_html__( '%s could not be verified', 'aumcrawl' ),
							esc_html( number_format_i18n( $row['unverified'] ) )
						);
						?>
					</span>
				<?php endif; ?>
			<?php else : ?>
				<span class="aumcrawl-never"><?php esc_html_e( 'Never seen', 'aumcrawl' ); ?></span>
			<?php endif; ?>
		</td>
		<td class="aumcrawl-switch-cell">
			<?php if ( $switch ) : ?>
				<label class="aml-switch-row">
					<span class="aml-switch">
						<input type="checkbox" name="aumcrawl_blocked[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $blocked ); ?> />
						<span class="aml-track"></span>
					</span>
					<span class="aml-switch-label"><?php esc_html_e( 'Block', 'aumcrawl' ); ?></span>
				</label>
			<?php else : ?>
				<span class="aumcrawl-locked"><?php esc_html_e( 'Always allowed', 'aumcrawl' ); ?></span>
			<?php endif; ?>
		</td>
	</tr>
	<?php
}
?>
<div class="wrap aml-app aumcrawl-app">

	<div class="aml-header">
		<span class="aml-logo" aria-hidden="true"><span class="dashicons dashicons-visibility"></span></span>
		<div>
			<h1 class="aml-title">
				<?php esc_html_e( 'AumCrawl', 'aumcrawl' ); ?>
				<span class="aml-badge">v<?php echo esc_html( AUMCRAWL_VERSION ); ?></span>
			</h1>
			<p class="aml-subtitle">
				<?php esc_html_e( 'See which crawlers read your site, and turn away the ones that never send anything back.', 'aumcrawl' ); ?>
			</p>
		</div>
	</div>
	<hr class="wp-header-end">

	<?php if ( isset( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Saved.', 'aumcrawl' ); ?></p></div>
	<?php endif; ?>

	<?php if ( isset( $_GET['cleared'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Records cleared.', 'aumcrawl' ); ?></p></div>
	<?php endif; ?>

	<div class="aml-card aumcrawl-summary">
		<?php if ( $aumcrawl_total > 0 ) : ?>
			<p class="aumcrawl-headline">
				<?php
				printf(
					/* translators: 1: number of AI and SEO crawlers, 2: total number of visits. */
					esc_html__( '%1$s crawlers read this site %2$s times recently.', 'aumcrawl' ),
					'<strong>' . esc_html( number_format_i18n( $aumcrawl_ai_seen ) ) . '</strong>',
					'<strong>' . esc_html( number_format_i18n( $aumcrawl_total ) ) . '</strong>'
				);
				?>
			</p>
		<?php else : ?>
			<p class="aumcrawl-headline"><?php esc_html_e( 'No crawler visits recorded yet. Counting starts the moment the plugin is active; give it a day or two.', 'aumcrawl' ); ?></p>
		<?php endif; ?>

		<?php if ( $aumcrawl_cache ) : ?>
			<p class="aml-field-hint">
				<?php
				printf(
					/* translators: %s: name of the detected page cache plugin. */
					esc_html__( '%s is active. Requests it answers from cache never reach PHP, so these counts are a lower bound rather than a full total.', 'aumcrawl' ),
					'<strong>' . esc_html( $aumcrawl_cache ) . '</strong>'
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<div class="aml-card">
		<h2 class="aml-card-h">
			<span class="dashicons dashicons-shield" aria-hidden="true"></span>
			<?php esc_html_e( 'What is actually in effect', 'aumcrawl' ); ?>
		</h2>

		<ul class="aumcrawl-status">
			<li>
				<?php if ( $aumcrawl_robots['physical'] ) : ?>
					<span class="aumcrawl-dot is-warn"></span>
					<strong><?php esc_html_e( 'Rules in robots.txt', 'aumcrawl' ); ?></strong>
					<?php esc_html_e( 'not in effect - a robots.txt file exists in your site root, and WordPress only serves its own when that file is absent.', 'aumcrawl' ); ?>
				<?php elseif ( ! $aumcrawl_robots['reachable'] ) : ?>
					<span class="aumcrawl-dot is-warn"></span>
					<strong><?php esc_html_e( 'Rules in robots.txt', 'aumcrawl' ); ?></strong>
					<?php esc_html_e( 'could not be checked - this site did not answer a request to its own /robots.txt.', 'aumcrawl' ); ?>
				<?php elseif ( ! $aumcrawl_robots['has_rules'] && $aumcrawl_settings['blocked'] ) : ?>
					<span class="aumcrawl-dot is-bad"></span>
					<strong><?php esc_html_e( 'Rules in robots.txt', 'aumcrawl' ); ?></strong>
					<?php esc_html_e( 'are missing from the served file. Another plugin is replacing robots.txt rather than adding to it. Paste the block below into that plugin instead.', 'aumcrawl' ); ?>
				<?php else : ?>
					<span class="aumcrawl-dot is-ok"></span>
					<strong><?php esc_html_e( 'Rules in robots.txt', 'aumcrawl' ); ?></strong>
					<?php esc_html_e( 'in effect', 'aumcrawl' ); ?>
					<span class="aumcrawl-strength is-mid"><?php esc_html_e( 'Every crawler listed on this page honours these', 'aumcrawl' ); ?></span>
				<?php endif; ?>
			</li>
			<li>
				<span class="aumcrawl-dot <?php echo $aumcrawl_settings['hard_block'] ? 'is-ok' : 'is-off'; ?>"></span>
				<strong><?php esc_html_e( 'Turning blocked crawlers away at the door', 'aumcrawl' ); ?></strong>
				<?php echo $aumcrawl_settings['hard_block'] ? esc_html__( 'on', 'aumcrawl' ) : esc_html__( 'off', 'aumcrawl' ); ?>
				<span class="aumcrawl-strength is-strong"><?php esc_html_e( 'Strongest: the page is never served', 'aumcrawl' ); ?></span>
			</li>
			<li>
				<span class="aumcrawl-dot <?php echo $aumcrawl_settings['send_noai_header'] ? 'is-ok' : 'is-off'; ?>"></span>
				<strong><?php esc_html_e( 'A "please do not train on this" note on every page', 'aumcrawl' ); ?></strong>
				<?php echo $aumcrawl_settings['send_noai_header'] ? esc_html__( 'on', 'aumcrawl' ) : esc_html__( 'off', 'aumcrawl' ); ?>
				<span class="aumcrawl-strength is-weak"><?php esc_html_e( 'Weakest: a convention, widely ignored', 'aumcrawl' ); ?></span>
			</li>
			<li>
				<?php if ( 'off' === $aumcrawl_verify ) : ?>
					<span class="aumcrawl-dot is-off"></span>
					<strong><?php esc_html_e( 'Identity checks', 'aumcrawl' ); ?></strong>
					<?php esc_html_e( 'off', 'aumcrawl' ); ?>
				<?php elseif ( 'no-results' === $aumcrawl_verify ) : ?>
					<span class="aumcrawl-dot is-warn"></span>
					<strong><?php esc_html_e( 'Identity checks', 'aumcrawl' ); ?></strong>
					<?php esc_html_e( 'on, but reaching no verdict', 'aumcrawl' ); ?>
					<span class="aumcrawl-strength is-weak"><?php esc_html_e( 'The address connecting to this site belongs to a proxy or an internal network, so it says nothing about the crawler behind it. Sites on a CDN need the real visitor address passed through.', 'aumcrawl' ); ?></span>
				<?php elseif ( 'no-data' === $aumcrawl_verify ) : ?>
					<span class="aumcrawl-dot is-off"></span>
					<strong><?php esc_html_e( 'Identity checks', 'aumcrawl' ); ?></strong>
					<?php esc_html_e( 'on, nothing to check yet', 'aumcrawl' ); ?>
				<?php else : ?>
					<span class="aumcrawl-dot is-ok"></span>
					<strong><?php esc_html_e( 'Identity checks', 'aumcrawl' ); ?></strong>
					<?php esc_html_e( 'on and reaching verdicts', 'aumcrawl' ); ?>
				<?php endif; ?>
			</li>
		</ul>

		<p class="aml-field-hint">
			<?php esc_html_e( 'robots.txt is a request, not a fence. The crawlers listed here honour it, but nothing stops software that chooses not to. Refusing blocked crawlers outright has real teeth, and only against those that identify themselves honestly.', 'aumcrawl' ); ?>
		</p>
	</div>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="aumcrawl_save" />
		<?php wp_nonce_field( 'aumcrawl_save' ); ?>

		<?php
		$aumcrawl_groups = array(
			AumCrawl_Bots::GROUP_CITES     => array(
				'icon'  => 'dashicons-external',
				'title' => __( 'These send traffic back', 'aumcrawl' ),
				'desc'  => __( 'They read a page because a person asked a question, and the answer credits you. Blocking them costs visits.', 'aumcrawl' ),
			),
			AumCrawl_Bots::GROUP_TAKES     => array(
				'icon'  => 'dashicons-download',
				'title' => __( 'These take and give nothing back', 'aumcrawl' ),
				'desc'  => __( 'Training corpora and commercial datasets. Your content goes in; no link, no citation and no visitor comes out.', 'aumcrawl' ),
			),
			AumCrawl_Bots::GROUP_ESSENTIAL => array(
				'icon'  => 'dashicons-yes-alt',
				'title' => __( 'Recorded, never switchable', 'aumcrawl' ),
				'desc'  => __( 'Search engines and link previews. This plugin deliberately offers no switch for them: turning one off does real damage, and that belongs in your own robots.txt rather than behind a button you can hit by accident.', 'aumcrawl' ),
			),
		);

		foreach ( $aumcrawl_groups as $aumcrawl_group => $aumcrawl_meta ) :
			$aumcrawl_list = AumCrawl_Bots::in_group( $aumcrawl_group );
			if ( ! $aumcrawl_list ) {
				continue;
			}
			?>
			<?php
			// The unswitchable group is folded away by default: seven rows
			// nobody can act on would otherwise push the actual decisions off
			// the first screen.
			$aumcrawl_fold = AumCrawl_Bots::GROUP_ESSENTIAL === $aumcrawl_group;
			?>
			<div class="aml-card">
				<?php if ( $aumcrawl_fold ) : ?>
					<details class="aumcrawl-fold">
						<summary>
							<span class="dashicons <?php echo esc_attr( $aumcrawl_meta['icon'] ); ?>" aria-hidden="true"></span>
							<span class="aumcrawl-fold-title"><?php echo esc_html( $aumcrawl_meta['title'] ); ?></span>
							<span class="aumcrawl-fold-count">
								<?php
								printf(
									/* translators: %s: number of crawlers in the group. */
									esc_html__( '%s crawlers, recorded only', 'aumcrawl' ),
									esc_html( number_format_i18n( count( $aumcrawl_list ) ) )
								);
								?>
							</span>
						</summary>
						<p class="aml-card-desc aumcrawl-fold-desc"><?php echo esc_html( $aumcrawl_meta['desc'] ); ?></p>
						<table class="aumcrawl-table">
							<tbody>
							<?php foreach ( $aumcrawl_list as $aumcrawl_slug => $aumcrawl_bot ) : ?>
								<?php aumcrawl_render_row( $aumcrawl_slug, $aumcrawl_bot, $aumcrawl_stats, $aumcrawl_deferred ); ?>
							<?php endforeach; ?>
							</tbody>
						</table>
					</details>
				<?php else : ?>
					<h2 class="aml-card-h">
						<span class="dashicons <?php echo esc_attr( $aumcrawl_meta['icon'] ); ?>" aria-hidden="true"></span>
						<?php echo esc_html( $aumcrawl_meta['title'] ); ?>
					</h2>
					<p class="aml-card-desc"><?php echo esc_html( $aumcrawl_meta['desc'] ); ?></p>

					<table class="aumcrawl-table">
						<tbody>
						<?php foreach ( $aumcrawl_list as $aumcrawl_slug => $aumcrawl_bot ) : ?>
							<?php aumcrawl_render_row( $aumcrawl_slug, $aumcrawl_bot, $aumcrawl_stats, $aumcrawl_deferred ); ?>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

		<div class="aml-card">
			<h2 class="aml-card-h">
				<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
				<?php esc_html_e( 'Settings', 'aumcrawl' ); ?>
			</h2>

			<label class="aml-switch-row">
				<span class="aml-switch">
					<input type="checkbox" name="aumcrawl_noai" value="1" <?php checked( ! empty( $aumcrawl_settings['send_noai_header'] ) ); ?> />
					<span class="aml-track"></span>
				</span>
				<span class="aml-switch-label"><?php esc_html_e( 'Attach a "do not train on this" note to every page', 'aumcrawl' ); ?></span>
			</label>
			<p class="aml-field-hint">
				<?php esc_html_e( 'Adds an X-Robots-Tag: noai header, which also covers your images. It is a convention rather than a published standard, so some tools read it and many do not. It costs nothing to send, but treat it as a note left on the door rather than a lock.', 'aumcrawl' ); ?>
			</p>

			<label class="aml-switch-row">
				<span class="aml-switch">
					<input type="checkbox" name="aumcrawl_hard" value="1" <?php checked( ! empty( $aumcrawl_settings['hard_block'] ) ); ?> />
					<span class="aml-track"></span>
				</span>
				<span class="aml-switch-label"><?php esc_html_e( 'Turn blocked crawlers away instead of only asking them to stay away', 'aumcrawl' ); ?></span>
			</label>
			<p class="aml-field-hint">
				<?php esc_html_e( 'Without this, blocked crawlers are only asked to leave through robots.txt, and a crawler that ignores robots.txt still gets your pages. With it, they receive an error instead of the page. It only works against crawlers that say who they are.', 'aumcrawl' ); ?>
			</p>

			<label class="aml-switch-row">
				<span class="aml-switch">
					<input type="checkbox" name="aumcrawl_verify" value="1" <?php checked( ! empty( $aumcrawl_settings['verify_bots'] ) ); ?> />
					<span class="aml-track"></span>
				</span>
				<span class="aml-switch-label"><?php esc_html_e( 'Check that crawlers are who they claim to be', 'aumcrawl' ); ?></span>
			</label>
			<p class="aml-field-hint">
				<?php esc_html_e( 'The check is a DNS lookup against the hostname each operator publishes. The address is used during that one request and never stored; if the check fails, only a shortened network range is kept.', 'aumcrawl' ); ?>
			</p>

			<div class="aml-field aumcrawl-retain">
				<label class="aml-label" for="aumcrawl_retain"><?php esc_html_e( 'Keep records for', 'aumcrawl' ); ?></label>
				<input type="number" id="aumcrawl_retain" name="aumcrawl_retain" min="7" max="365"
					value="<?php echo esc_attr( $aumcrawl_settings['retain_days'] ); ?>" />
				<span class="aml-field-hint"><?php esc_html_e( 'days. Per-page detail is kept for seven days regardless.', 'aumcrawl' ); ?></span>
			</div>

			<p class="aml-actions-bar">
				<button type="submit" class="aml-btn aml-btn-primary"><?php esc_html_e( 'Save changes', 'aumcrawl' ); ?></button>
			</p>
		</div>
	</form>

	<div class="aml-card aumcrawl-danger">
		<h2 class="aml-card-h">
			<span class="dashicons dashicons-trash" aria-hidden="true"></span>
			<?php esc_html_e( 'Start the record over', 'aumcrawl' ); ?>
		</h2>
		<p class="aml-card-desc"><?php esc_html_e( 'Deletes every visit counted so far. Your crawler settings are kept.', 'aumcrawl' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="aumcrawl_clear" />
			<?php wp_nonce_field( 'aumcrawl_clear' ); ?>
			<button type="submit" class="aml-btn aml-btn-quiet"><?php esc_html_e( 'Clear all records', 'aumcrawl' ); ?></button>
		</form>
	</div>

	<?php if ( $aumcrawl_pages || $aumcrawl_suspects ) : ?>
		<div class="aml-card">
			<h2 class="aml-card-h">
				<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
				<?php esc_html_e( 'Activity', 'aumcrawl' ); ?>
			</h2>

			<?php if ( $aumcrawl_pages ) : ?>
				<h3 class="aumcrawl-sub"><?php esc_html_e( 'Most crawled pages', 'aumcrawl' ); ?></h3>
				<table class="aumcrawl-table aumcrawl-table-plain">
					<tbody>
					<?php foreach ( $aumcrawl_pages as $aumcrawl_page ) : ?>
						<tr>
							<td><code><?php echo esc_html( $aumcrawl_page['url'] ); ?></code></td>
							<td class="aumcrawl-num"><?php echo esc_html( number_format_i18n( (int) $aumcrawl_page['hits'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( $aumcrawl_suspects ) : ?>
				<h3 class="aumcrawl-sub"><?php esc_html_e( 'Failed the identity check', 'aumcrawl' ); ?></h3>
				<p class="aml-card-desc"><?php esc_html_e( 'These ranges sent a crawler name that their published hostname does not back up. Addresses are shortened before being stored.', 'aumcrawl' ); ?></p>
				<table class="aumcrawl-table aumcrawl-table-plain">
					<tbody>
					<?php foreach ( $aumcrawl_suspects as $aumcrawl_suspect ) : ?>
						<tr>
							<td><code><?php echo esc_html( $aumcrawl_suspect['net'] ); ?></code></td>
							<td><?php echo esc_html( $aumcrawl_suspect['bot'] ); ?></td>
							<td class="aumcrawl-num"><?php echo esc_html( number_format_i18n( (int) $aumcrawl_suspect['hits'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
	<?php endif; ?>
	<?php
	// One line pointing at the rest of the AumCreate ecosystem; plain text and a
	// link, nothing else.
	$aumcrawl_eco_url = 'https://aumcreate.com/?utm_source=plugin&utm_medium=aumcrawl&utm_campaign=settings';
	?>
	<p class="aum-ecosystem-note" style="margin:24px 0 0;color:#646970;font-size:12px">
		<?php
		printf(
			/* translators: %s: link to aumcreate.com */
			esc_html__( 'Part of the AumCreate ecosystem — themes and templates built around it. %s', 'aumcrawl' ),
			'<a href="' . esc_url( $aumcrawl_eco_url ) . '" target="_blank" rel="noopener">aumcreate.com</a>'
		);
		?>
	</p>
</div>
