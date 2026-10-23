<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Storage;

use MeshResearch\DomainMapping\Mapping;

/**
 * Persistence boundary for the wp_domain_mapping table (read and row
 * writes only; the plugin never creates, alters or drops tables).
 */
interface MappingStore {
	/**
	 * Whether the wp_domain_mapping table exists. The plugin never creates
	 * or alters tables; the table is provisioned with the network.
	 */
	public function is_available(): bool;

	/** @return list<Mapping> */
	public function all(): array;

	public function find( int $id ): ?Mapping;

	/** Exact match on the stored (normalised) hostname. */
	public function find_by_domain( string $domain ): ?Mapping;

	/** @return list<Mapping> */
	public function for_site( int $blog_id ): array;

	public function insert( int $blog_id, string $domain, bool $active = true ): Mapping;

	public function delete( int $id ): bool;
}
