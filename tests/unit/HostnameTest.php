<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Tests\Unit;

use MeshResearch\DomainMapping\Hostname;
use MeshResearch\DomainMapping\InvalidHostname;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HostnameTest extends TestCase {
	/** @return iterable<string, array{string, string}> */
	public static function normalised(): iterable {
		yield 'plain'                 => array( 'poetry.rcah.msu.edu', 'poetry.rcah.msu.edu' );
		yield 'uppercase'             => array( 'Poetry.RCAH.MSU.edu', 'poetry.rcah.msu.edu' );
		yield 'surrounding space'     => array( "  poetry.rcah.msu.edu \n", 'poetry.rcah.msu.edu' );
		yield 'https scheme'          => array( 'https://poetry.rcah.msu.edu', 'poetry.rcah.msu.edu' );
		yield 'http scheme'           => array( 'http://poetry.rcah.msu.edu', 'poetry.rcah.msu.edu' );
		yield 'trailing slash'        => array( 'poetry.rcah.msu.edu/', 'poetry.rcah.msu.edu' );
		yield 'path'                  => array( 'https://poetry.rcah.msu.edu/wp-admin/', 'poetry.rcah.msu.edu' );
		yield 'query string'          => array( 'poetry.rcah.msu.edu?x=1', 'poetry.rcah.msu.edu' );
		yield 'fragment'              => array( 'poetry.rcah.msu.edu#top', 'poetry.rcah.msu.edu' );
		yield 'trailing dot'          => array( 'poetry.rcah.msu.edu.', 'poetry.rcah.msu.edu' );
		yield 'www kept'              => array( 'www.example.org', 'www.example.org' );
		yield 'hyphens'               => array( 'my-site.example-domain.org', 'my-site.example-domain.org' );
		yield 'digits'                => array( 'site2024.example.org', 'site2024.example.org' );
		yield 'punycode'              => array( 'xn--bcher-kva.example', 'xn--bcher-kva.example' );
	}

	#[DataProvider( 'normalised' )]
	public function test_normalize_returns_the_bare_lowercase_hostname( string $input, string $expected ): void {
		self::assertSame( $expected, Hostname::normalize( $input ) );
	}

	/** @return iterable<string, array{string}> */
	public static function rejected(): iterable {
		yield 'empty'                    => array( '' );
		yield 'whitespace only'          => array( "  \t" );
		yield 'scheme only'              => array( 'https://' );
		yield 'single label'             => array( 'localhost' );
		yield 'space inside'             => array( 'poetry rcah.msu.edu' );
		yield 'port'                     => array( 'poetry.rcah.msu.edu:8443' );
		yield 'userinfo'                 => array( 'user@poetry.rcah.msu.edu' );
		yield 'leading hyphen label'     => array( '-poetry.msu.edu' );
		yield 'trailing hyphen label'    => array( 'poetry-.msu.edu' );
		yield 'empty label'              => array( 'poetry..msu.edu' );
		yield 'leading dot'              => array( '.poetry.msu.edu' );
		yield 'underscore'               => array( 'poetry_site.msu.edu' );
		yield 'ipv4'                     => array( '192.168.1.1' );
		yield 'ipv6'                     => array( '[::1]' );
		yield 'non-ascii'                => array( 'bücher.example' );
		yield 'label too long'           => array( str_repeat( 'a', 64 ) . '.example.org' );
		yield 'total too long'           => array( implode( '.', array_fill( 0, 5, str_repeat( 'a', 60 ) ) ) );
		yield 'wildcard'                 => array( '*.example.org' );
	}

	#[DataProvider( 'rejected' )]
	public function test_normalize_rejects_invalid_input( string $input ): void {
		$this->expectException( InvalidHostname::class );
		Hostname::normalize( $input );
	}

	public function test_rejection_message_is_operator_readable(): void {
		try {
			Hostname::normalize( 'poetry.rcah.msu.edu:8443' );
			self::fail( 'Expected InvalidHostname' );
		} catch ( InvalidHostname $e ) {
			self::assertNotSame( '', $e->getMessage() );
			self::assertStringContainsString( 'poetry.rcah.msu.edu:8443', $e->getMessage() );
		}
	}

	/** @return iterable<string, array{string, string, bool}> */
	public static function membership(): iterable {
		yield 'identical'                 => array( 'hcommons.org', 'hcommons.org', true );
		yield 'subdomain'                 => array( 'mla.hcommons.org', 'hcommons.org', true );
		yield 'deep subdomain'            => array( 'a.b.hcommons.org', 'hcommons.org', true );
		yield 'suffix without dot'        => array( 'nothcommons.org', 'hcommons.org', false );
		yield 'different domain'          => array( 'poetry.rcah.msu.edu', 'hcommons.org', false );
		yield 'parent is not within child' => array( 'hcommons.org', 'mla.hcommons.org', false );
		yield 'case-insensitive'          => array( 'MLA.HCommons.org', 'hcommons.org', true );
		yield 'empty network'             => array( 'hcommons.org', '', false );
	}

	#[DataProvider( 'membership' )]
	public function test_is_within( string $host, string $network, bool $expected ): void {
		self::assertSame( $expected, Hostname::is_within( $host, $network ) );
	}
}
