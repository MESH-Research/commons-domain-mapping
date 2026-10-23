<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping;

/** Base class: every message is safe to show to an administrator. */
class DomainMappingException extends \RuntimeException {}
