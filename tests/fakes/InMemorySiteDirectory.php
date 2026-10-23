<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Tests\Fakes;

use MeshResearch\DomainMapping\Hostname;
use MeshResearch\DomainMapping\Site;
use MeshResearch\DomainMapping\Storage\SiteDirectory;

final class InMemorySiteDirectory implements SiteDirectory {
	/** @var array<int, Site> */
	private array $sites = array();
	/** @var list<string> */
	private array $networks = array();
	/** @var array<int, array{domain: string, home: string}> */
	public array $primary_changes = array();

	public function add_network( string $domain ): void {
		$this->networks[] = $domain;
	}

	public function add_site( int $id, string $domain, string $path = '/', string $name = '', int $network_id = 1 ): Site {
		$site               = new Site( $id, $domain, $path, $name, $network_id );
		$this->sites[ $id ] = $site;
		return $site;
	}

	public function find( int $id ): ?Site {
		return $this->sites[ $id ] ?? null;
	}

	public function search( string $term, int $limit = 10 ): array {
		$term  = strtolower( trim( $term ) );
		$found = array();
		foreach ( $this->sites as $site ) {
			if ( '' === $term || str_contains( strtolower( $site->domain . $site->path ), $term ) ) {
				$found[] = $site;
			}
		}
		return array_slice( $found, 0, $limit );
	}

	public function network_domains(): array {
		return $this->networks;
	}

	public function sites_outside_networks(): array {
		$outside = array();
		foreach ( $this->sites as $site ) {
			$inside = false;
			foreach ( $this->networks as $network ) {
				if ( Hostname::is_within( $site->domain, $network ) ) {
					$inside = true;
					break;
				}
			}
			if ( ! $inside ) {
				$outside[] = $site;
			}
		}
		return $outside;
	}

	public function set_primary_address( int $id, string $domain ): void {
		$site = $this->sites[ $id ] ?? throw new \RuntimeException( "No site $id" );
		$this->sites[ $id ]           = new Site( $id, $domain, '/', $site->name, $site->network_id );
		$this->primary_changes[ $id ] = array(
			'domain' => $domain,
			'home'   => 'https://' . $domain,
		);
	}
}
