<?php
/**
 * PHPUnit bootstrap.
 *
 * The plugin's domain layer does not depend on WordPress, so tests run on a
 * plain PHP host. The handful of WordPress functions the layer does call
 * (translation and escaping helpers) are stubbed in tests/stubs/wordpress.php.
 */

declare( strict_types=1 );

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs/wordpress.php';
require_once __DIR__ . '/fakes/InMemoryMappingStore.php';
require_once __DIR__ . '/fakes/InMemorySiteDirectory.php';
