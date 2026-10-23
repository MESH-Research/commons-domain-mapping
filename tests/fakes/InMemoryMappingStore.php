<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Tests\Fakes;

use MeshResearch\DomainMapping\Mapping;
use MeshResearch\DomainMapping\Storage\MappingStore;

final class InMemoryMappingStore implements MappingStore {
	/** @var array<int, Mapping> */
	private array $rows = array();
	private int $next_id = 1;
	public bool $available = true;

	public function seed( int $blog_id, string $domain, bool $active = true ): Mapping {
		return $this->insert( $blog_id, $domain, $active );
	}

	public function is_available(): bool {
		return $this->available;
	}

	public function all(): array {
		return array_values( $this->rows );
	}

	public function find( int $id ): ?Mapping {
		return $this->rows[ $id ] ?? null;
	}

	public function find_by_domain( string $domain ): ?Mapping {
		foreach ( $this->rows as $row ) {
			if ( $row->domain === $domain ) {
				return $row;
			}
		}
		return null;
	}

	public function for_site( int $blog_id ): array {
		return array_values( array_filter( $this->rows, fn( Mapping $m ) => $m->blog_id === $blog_id ) );
	}

	public function insert( int $blog_id, string $domain, bool $active = true ): Mapping {
		$mapping                   = new Mapping( $this->next_id++, $blog_id, $domain, $active );
		$this->rows[ $mapping->id ] = $mapping;
		return $mapping;
	}

	public function delete( int $id ): bool {
		if ( ! isset( $this->rows[ $id ] ) ) {
			return false;
		}
		unset( $this->rows[ $id ] );
		return true;
	}
}
