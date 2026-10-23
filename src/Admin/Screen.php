<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Admin;

use MeshResearch\DomainMapping\MappingSummary;
use MeshResearch\DomainMapping\Plugin;
use MeshResearch\DomainMapping\Site;

/**
 * Network Admin → Settings → Domain Mapping.
 *
 * Every entry point re-checks super-admin status; the menu entry is not
 * even registered for anyone else.
 */
final class Screen {
	public const SLUG = 'commons-domain-mapping';

	private const ACTIONS = array(
		'cdm_add'          => 'add',
		'cdm_remove'       => 'remove',
		'cdm_make_primary' => 'make_primary',
	);

	/** Hook suffix returned by add_submenu_page(), used to scope asset loading. */
	private string $hook = '';

	public function __construct(
		private readonly Plugin $plugin,
		private readonly FormHandler $handler,
	) {}

	public function register(): void {
		add_action( 'network_admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		foreach ( array_keys( self::ACTIONS ) as $action ) {
			add_action( 'admin_post_' . $action, array( $this, 'handle_post' ) );
		}
	}

	public function add_menu(): void {
		if ( ! $this->plugin->access()->granted() ) {
			return;
		}
		$hook = add_submenu_page(
			'settings.php',
			__( 'Domain Mapping', 'commons-domain-mapping' ),
			__( 'Domain Mapping', 'commons-domain-mapping' ),
			'manage_network',
			self::SLUG,
			array( $this, 'render' )
		);
		$this->hook = is_string( $hook ) ? $hook : '';
	}

