<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Storage;

use MeshResearch\DomainMapping\Site;

/**
 * Read/write boundary for sites and networks.
 */
interface SiteDirectory {
	public function find( int $id ): ?Site;

	/**
	 * Sites whose address contains the term. An empty term returns the
	 * most recently created sites.
	 *
	 * @return list<Site>
	 */
	public function search( string $term, int $limit = 10 ): array;

	/** @return list<string> */
	public function network_domains(): array;

	/**
	 * Sites whose primary domain is neither a network domain nor a
	 * subdomain of one.
	 *
	 * @return list<Site>
	 */
	public function sites_outside_networks(): array;

	/** Make $domain the site's primary address (wp_blogs + home/siteurl). */
	public function set_primary_address( int $id, string $domain ): void;
}
