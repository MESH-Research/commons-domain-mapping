<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping;

/**
 * A mapping joined with the site it points at, for display.
 */
final class MappingSummary {
	public function __construct(
		public readonly Mapping $mapping,
		public readonly ?Site $site,
		public readonly bool $is_primary,
	) {}
}
