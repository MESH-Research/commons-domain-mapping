<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping;

/**
 * Summary of a site in the network, as the mapping screen needs it.
 */
final class Site {
	public function __construct(
		public readonly int $id,
		public readonly string $domain,
		public readonly string $path,
		public readonly string $name,
		public readonly int $network_id,
	) {}

	/** The site's primary address without a scheme, e.g. "hcommons.org/poetry/". */
	public function address(): string {
		return $this->domain . $this->path;
	}

	public function url(): string {
		return 'https://' . $this->address();
	}

	/** @return array{id:int, name:string, address:string, url:string, domain:string, path:string, network_id:int} */
	public function to_array(): array {
		return array(
			'id'         => $this->id,
			'name'       => $this->name,
			'address'    => $this->address(),
			'url'        => $this->url(),
			'domain'     => $this->domain,
			'path'       => $this->path,
			'network_id' => $this->network_id,
		);
	}
}
