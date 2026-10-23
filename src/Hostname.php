<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping;

/**
 * Pure hostname normalisation and validation.
 */
final class Hostname {
	private const MAX_LENGTH       = 253;
	private const MAX_LABEL_LENGTH = 63;

	/**
	 * Turn operator input ("https://Poetry.RCAH.msu.edu/") into the exact
	 * lowercase hostname sunrise.php compares HTTP_HOST against.
	 *
	 * @throws InvalidHostname
	 */
	public static function normalize( string $input ): string {
		$original = trim( $input );
		$host     = strtolower( $original );
		$host     = preg_replace( '#^[a-z][a-z0-9+.-]*://#', '', $host ) ?? $host;
		$host     = preg_replace( '#[/?\#].*$#s', '', $host ) ?? $host;
		$host     = rtrim( $host, '.' );

		if ( '' === $host ) {
			throw new InvalidHostname( __( 'Enter a hostname, for example poetry.rcah.msu.edu.', 'commons-domain-mapping' ) );
		}

		$reason = self::invalid_reason( $host );
		if ( null !== $reason ) {
			throw new InvalidHostname(
				sprintf(
					/* translators: 1: the hostname as typed, 2: why it was rejected */
					__( '“%1$s” is not a valid hostname: %2$s', 'commons-domain-mapping' ),
					$original,
					$reason
				)
			);
		}

		return $host;
	}

	/** True when $host is $network_domain or a subdomain of it. */
	public static function is_within( string $host, string $network_domain ): bool {
		$host           = strtolower( $host );
		$network_domain = strtolower( $network_domain );
		if ( '' === $network_domain || '' === $host ) {
			return false;
		}
		return $host === $network_domain || str_ends_with( $host, '.' . $network_domain );
	}

	private static function invalid_reason( string $host ): ?string {
		if ( str_contains( $host, ':' ) || str_contains( $host, '@' ) ) {
			return __( 'use the bare hostname without a port or credentials.', 'commons-domain-mapping' );
		}
		if ( strlen( $host ) > self::MAX_LENGTH ) {
			return __( 'it is longer than 253 characters.', 'commons-domain-mapping' );
		}
		if ( ! preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
			return __( 'only letters, digits, hyphens and dots are allowed (use punycode for non-ASCII names).', 'commons-domain-mapping' );
		}

		$labels = explode( '.', $host );
		if ( count( $labels ) < 2 ) {
			return __( 'it needs at least one dot, for example site.example.org.', 'commons-domain-mapping' );
		}
		foreach ( $labels as $label ) {
			if ( '' === $label ) {
				return __( 'it contains an empty label (two dots in a row, or a leading dot).', 'commons-domain-mapping' );
			}
			if ( strlen( $label ) > self::MAX_LABEL_LENGTH ) {
				return __( 'one part is longer than 63 characters.', 'commons-domain-mapping' );
			}
			if ( str_starts_with( $label, '-' ) || str_ends_with( $label, '-' ) ) {
				return __( 'a part starts or ends with a hyphen.', 'commons-domain-mapping' );
			}
		}
		if ( ctype_digit( str_replace( '.', '', $host ) ) ) {
			return __( 'IP addresses cannot be mapped.', 'commons-domain-mapping' );
		}
		return null;
	}
}
