<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Tests\Unit;

use MeshResearch\DomainMapping\AccessDenied;
use MeshResearch\DomainMapping\Admin\Access;
use PHPUnit\Framework\TestCase;

final class AccessTest extends TestCase {
	public function test_super_admins_are_granted(): void {
		$access = new Access( fn() => true );

		self::assertTrue( $access->granted() );
		$access->assert();
		$this->addToAssertionCount( 1 );
	}

	public function test_everyone_else_is_denied(): void {
		$access = new Access( fn() => false );

		self::assertFalse( $access->granted() );
		$this->expectException( AccessDenied::class );
		$access->assert();
	}
}
