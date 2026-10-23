<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Tests\Unit;

use MeshResearch\DomainMapping\InvalidHostname;
use MeshResearch\DomainMapping\Mapping;
use MeshResearch\DomainMapping\MappingConflict;
use MeshResearch\DomainMapping\MappingNotFound;
use MeshResearch\DomainMapping\MappingService;
use MeshResearch\DomainMapping\MappingSummary;
use MeshResearch\DomainMapping\Tests\Fakes\InMemoryMappingStore;
use MeshResearch\DomainMapping\Tests\Fakes\InMemorySiteDirectory;
use MeshResearch\DomainMapping\UnknownSite;
use PHPUnit\Framework\TestCase;

final class MappingServiceTest extends TestCase {
	private InMemoryMappingStore $store;
	private InMemorySiteDirectory $sites;
	private MappingService $service;

	protected function setUp(): void {
		$this->store = new InMemoryMappingStore();
		$this->sites = new InMemorySiteDirectory();
		$this->sites->add_network( 'hcommons.org' );
		$this->sites->add_network( 'commons.msu.edu' );
		$this->sites->add_site( 1, 'hcommons.org', '/', 'Humanities Commons' );
		$this->sites->add_site( 42, 'poetry.hcommons.org', '/', 'Poetry at RCAH' );
		$this->sites->add_site( 77, 'commons.msu.edu', '/history/', 'MSU History', 2 );
		$this->service = new MappingService( $this->store, $this->sites );
	}

	public function test_add_stores_the_normalised_hostname_against_the_site(): void {
		$mapping = $this->service->add( 'https://Poetry.RCAH.msu.edu/', 42 );

		self::assertSame( 'poetry.rcah.msu.edu', $mapping->domain );
		self::assertSame( 42, $mapping->blog_id );
		self::assertTrue( $mapping->active );
		self::assertNotNull( $this->store->find_by_domain( 'poetry.rcah.msu.edu' ) );
	}

	public function test_add_does_not_change_the_primary_address_by_default(): void {
		$this->service->add( 'poetry.rcah.msu.edu', 42 );

		self::assertSame( array(), $this->sites->primary_changes );
		self::assertSame( 'poetry.hcommons.org', $this->sites->find( 42 )->domain );
	}

	public function test_add_can_make_the_hostname_the_primary_address(): void {
		$this->service->add( 'poetry.rcah.msu.edu', 42, true );

		self::assertSame( 'poetry.rcah.msu.edu', $this->sites->find( 42 )->domain );
		self::assertSame( '/', $this->sites->find( 42 )->path );
		self::assertSame( 'https://poetry.rcah.msu.edu', $this->sites->primary_changes[42]['home'] );
	}

	public function test_add_rejects_an_invalid_hostname_without_touching_storage(): void {
		try {
			$this->service->add( 'not a host', 42 );
			self::fail( 'Expected InvalidHostname' );
		} catch ( InvalidHostname ) {
			self::assertSame( array(), $this->store->all() );
		}
	}

	public function test_add_rejects_an_unknown_site_without_touching_storage(): void {
		try {
			$this->service->add( 'poetry.rcah.msu.edu', 999 );
			self::fail( 'Expected UnknownSite' );
		} catch ( UnknownSite $e ) {
			self::assertStringContainsString( '999', $e->getMessage() );
			self::assertSame( array(), $this->store->all() );
		}
	}

	public function test_add_rejects_a_hostname_that_is_already_mapped(): void {
		$existing = $this->store->seed( 1, 'poetry.rcah.msu.edu' );

		try {
			$this->service->add( 'POETRY.rcah.msu.edu', 42 );
			self::fail( 'Expected MappingConflict' );
		} catch ( MappingConflict $e ) {
			self::assertSame( $existing->id, $e->existing->id );
			self::assertStringContainsString( 'poetry.rcah.msu.edu', $e->getMessage() );
			self::assertCount( 1, $this->store->all() );
		}
	}

