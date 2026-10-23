<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping;

/**
 * One row of the wp_domain_mapping table.
 */
final class Mapping {
	public function __construct(
		public readonly int $id,
		public readonly int $blog_id,
		public readonly string $domain,
		public readonly bool $active = true,
	) {}
}