	public function enqueue( string $hook_suffix ): void {
		if ( '' === $this->hook || $hook_suffix !== $this->hook ) {
			return;
		}
		$base = plugin_dir_url( COMMONS_DOMAIN_MAPPING_FILE ) . 'assets/';
		$dir  = COMMONS_DOMAIN_MAPPING_DIR . '/assets/';

		wp_enqueue_style( 'cdm-admin', $base . 'admin.css', array( 'dashicons' ), (string) filemtime( $dir . 'admin.css' ) );
		wp_enqueue_script( 'cdm-combobox-logic', $base . 'combobox-logic.js', array(), (string) filemtime( $dir . 'combobox-logic.js' ), true );
		wp_enqueue_script( 'cdm-admin', $base . 'admin.js', array( 'cdm-combobox-logic' ), (string) filemtime( $dir . 'admin.js' ), true );
		wp_add_inline_script(
			'cdm-admin',
			'window.cdmSettings = ' . wp_json_encode(
				array(
					'restUrl' => rest_url( Plugin::REST_NAMESPACE . '/sites' ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
					'i18n'    => array(
						'searching'     => __( 'Searching…', 'commons-domain-mapping' ),
						'searchFailed'  => __( 'Search is unavailable. Enter the site ID instead.', 'commons-domain-mapping' ),
						'selected'      => __( 'Selected %s.', 'commons-domain-mapping' ),
						'cleared'       => __( 'Site selection cleared.', 'commons-domain-mapping' ),
						'confirmRemove' => __( 'Remove the mapping for %s? Visitors using that hostname will no longer reach the site until it is mapped again.', 'commons-domain-mapping' ),
						'confirmPrimary' => __( 'Make %s the primary address? The site’s links will be generated with this hostname from now on.', 'commons-domain-mapping' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/** admin-post.php entry point for all three forms. */
	public function handle_post(): void {
		$action = isset( $_POST['action'] ) ? sanitize_key( (string) wp_unslash( $_POST['action'] ) ) : '';
		$method = self::ACTIONS[ $action ] ?? null;

		if ( null === $method || ! $this->plugin->access()->granted() ) {
			wp_die( esc_html__( 'Only super administrators can manage domain mappings.', 'commons-domain-mapping' ), 403 );
		}
		check_admin_referer( $action, '_cdm_nonce' );

		try {
			$outcome = $this->handler->{$method}( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		} catch ( \MeshResearch\DomainMapping\AccessDenied $e ) {
			wp_die( esc_html( $e->getMessage() ), 403 );
		}

		set_transient( $this->notice_key(), array( 'status' => $outcome->status, 'message' => $outcome->message ), MINUTE_IN_SECONDS * 5 );
		wp_safe_redirect( $this->url() );
		exit;
	}

	public function url(): string {
		return network_admin_url( 'settings.php?page=' . self::SLUG );
	}

	public function render(): void {
		if ( ! $this->plugin->access()->granted() ) {
			wp_die( esc_html__( 'Only super administrators can manage domain mappings.', 'commons-domain-mapping' ), 403 );
		}

		$store     = $this->plugin->store();
		$available = $store->is_available();
		$service   = $this->plugin->service();
		$rows      = $available ? $service->all() : array();
		$gaps      = $available ? $service->unmapped_sites() : array();
		$networks = $this->plugin->sites()->network_domains();
		$notice   = get_transient( $this->notice_key() );
		if ( is_array( $notice ) ) {
			delete_transient( $this->notice_key() );
		}
		?>
		<div class="wrap cdm-wrap">
			<h1><?php esc_html_e( 'Domain Mapping', 'commons-domain-mapping' ); ?></h1>
			<p class="cdm-lede">
				<?php esc_html_e( 'Point a custom hostname at a site in the network. A mapping lets the network resolve the hostname and issue login cookies for it; DNS and TLS certificates are handled separately.', 'commons-domain-mapping' ); ?>
			</p>

			<?php if ( is_array( $notice ) ) : ?>
				<?php $is_error = Outcome::ERROR === $notice['status']; ?>
				<div id="cdm-notice" class="notice inline <?php echo $is_error ? 'notice-error' : 'notice-success'; ?> cdm-notice" role="<?php echo $is_error ? 'alert' : 'status'; ?>" tabindex="-1">
					<p><?php echo esc_html( (string) $notice['message'] ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( ! $available ) : ?>
				<div class="notice inline notice-error cdm-notice" role="alert">
					<p>
						<?php
						printf(
							/* translators: %s: database table name */
							esc_html__( 'The %s table does not exist, so mappings cannot be managed. This plugin never creates tables; the table is provisioned with the network.', 'commons-domain-mapping' ),
							'<code>' . esc_html( $store->table() ) . '</code>'
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<?php if ( array() !== $gaps ) : ?>
					<?php $this->render_attention( $gaps ); ?>
				<?php endif; ?>

				<div class="cdm-layout">
					<?php $this->render_add_form(); ?>
					<?php $this->render_table( $rows ); ?>
				</div>
			<?php endif; ?>

			<section class="cdm-card cdm-card--muted" aria-labelledby="cdm-how-heading">
				<h2 id="cdm-how-heading" class="cdm-card__title"><?php esc_html_e( 'How this works', 'commons-domain-mapping' ); ?></h2>
				<ol class="cdm-steps">
					<li><?php esc_html_e( 'Point the hostname’s DNS at the network and install a certificate for it.', 'commons-domain-mapping' ); ?></li>
					<li><?php esc_html_e( 'Add a mapping here. The network’s sunrise.php looks the hostname up on every request, loads the right site, and scopes cookies to the hostname so logins work.', 'commons-domain-mapping' ); ?></li>
					<li><?php esc_html_e( 'Make it the primary address when the site should generate its links with the new hostname. Other mapped hostnames keep working as aliases.', 'commons-domain-mapping' ); ?></li>
				</ol>
				<?php if ( array() !== $networks ) : ?>
					<p class="cdm-muted">
						<?php
						printf(
							/* translators: %s: comma-separated list of network domains */
							esc_html__( 'Hostnames under %s already resolve without a mapping; only addresses outside those domains need one.', 'commons-domain-mapping' ),
							'<strong>' . esc_html( implode( ', ', $networks ) ) . '</strong>'
						);
						?>
					</p>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}

	/** @param list<Site> $gaps */
	private function render_attention( array $gaps ): void {
		?>
		<section class="cdm-card cdm-card--attention" aria-labelledby="cdm-attention-heading">
			<h2 id="cdm-attention-heading" class="cdm-card__title">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<?php
				printf(
					/* translators: %d: number of sites */
					esc_html( _n( '%d site needs a mapping', '%d sites need a mapping', count( $gaps ), 'commons-domain-mapping' ) ),
					count( $gaps )
				);
				?>
			</h2>
			<p><?php esc_html_e( 'These sites use an address outside every network domain but have no mapping row, so their login cookies are issued for the wrong domain and sign-in loops. Mapping the address fixes it.', 'commons-domain-mapping' ); ?></p>
			<ul class="cdm-attention-list">
				<?php foreach ( $gaps as $site ) : ?>
					<li class="cdm-attention-item">
						<div class="cdm-attention-item__site">
							<strong><?php echo esc_html( '' !== $site->name ? $site->name : $site->address() ); ?></strong>
							<span class="cdm-muted"><code class="cdm-host"><?php echo esc_html( $site->domain ); ?></code> · <?php printf( esc_html__( 'site %d', 'commons-domain-mapping' ), (int) $site->id ); ?></span>
						</div>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cdm-inline-form">
							<?php wp_nonce_field( 'cdm_add', '_cdm_nonce' ); ?>
							<input type="hidden" name="action" value="cdm_add">
							<input type="hidden" name="hostname" value="<?php echo esc_attr( $site->domain ); ?>">
							<input type="hidden" name="blog_id" value="<?php echo esc_attr( (string) $site->id ); ?>">
							<button type="submit" class="button button-secondary">
								<?php
								printf(
									/* translators: %s: hostname */
									esc_html__( 'Map %s', 'commons-domain-mapping' ),
									esc_html( $site->domain )
								);
								?>
							</button>
						</form>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
		<?php
	}

	private function render_add_form(): void {
		?>
		<section class="cdm-card" aria-labelledby="cdm-add-heading">
			<h2 id="cdm-add-heading" class="cdm-card__title"><?php esc_html_e( 'Add a mapping', 'commons-domain-mapping' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cdm-form" id="cdm-add-form">
				<?php wp_nonce_field( 'cdm_add', '_cdm_nonce' ); ?>
				<input type="hidden" name="action" value="cdm_add">

				<div class="cdm-field">
					<label for="cdm-hostname" class="cdm-label"><?php esc_html_e( 'Hostname', 'commons-domain-mapping' ); ?></label>
					<input type="text" id="cdm-hostname" name="hostname" class="cdm-input" required autocomplete="off" autocapitalize="none" spellcheck="false" inputmode="url" aria-describedby="cdm-hostname-help">
					<p id="cdm-hostname-help" class="cdm-help"><?php esc_html_e( 'The bare hostname, for example poetry.rcah.msu.edu. Schemes, paths and trailing slashes are stripped.', 'commons-domain-mapping' ); ?></p>
				</div>

				<div class="cdm-field cdm-combobox" data-cdm-combobox>
					<label for="cdm-site-search" class="cdm-label cdm-js-only"><?php esc_html_e( 'Site', 'commons-domain-mapping' ); ?></label>
					<div class="cdm-combobox__control cdm-js-only">
						<input type="text" id="cdm-site-search" class="cdm-input cdm-combobox__input" role="combobox" aria-expanded="false" aria-controls="cdm-site-listbox" aria-autocomplete="list" aria-haspopup="listbox" aria-describedby="cdm-site-help" autocomplete="off" spellcheck="false">
						<ul id="cdm-site-listbox" class="cdm-combobox__listbox" role="listbox" aria-label="<?php esc_attr_e( 'Matching sites', 'commons-domain-mapping' ); ?>" hidden></ul>
					</div>
					<p id="cdm-site-help" class="cdm-help cdm-js-only"><?php esc_html_e( 'Type part of the site’s address or its ID, then choose a result.', 'commons-domain-mapping' ); ?></p>
					<div id="cdm-site-status" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>

					<div class="cdm-selected" id="cdm-selected" hidden>
						<div class="cdm-selected__body">
							<span class="cdm-selected__eyebrow"><?php esc_html_e( 'Selected site', 'commons-domain-mapping' ); ?></span>
							<strong class="cdm-selected__name" id="cdm-selected-name"></strong>
							<span class="cdm-muted" id="cdm-selected-meta"></span>
						</div>
						<button type="button" class="button-link cdm-selected__change" id="cdm-selected-change"><?php esc_html_e( 'Change', 'commons-domain-mapping' ); ?></button>
					</div>

					<div class="cdm-id-field" id="cdm-id-field">
						<label for="cdm-blog-id" class="cdm-label"><?php esc_html_e( 'Site ID', 'commons-domain-mapping' ); ?></label>
						<input type="number" id="cdm-blog-id" name="blog_id" class="cdm-input cdm-input--short" min="1" step="1" inputmode="numeric" aria-describedby="cdm-blog-id-help">
						<p id="cdm-blog-id-help" class="cdm-help"><?php esc_html_e( 'The numeric ID shown in Network Admin → Sites.', 'commons-domain-mapping' ); ?></p>
					</div>
					<p class="cdm-js-only cdm-help">
						<button type="button" class="button-link" id="cdm-toggle-id"><?php esc_html_e( 'Enter a site ID instead', 'commons-domain-mapping' ); ?></button>
					</p>
				</div>

				<div class="cdm-field cdm-field--checkbox">
					<input type="checkbox" id="cdm-make-primary" name="make_primary" value="1" aria-describedby="cdm-make-primary-help">
					<label for="cdm-make-primary"><?php esc_html_e( 'Make this the site’s primary address', 'commons-domain-mapping' ); ?></label>
					<p id="cdm-make-primary-help" class="cdm-help"><?php esc_html_e( 'Updates the site’s address and home URL so its links use the new hostname. Leave unchecked to add an alias only.', 'commons-domain-mapping' ); ?></p>
				</div>

				<div class="cdm-actions">
					<button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Add mapping', 'commons-domain-mapping' ); ?></button>
				</div>
			</form>
		</section>
		<?php
	}

	/** @param list<MappingSummary> $rows */
	private function render_table( array $rows ): void {
		?>
		<section class="cdm-card cdm-card--table" aria-labelledby="cdm-list-heading">
			<h2 id="cdm-list-heading" class="cdm-card__title">
				<?php esc_html_e( 'Mapped hostnames', 'commons-domain-mapping' ); ?>
				<span class="cdm-count" aria-hidden="true"><?php echo esc_html( (string) count( $rows ) ); ?></span>
				<span class="screen-reader-text">
					<?php
					printf(
						/* translators: %d: number of mappings */
						esc_html( _n( '(%d mapping)', '(%d mappings)', count( $rows ), 'commons-domain-mapping' ) ),
						count( $rows )
					);
					?>
				</span>
			</h2>

			<?php if ( array() === $rows ) : ?>
				<div class="cdm-empty">
					<span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span>
					<p><strong><?php esc_html_e( 'No hostnames are mapped yet.', 'commons-domain-mapping' ); ?></strong></p>
					<p class="cdm-muted"><?php esc_html_e( 'Add one with the form to give a site a custom address.', 'commons-domain-mapping' ); ?></p>
				</div>
			<?php else : ?>
				<div class="cdm-table-scroll" role="region" aria-label="<?php esc_attr_e( 'Mapped hostnames table, scrolls horizontally', 'commons-domain-mapping' ); ?>" tabindex="0">
					<table class="cdm-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Hostname', 'commons-domain-mapping' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Site', 'commons-domain-mapping' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'commons-domain-mapping' ); ?></th>
								<th scope="col"><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'commons-domain-mapping' ); ?></span></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<?php $this->render_row( $row ); ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private function render_row( MappingSummary $row ): void {
		$mapping = $row->mapping;
		$site    = $row->site;
		?>
		<tr>
			<th scope="row" class="cdm-table__host">
				<a href="<?php echo esc_url( 'https://' . $mapping->domain . '/' ); ?>" class="cdm-host-link" target="_blank" rel="noopener">
					<code class="cdm-host"><?php echo esc_html( $mapping->domain ); ?></code>
					<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'commons-domain-mapping' ); ?></span>
				</a>
			</th>
			<td class="cdm-table__site">
				<?php if ( null !== $site ) : ?>
					<a href="<?php echo esc_url( get_admin_url( $site->id ) ); ?>" class="cdm-site-name"><?php echo esc_html( '' !== $site->name ? $site->name : $site->address() ); ?></a>
					<span class="cdm-muted cdm-site-meta"><?php echo esc_html( $site->address() ); ?> · <?php printf( esc_html__( 'site %d', 'commons-domain-mapping' ), (int) $site->id ); ?></span>
				<?php else : ?>
					<span class="cdm-site-name"><?php esc_html_e( 'Deleted site', 'commons-domain-mapping' ); ?></span>
					<span class="cdm-muted cdm-site-meta"><?php printf( esc_html__( 'site %d no longer exists', 'commons-domain-mapping' ), (int) $mapping->blog_id ); ?></span>
				<?php endif; ?>
			</td>
			<td class="cdm-table__status">
				<?php if ( null === $site ) : ?>
					<span class="cdm-pill cdm-pill--orphan"><span class="dashicons dashicons-dismiss" aria-hidden="true"></span><?php esc_html_e( 'Orphaned', 'commons-domain-mapping' ); ?></span>
				<?php elseif ( $row->is_primary ) : ?>
					<span class="cdm-pill cdm-pill--primary"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span><?php esc_html_e( 'Primary', 'commons-domain-mapping' ); ?></span>
				<?php else : ?>
					<span class="cdm-pill cdm-pill--alias"><span class="dashicons dashicons-randomize" aria-hidden="true"></span><?php esc_html_e( 'Alias', 'commons-domain-mapping' ); ?></span>
				<?php endif; ?>
			</td>
			<td class="cdm-table__actions">
				<?php if ( null !== $site && ! $row->is_primary ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cdm-inline-form" data-cdm-confirm="primary" data-cdm-host="<?php echo esc_attr( $mapping->domain ); ?>">
						<?php wp_nonce_field( 'cdm_make_primary', '_cdm_nonce' ); ?>
						<input type="hidden" name="action" value="cdm_make_primary">
						<input type="hidden" name="mapping_id" value="<?php echo esc_attr( (string) $mapping->id ); ?>">
						<button type="submit" class="button button-small">
							<?php esc_html_e( 'Make primary', 'commons-domain-mapping' ); ?>
							<span class="screen-reader-text"><?php echo esc_html( $mapping->domain ); ?></span>
						</button>
					</form>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="cdm-inline-form" data-cdm-confirm="remove" data-cdm-host="<?php echo esc_attr( $mapping->domain ); ?>">
					<?php wp_nonce_field( 'cdm_remove', '_cdm_nonce' ); ?>
					<input type="hidden" name="action" value="cdm_remove">
					<input type="hidden" name="mapping_id" value="<?php echo esc_attr( (string) $mapping->id ); ?>">
					<button type="submit" class="button button-small cdm-button--danger">
						<?php esc_html_e( 'Remove', 'commons-domain-mapping' ); ?>
						<span class="screen-reader-text"><?php echo esc_html( $mapping->domain ); ?></span>
					</button>
				</form>
			</td>
		</tr>
		<?php
	}

	private function notice_key(): string {
		return 'cdm_notice_' . get_current_user_id();
	}
}