	public function test_a_site_may_have_several_hostnames(): void {
		$this->service->add( 'poetry.rcah.msu.edu', 42 );
		$this->service->add( 'www.poetry.rcah.msu.edu', 42 );

		self::assertCount( 2, $this->store->for_site( 42 ) );
	}

	public function test_all_joins_each_mapping_with_its_site_ordered_by_hostname(): void {
		$this->store->seed( 77, 'zeta.example.org' );
		$this->store->seed( 42, 'alpha.example.org' );
		$this->store->seed( 500, 'orphan.example.org' ); // site deleted

		$rows = $this->service->all();

		self::assertContainsOnlyInstancesOf( MappingSummary::class, $rows );
		self::assertSame(
			array( 'alpha.example.org', 'orphan.example.org', 'zeta.example.org' ),
			array_map( fn( MappingSummary $r ) => $r->mapping->domain, $rows )
		);
		self::assertSame( 'Poetry at RCAH', $rows[0]->site?->name );
		self::assertNull( $rows[1]->site );
		self::assertSame( 'MSU History', $rows[2]->site?->name );
	}

	public function test_all_marks_mappings_that_match_the_site_primary_address(): void {
		$this->store->seed( 42, 'poetry.hcommons.org' );
		$this->store->seed( 42, 'poetry.rcah.msu.edu' );

		$by_domain = array();
		foreach ( $this->service->all() as $row ) {
			$by_domain[ $row->mapping->domain ] = $row->is_primary;
		}

		self::assertTrue( $by_domain['poetry.hcommons.org'] );
		self::assertFalse( $by_domain['poetry.rcah.msu.edu'] );
	}

	public function test_remove_deletes_the_row_and_returns_it(): void {
		$seeded = $this->store->seed( 42, 'poetry.rcah.msu.edu' );

		$removed = $this->service->remove( $seeded->id );

		self::assertSame( 'poetry.rcah.msu.edu', $removed->domain );
		self::assertNull( $this->store->find( $seeded->id ) );
	}

	public function test_remove_of_a_missing_row_fails(): void {
		$this->expectException( MappingNotFound::class );
		$this->service->remove( 123 );
	}

	public function test_make_primary_points_the_site_at_the_mapped_hostname(): void {
		$seeded = $this->store->seed( 42, 'poetry.rcah.msu.edu' );

		$mapping = $this->service->make_primary( $seeded->id );

		self::assertSame( $seeded->id, $mapping->id );
		self::assertSame( 'poetry.rcah.msu.edu', $this->sites->find( 42 )->domain );
		self::assertSame( '/', $this->sites->find( 42 )->path );
	}

	public function test_make_primary_fails_when_the_mapping_or_site_is_missing(): void {
		$orphan = $this->store->seed( 500, 'orphan.example.org' );

		try {
			$this->service->make_primary( 9999 );
			self::fail( 'Expected MappingNotFound' );
		} catch ( MappingNotFound ) {
		}

		$this->expectException( UnknownSite::class );
		$this->service->make_primary( $orphan->id );
	}

	public function test_unmapped_sites_lists_external_primaries_with_no_mapping_row(): void {
		// The incident: address changed in Network Admin, no mapping row.
		$this->sites->add_site( 90, 'poetry.rcah.msu.edu', '/', 'Poetry at RCAH' );
		// Properly mapped external site: fine.
		$this->sites->add_site( 91, 'blog.eve.gd', '/', 'Blog' );
		$this->store->seed( 91, 'blog.eve.gd' );
		// Mapped, but the row points at a different site: still broken.
		$this->sites->add_site( 92, 'journal.example.org', '/', 'Journal' );
		$this->store->seed( 42, 'journal.example.org' );

		$ids = array_map( fn( $s ) => $s->id, $this->service->unmapped_sites() );

		self::assertSame( array( 90, 92 ), $ids );
	}

	public function test_unmapped_sites_ignores_sites_inside_a_network(): void {
		self::assertSame( array(), $this->service->unmapped_sites() );
	}
}
