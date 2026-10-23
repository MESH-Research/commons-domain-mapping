<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping\Admin;

/**
 * What a form submission produced: a notice for the operator.
 */
final class Outcome {
	public const SUCCESS = 'success';
	public const ERROR   = 'error';

	public function __construct(
		public readonly string $status,
		public readonly string $message,
	) {}

	public static function success( string $message ): self {
		return new self( self::SUCCESS, $message );
	}

	public static function error( string $message ): self {
		return new self( self::ERROR, $message );
	}

	public function ok(): bool {
		return self::SUCCESS === $this->status;
	}
}
