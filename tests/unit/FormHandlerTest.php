<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Tests\Unit;

use MeshResearch\DomainMapping\AccessDenied;
use MeshResearch\DomainMapping\Admin\Access;
use MeshResearch\DomainMapping\Admin\FormHandler;
use MeshResearch\DomainMapping\Admin\Outcome;
use MeshResearch\DomainMapping\MappingService;
use MeshResearch\DomainMapping\Tests\Fakes\InMemoryMappingStore;
use MeshResearch\DomainMapping\Tests\Fakes\InMemorySiteDirectory;
use PHPUnit\Framework\TestCase;

final class FormHandlerTest extends TestCase {
	private InMemoryMappingStore $store;
	private InMemorySiteDirectory $sites;
	private FormHandler $handler;

	protected function setUp(): void {
		$this->store = new InMemoryMappingStore();
		$this->sites = new InMemorySiteDirectory();
		$this->sites->add_network( 'hcommons.org' );
		$this->sites->add_site( 42, 'poetry.hcommons.org', '/', 'Poetry at RCAH' );
		$this->handler = new FormHandler(
			new MappingService( $this->store, $this->sites ),
			new Access( fn() => true )
		);
	}

	public function test_add_success_names_the_hostname_and_site(): void {
		$outcome = $this->handler->add(
			array(
				'hostname' => ' https://Poetry.RCAH.msu.edu/ ',
				'blog_id'  => '42',
			)
		);

		self::assertTrue( $outcome->ok() );
		self::assertStringContainsString( 'poetry.rcah.msu.edu', $outcome->message );
		self::assertStringContainsString( 'Poetry at RCAH', $outcome->message );
		self::assertNotNull( $this->store->find_by_domain( 'poetry.rcah.msu.edu' ) );
		self::assertSame( array(), $this->sites->primary_changes );
	}

	public function test_add_honours_the_make_primary_checkbox(): void {
		$outcome = $this->handler->add(
			array(
				'hostname'     => 'poetry.rcah.msu.edu',
				'blog_id'      => '42',
				'make_primary' => '1',
			)
		);

		self::assertTrue( $outcome->ok() );
		self::assertSame( 'poetry.rcah.msu.edu', $this->sites->find( 42 )->domain );
		self::assertStringContainsString( 'primary', strtolower( $outcome->message ) );
	}

	public function test_add_without_a_site_is_an_error(): void {
		$outcome = $this->handler->add( array( 'hostname' => 'poetry.rcah.msu.edu' ) );

		self::assertSame( Outcome::ERROR, $outcome->status );
		self::assertStringContainsString( 'site', strtolower( $outcome->message ) );
		self::assertSame( array(), $this->store->all() );
	}

	public function test_add_with_a_bad_hostname_reports_the_validation_message(): void {
		$outcome = $this->handler->add(
			array(
				'hostname' => 'poetry.rcah.msu.edu:8443',
				'blog_id'  => '42',
			)
		);

		self::assertFalse( $outcome->ok() );
		self::assertStringContainsString( 'poetry.rcah.msu.edu:8443', $outcome->message );
	}

	public function test_add_duplicate_reports_which_site_already_owns_it(): void {
		$this->store->seed( 42, 'poetry.rcah.msu.edu' );

		$outcome = $this->handler->add(
			array(
				'hostname' => 'poetry.rcah.msu.edu',
				'blog_id'  => '42',
			)
		);

		self::assertFalse( $outcome->ok() );
		self::assertStringContainsString( 'already', $outcome->message );
		self::assertCount( 1, $this->store->all() );
	}

	public function test_remove_reports_the_removed_hostname(): void {
		$seeded = $this->store->seed( 42, 'poetry.rcah.msu.edu' );

		$outcome = $this->handler->remove( array( 'mapping_id' => (string) $seeded->id ) );

		self::assertTrue( $outcome->ok() );
		self::assertStringContainsString( 'poetry.rcah.msu.edu', $outcome->message );
		self::assertSame( array(), $this->store->all() );
	}

	public function test_remove_of_unknown_id_is_an_error(): void {
		$outcome = $this->handler->remove( array( 'mapping_id' => '77' ) );

		self::assertFalse( $outcome->ok() );
	}

	public function test_make_primary_updates_the_site_and_reports_it(): void {
		$seeded = $this->store->seed( 42, 'poetry.rcah.msu.edu' );

		$outcome = $this->handler->make_primary( array( 'mapping_id' => (string) $seeded->id ) );

		self::assertTrue( $outcome->ok() );
		self::assertSame( 'poetry.rcah.msu.edu', $this->sites->find( 42 )->domain );
		self::assertStringContainsString( 'poetry.rcah.msu.edu', $outcome->message );
	}

	public function test_non_super_admins_cannot_do_anything(): void {
		$seeded  = $this->store->seed( 42, 'poetry.rcah.msu.edu' );
		$handler = new FormHandler(
			new MappingService( $this->store, $this->sites ),
			new Access( fn() => false )
		);

		foreach (
			array(
				fn() => $handler->add( array( 'hostname' => 'other.example.org', 'blog_id' => '42' ) ),
				fn() => $handler->remove( array( 'mapping_id' => (string) $seeded->id ) ),
				fn() => $handler->make_primary( array( 'mapping_id' => (string) $seeded->id ) ),
			) as $attempt
		) {
			try {
				$attempt();
				self::fail( 'Expected AccessDenied' );
			} catch ( AccessDenied ) {
			}
		}

		self::assertCount( 1, $this->store->all() );
		self::assertSame( 'poetry.hcommons.org', $this->sites->find( 42 )->domain );
	}
}
