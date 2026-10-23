<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Storage;

use MeshResearch\DomainMapping\Site;

/**
 * Sites and networks through the WordPress multisite API.
 */
final class WpSiteDirectory implements SiteDirectory {
	/** Upper bound on the "needs attention" scan so a pathological network cannot stall the screen. */
	private const OUTSIDE_LIMIT = 200;

	public function __construct( private readonly \wpdb $db ) {}

	public function find( int $id ): ?Site {
		$site = $id > 0 ? get_site( $id ) : null;
		return $site instanceof \WP_Site ? $this->from_wp_site( $site ) : null;
	}

	public function search( string $term, int $limit = 10 ): array {
		$args = array(
			'number'  => $limit,
			'deleted' => 0,
		);
		if ( '' === $term ) {
			$args['orderby'] = 'registered';
			$args['order']   = 'DESC';
		} else {
			$args['search']         = $term;
			$args['search_columns'] = array( 'domain', 'path' );
			$args['orderby']        = 'domain';
			$args['order']          = 'ASC';
		}
		$sites = get_sites( $args );
		return array_values( array_map( array( $this, 'from_wp_site' ), is_array( $sites ) ? $sites : array() ) );
	}

	public function network_domains(): array {
		$domains = array();
		foreach ( get_networks() as $network ) {
			if ( $network instanceof \WP_Network && '' !== $network->domain ) {
				$domains[] = strtolower( $network->domain );
			}
		}
		return array_values( array_unique( $domains ) );
	}

	public function sites_outside_networks(): array {
		$conditions = array( 'deleted = 0' );
		foreach ( $this->network_domains() as $domain ) {
			$conditions[] = $this->db->prepare( '(domain <> %s AND domain NOT LIKE %s)', $domain, '%.' . $this->db->esc_like( $domain ) );
		}
		$where = implode( ' AND ', $conditions );
		$limit = self::OUTSIDE_LIMIT;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $this->db->get_col( "SELECT blog_id FROM {$this->db->blogs} WHERE {$where} ORDER BY domain ASC LIMIT {$limit}" );

		$sites = array();
		foreach ( (array) $ids as $id ) {
			$site = $this->find( (int) $id );
			if ( null !== $site ) {
				$sites[] = $site;
			}
		}
		return $sites;
	}

	/**
	 * Mirrors what Network Admin → Edit Site does, keeping any core
	 * sub-path (e.g. Bedrock's /wp) that siteurl has relative to home.
	 */
	public function set_primary_address( int $id, string $domain ): void {
		$current_home    = (string) get_blog_option( $id, 'home' );
		$current_siteurl = (string) get_blog_option( $id, 'siteurl' );

		$home_path    = rtrim( (string) wp_parse_url( $current_home, PHP_URL_PATH ), '/' );
		$siteurl_path = rtrim( (string) wp_parse_url( $current_siteurl, PHP_URL_PATH ), '/' );
		$core_suffix  = str_starts_with( $siteurl_path, $home_path ) ? substr( $siteurl_path, strlen( $home_path ) ) : '';

		update_blog_details(
			$id,
			array(
				'domain' => $domain,
				'path'   => '/',
			)
		);
		update_blog_option( $id, 'home', 'https://' . $domain );
		update_blog_option( $id, 'siteurl', 'https://' . $domain . $core_suffix );
		clean_blog_cache( $id );
	}

	private function from_wp_site( \WP_Site $site ): Site {
		return new Site(
			(int) $site->blog_id,
			strtolower( $site->domain ),
			$site->path,
			(string) get_blog_option( (int) $site->blog_id, 'blogname' ),
			(int) $site->network_id
		);
	}
}
