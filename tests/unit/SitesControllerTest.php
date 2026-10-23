<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Tests\Unit;

use MeshResearch\DomainMapping\AccessDenied;
use MeshResearch\DomainMapping\Admin\Access;
use MeshResearch\DomainMapping\Rest\SitesController;
use MeshResearch\DomainMapping\Tests\Fakes\InMemorySiteDirectory;
use PHPUnit\Framework\TestCase;

final class SitesControllerTest extends TestCase {
	private InMemorySiteDirectory $sites;
	private SitesController $controller;

	protected function setUp(): void {
		$this->sites = new InMemorySiteDirectory();
		$this->sites->add_site( 1, 'hcommons.org', '/', 'Humanities Commons' );
		$this->sites->add_site( 42, 'poetry.hcommons.org', '/', 'Poetry at RCAH' );
		$this->sites->add_site( 77, 'commons.msu.edu', '/history/', 'MSU History', 2 );
		$this->controller = new SitesController( $this->sites, new Access( fn() => true ) );
	}

	public function test_results_are_plain_arrays_the_combobox_can_render(): void {
		$results = $this->controller->search( 'poetry' );

		self::assertSame(
			array(
				array(
					'id'         => 42,
					'name'       => 'Poetry at RCAH',
					'address'    => 'poetry.hcommons.org/',
					'url'        => 'https://poetry.hcommons.org/',
					'domain'     => 'poetry.hcommons.org',
					'path'       => '/',
					'network_id' => 1,
				),
			),
			$results
		);
	}

	public function test_numeric_term_matches_the_site_id_first(): void {
		$this->sites->add_site( 4242, 'site77.example.org', '/', 'Contains 77' );

		$results = $this->controller->search( '77' );

		self::assertSame( 77, $results[0]['id'] );
		self::assertSame( array( 77, 4242 ), array_column( $results, 'id' ) );
	}

	public function test_numeric_term_with_no_such_id_still_searches_addresses(): void {
		$this->sites->add_site( 4242, 'site77.example.org', '/', 'Contains 77' );
		$this->sites = new InMemorySiteDirectory();
		$this->sites->add_site( 4242, 'site77.example.org', '/', 'Contains 77' );
		$controller = new SitesController( $this->sites, new Access( fn() => true ) );

		self::assertSame( array( 4242 ), array_column( $controller->search( '77' ), 'id' ) );
	}

	public function test_search_ignores_surrounding_whitespace_and_schemes(): void {
		self::assertSame( array( 42 ), array_column( $this->controller->search( '  https://poetry.hcommons.org/ ' ), 'id' ) );
	}

	public function test_results_are_capped(): void {
		for ( $i = 100; $i < 130; $i++ ) {
			$this->sites->add_site( $i, "site$i.example.org", '/', "Site $i" );
		}

		self::assertCount( SitesController::LIMIT, $this->controller->search( 'example.org' ) );
	}

	public function test_non_super_admins_get_nothing(): void {
		$controller = new SitesController( $this->sites, new Access( fn() => false ) );

		$this->expectException( AccessDenied::class );
		$controller->search( 'poetry' );
	}
}
