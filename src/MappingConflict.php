<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping;

final class MappingConflict extends DomainMappingException {
	public function __construct( string $message, public readonly Mapping $existing ) {
		parent::__construct( $message );
	}
}
