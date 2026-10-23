<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping;

use MeshResearch\DomainMapping\Storage\MappingStore;
use MeshResearch\DomainMapping\Storage\SiteDirectory;

/**
 * The use cases behind the admin screen and the CLI command.
 */
final class MappingService {
	public function __construct(
		private readonly MappingStore $store,
		private readonly SiteDirectory $sites,
	) {}

	/** @return list<MappingSummary> ordered by hostname */
	public function all(): array {
		$mappings = $this->store->all();
		usort( $mappings, fn( Mapping $a, Mapping $b ) => strcmp( $a->domain, $b->domain ) );

		$rows = array();
		foreach ( $mappings as $mapping ) {
			$site   = $this->sites->find( $mapping->blog_id );
			$rows[] = new MappingSummary(
				$mapping,
				$site,
				null !== $site && strtolower( $site->domain ) === $mapping->domain
			);
		}
		return $rows;
	}

	/**
	 * @throws InvalidHostname|UnknownSite|MappingConflict
	 */
	public function add( string $hostname, int $blog_id, bool $make_primary = false ): Mapping {
		$hostname = Hostname::normalize( $hostname );
		$site     = $this->require_site( $blog_id );

		$existing = $this->store->find_by_domain( $hostname );
		if ( null !== $existing ) {
			$owner = $this->sites->find( $existing->blog_id );
			throw new MappingConflict(
				sprintf(
					/* translators: 1: hostname, 2: site description */
					__( '%1$s is already mapped to %2$s.', 'commons-domain-mapping' ),
					$hostname,
					null !== $owner ? $this->describe( $owner ) : sprintf( __( 'site %d (deleted)', 'commons-domain-mapping' ), $existing->blog_id )
				),
				$existing
			);
		}

		$mapping = $this->store->insert( $blog_id, $hostname );
		if ( $make_primary ) {
			$this->sites->set_primary_address( $site->id, $hostname );
		}
		return $mapping;
	}

	/** @throws MappingNotFound */
	public function remove( int $id ): Mapping {
		$mapping = $this->require_mapping( $id );
		$this->store->delete( $mapping->id );
		return $mapping;
	}

	/** @throws MappingNotFound|UnknownSite */
	public function make_primary( int $id ): Mapping {
		$mapping = $this->require_mapping( $id );
		$site    = $this->require_site( $mapping->blog_id );
		$this->sites->set_primary_address( $site->id, $mapping->domain );
		return $mapping;
	}

	/**
	 * Sites whose primary address is outside every network and has no
	 * mapping row pointing at them: the configuration that breaks login
	 * cookies.
	 *
	 * @return list<Site>
	 */
	public function unmapped_sites(): array {
		$gaps = array();
		foreach ( $this->sites->sites_outside_networks() as $site ) {
			$mapping = $this->store->find_by_domain( strtolower( $site->domain ) );
			if ( null === $mapping || $mapping->blog_id !== $site->id ) {
				$gaps[] = $site;
			}
		}
		return $gaps;
	}

	/** Human-readable site label for notices. */
	public function describe( Site $site ): string {
		return '' !== $site->name
			? sprintf( '%s (%s, site %d)', $site->name, $site->address(), $site->id )
			: sprintf( '%s (site %d)', $site->address(), $site->id );
	}

	public function site( int $blog_id ): ?Site {
		return $this->sites->find( $blog_id );
	}

	/** @throws UnknownSite */
	private function require_site( int $blog_id ): Site {
		return $this->sites->find( $blog_id )
			?? throw new UnknownSite( sprintf( __( 'There is no site with ID %d.', 'commons-domain-mapping' ), $blog_id ) );
	}

	/** @throws MappingNotFound */
	private function require_mapping( int $id ): Mapping {
		return $this->store->find( $id )
			?? throw new MappingNotFound( __( 'That mapping no longer exists.', 'commons-domain-mapping' ) );
	}
}
