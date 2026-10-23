<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Admin;

use MeshResearch\DomainMapping\DomainMappingException;
use MeshResearch\DomainMapping\MappingService;

/**
 * Translates submitted form fields into service calls and an Outcome.
 * Nonce checks happen before this is reached; the super-admin check
 * happens here too so no caller can forget it.
 */
final class FormHandler {
	public function __construct(
		private readonly MappingService $service,
		private readonly Access $access,
	) {}

	/** @param array<string, mixed> $request Fields: hostname, blog_id, make_primary. */
	public function add( array $request ): Outcome {
		$this->access->assert();

		$hostname     = sanitize_text_field( wp_unslash( $request['hostname'] ?? '' ) );
		$blog_id      = absint( $request['blog_id'] ?? 0 );
		$make_primary = ! empty( $request['make_primary'] );

		if ( 0 === $blog_id ) {
			return Outcome::error( __( 'Choose the site this hostname should point at.', 'commons-domain-mapping' ) );
		}

		try {
			$mapping = $this->service->add( $hostname, $blog_id, $make_primary );
		} catch ( DomainMappingException $e ) {
			return Outcome::error( $e->getMessage() );
		}

		$site = $this->service->site( $blog_id );
		$label = null !== $site ? $this->service->describe( $site ) : (string) $blog_id;

		return Outcome::success(
			$make_primary
				? sprintf(
					/* translators: 1: hostname, 2: site description */
					__( 'Mapped %1$s to %2$s and made it the site’s primary address.', 'commons-domain-mapping' ),
					$mapping->domain,
					$label
				)
				: sprintf(
					/* translators: 1: hostname, 2: site description */
					__( 'Mapped %1$s to %2$s.', 'commons-domain-mapping' ),
					$mapping->domain,
					$label
				)
		);
	}

	/** @param array<string, mixed> $request Fields: mapping_id. */
	public function remove( array $request ): Outcome {
		$this->access->assert();

		try {
			$mapping = $this->service->remove( absint( $request['mapping_id'] ?? 0 ) );
		} catch ( DomainMappingException $e ) {
			return Outcome::error( $e->getMessage() );
		}

		return Outcome::success(
			sprintf(
				/* translators: %s: hostname */
				__( 'Removed the mapping for %s.', 'commons-domain-mapping' ),
				$mapping->domain
			)
		);
	}

	/** @param array<string, mixed> $request Fields: mapping_id. */
	public function make_primary( array $request ): Outcome {
		$this->access->assert();

		try {
			$mapping = $this->service->make_primary( absint( $request['mapping_id'] ?? 0 ) );
		} catch ( DomainMappingException $e ) {
			return Outcome::error( $e->getMessage() );
		}

		$site  = $this->service->site( $mapping->blog_id );
		$label = null !== $site ? $this->service->describe( $site ) : (string) $mapping->blog_id;

		return Outcome::success(
			sprintf(
				/* translators: 1: hostname, 2: site description */
				__( '%1$s is now the primary address of %2$s.', 'commons-domain-mapping' ),
				$mapping->domain,
				$label
			)
		);
	}
}
