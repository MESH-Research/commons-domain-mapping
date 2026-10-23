<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Storage;

use MeshResearch\DomainMapping\Mapping;

/**
 * wp_domain_mapping via $wpdb. The table follows the WPMU Domain Mapping
 * schema (id, blog_id, domain, active) and is what sunrise.php reads. This
 * class only reads rows and inserts/deletes them: it never creates, alters
 * or drops the table.
 */
final class WpdbMappingStore implements MappingStore {
	public function __construct( private readonly \wpdb $db ) {}

	public function table(): string {
		return $this->db->base_prefix . 'domain_mapping';
	}

	public function is_available(): bool {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $this->db->get_var( $this->db->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}


	public function all(): array {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $this->db->get_results( "SELECT id, blog_id, domain, active FROM {$table} ORDER BY domain ASC", ARRAY_A ) ?: array();
		return array_map( array( $this, 'hydrate' ), $rows );
	}

	public function find( int $id ): ?Mapping {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $this->db->get_row( $this->db->prepare( "SELECT id, blog_id, domain, active FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return $row ? $this->hydrate( $row ) : null;
	}

	public function find_by_domain( string $domain ): ?Mapping {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $this->db->get_row( $this->db->prepare( "SELECT id, blog_id, domain, active FROM {$table} WHERE domain = %s ORDER BY id ASC LIMIT 1", $domain ), ARRAY_A );
		return $row ? $this->hydrate( $row ) : null;
	}

	public function for_site( int $blog_id ): array {
		$table = $this->table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $this->db->get_results( $this->db->prepare( "SELECT id, blog_id, domain, active FROM {$table} WHERE blog_id = %d ORDER BY domain ASC", $blog_id ), ARRAY_A ) ?: array();
		return array_map( array( $this, 'hydrate' ), $rows );
	}

	public function insert( int $blog_id, string $domain, bool $active = true ): Mapping {
		$inserted = $this->db->insert(
			$this->table(),
			array(
				'blog_id' => $blog_id,
				'domain'  => $domain,
				'active'  => $active ? 1 : 0,
			),
			array( '%d', '%s', '%d' )
		);
		if ( false === $inserted ) {
			throw new \RuntimeException( 'Could not write to the domain mapping table: ' . $this->db->last_error );
		}
		return new Mapping( (int) $this->db->insert_id, $blog_id, $domain, $active );
	}

	public function delete( int $id ): bool {
		return (bool) $this->db->delete( $this->table(), array( 'id' => $id ), array( '%d' ) );
	}

	/** @param array<string, mixed> $row */
	private function hydrate( array $row ): Mapping {
		return new Mapping(
			(int) $row['id'],
			(int) $row['blog_id'],
			strtolower( (string) $row['domain'] ),
			(bool) (int) $row['active']
		);
	}
}
