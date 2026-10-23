<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Cli;

use MeshResearch\DomainMapping\DomainMappingException;
use MeshResearch\DomainMapping\MappingService;
use MeshResearch\DomainMapping\MappingSummary;

/**
 * Manage custom hostname mappings for sites in the network.
 *
 * Shell access to the host is the credential here; the web screen is
 * restricted to super administrators.
 */
final class Command {
	public function __construct( private readonly MappingService $service ) {}

	/**
	 * List every mapped hostname.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : table, json, csv or yaml.
	 * ---
	 * default: table
	 * ---
	 *
	 * @subcommand list
	 */
	public function list_( array $args, array $assoc_args ): void {
		$rows = array_map(
			static fn( MappingSummary $row ) => array(
				'id'       => $row->mapping->id,
				'hostname' => $row->mapping->domain,
				'blog_id'  => $row->mapping->blog_id,
				'site'     => $row->site?->name ?? '(deleted)',
				'address'  => $row->site?->address() ?? '',
				'primary'  => $row->is_primary ? 'yes' : 'no',
			),
			$this->service->all()
		);
		\WP_CLI\Utils\format_items( $assoc_args['format'] ?? 'table', $rows, array( 'id', 'hostname', 'blog_id', 'site', 'address', 'primary' ) );
	}

	/**
	 * Map a hostname to a site.
	 *
	 * ## OPTIONS
	 *
	 * <hostname>
	 * : The hostname, e.g. poetry.rcah.msu.edu.
	 *
	 * --site=<id>
	 * : Blog ID of the site.
	 *
	 * [--primary]
	 * : Also make the hostname the site's primary address.
	 *
	 * ## EXAMPLES
	 *
	 *     wp commons-domain add poetry.rcah.msu.edu --site=42 --primary
	 */
	public function add( array $args, array $assoc_args ): void {
		try {
			$mapping = $this->service->add( $args[0], (int) ( $assoc_args['site'] ?? 0 ), ! empty( $assoc_args['primary'] ) );
		} catch ( DomainMappingException $e ) {
			\WP_CLI::error( $e->getMessage() );
			return;
		}
		\WP_CLI::success( sprintf( 'Mapped %s to site %d (mapping %d).', $mapping->domain, $mapping->blog_id, $mapping->id ) );
	}

	/**
	 * Remove a mapping.
	 *
	 * ## OPTIONS
	 *
	 * <hostname-or-id>
	 * : The hostname, or the numeric mapping ID from `list`.
	 */
	public function remove( array $args ): void {
		try {
			$mapping = $this->service->remove( $this->resolve_id( $args[0] ) );
		} catch ( DomainMappingException $e ) {
			\WP_CLI::error( $e->getMessage() );
			return;
		}
		\WP_CLI::success( sprintf( 'Removed the mapping for %s.', $mapping->domain ) );
	}

	/**
	 * Make a mapped hostname the site's primary address.
	 *
	 * ## OPTIONS
	 *
	 * <hostname-or-id>
	 * : The hostname, or the numeric mapping ID from `list`.
	 */
	public function primary( array $args ): void {
		try {
			$mapping = $this->service->make_primary( $this->resolve_id( $args[0] ) );
		} catch ( DomainMappingException $e ) {
			\WP_CLI::error( $e->getMessage() );
			return;
		}
		\WP_CLI::success( sprintf( '%s is now the primary address of site %d.', $mapping->domain, $mapping->blog_id ) );
	}

	/**
	 * Find sites whose primary address is outside every network and has no
	 * mapping row (the configuration that breaks login cookies).
	 */
	public function check(): void {
		$gaps = $this->service->unmapped_sites();
		if ( array() === $gaps ) {
			\WP_CLI::success( 'Every site with a custom address has a mapping.' );
			return;
		}
		foreach ( $gaps as $site ) {
			\WP_CLI::warning( sprintf( 'Site %d (%s) uses %s with no mapping. Fix: wp commons-domain add %s --site=%d', $site->id, $site->name, $site->domain, $site->domain, $site->id ) );
		}
		\WP_CLI::halt( 1 );
	}

	/** @throws DomainMappingException */
	private function resolve_id( string $hostname_or_id ): int {
		if ( ctype_digit( $hostname_or_id ) ) {
			return (int) $hostname_or_id;
		}
		foreach ( $this->service->all() as $row ) {
			if ( $row->mapping->domain === strtolower( trim( $hostname_or_id ) ) ) {
				return $row->mapping->id;
			}
		}
		throw new \MeshResearch\DomainMapping\MappingNotFound( sprintf( 'No mapping for %s.', $hostname_or_id ) );
	}
}
