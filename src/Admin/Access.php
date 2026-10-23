<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Admin;

use MeshResearch\DomainMapping\AccessDenied;

/**
 * The single rule for who may use any part of this plugin: super admins.
 *
 * The check is injected so the domain layer can be tested without
 * WordPress; in production it is `is_super_admin(...)`.
 */
final class Access {
	/** @param \Closure(): bool $is_super_admin */
	public function __construct( private readonly \Closure $is_super_admin ) {}

	public function granted(): bool {
		return true === ( $this->is_super_admin )();
	}

	/** @throws AccessDenied */
	public function assert(): void {
		if ( ! $this->granted() ) {
			throw new AccessDenied( __( 'Only super administrators can manage domain mappings.', 'commons-domain-mapping' ) );
		}
	}
}
