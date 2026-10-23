<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Rest;

use MeshResearch\DomainMapping\Admin\Access;
use MeshResearch\DomainMapping\Site;
use MeshResearch\DomainMapping\Storage\SiteDirectory;

/**
 * Site search behind the admin combobox.
 */
final class SitesController {
	public const LIMIT = 10;

	public function __construct(
		private readonly SiteDirectory $sites,
		private readonly Access $access,
	) {}

	/**
	 * @throws \MeshResearch\DomainMapping\AccessDenied
	 * @return list<array{id:int, name:string, address:string, url:string, domain:string, path:string, network_id:int}>
	 */
	public function search( string $term ): array {
		$this->access->assert();

		$term = strtolower( trim( $term ) );
		$term = preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $term ) ?? $term;
		$term = trim( $term, '/' );

		/** @var array<int, Site> $found */
		$found = array();
		if ( ctype_digit( $term ) ) {
			$by_id = $this->sites->find( (int) $term );
			if ( null !== $by_id ) {
				$found[ $by_id->id ] = $by_id;
			}
		}
		foreach ( $this->sites->search( $term, self::LIMIT ) as $site ) {
			$found[ $site->id ] = $site;
		}

		return array_map(
			fn( Site $site ) => $site->to_array(),
			array_slice( array_values( $found ), 0, self::LIMIT )
		);
	}
}
