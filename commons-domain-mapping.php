<?php
/**
 * Plugin Name:       Commons Domain Mapping
 * Plugin URI:        https://github.com/MESH-Research/commons-domain-mapping
 * Description:       Map custom hostnames to sites on the Knowledge Commons multisite so sunrise.php resolves them and issues login cookies on the right domain. Super administrators only.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.5
 * Author:            MESH Research
 * Author URI:        https://meshresearch.net/
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       commons-domain-mapping
 * Network:           true
 *
 * @package MeshResearch\DomainMapping
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMMONS_DOMAIN_MAPPING_VERSION', '1.0.0' );
define( 'COMMONS_DOMAIN_MAPPING_FILE', __FILE__ );
define( 'COMMONS_DOMAIN_MAPPING_DIR', __DIR__ );

// Self-contained PSR-4 autoloader so the plugin works whether or not the
// site's composer autoloader knows about it.
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'MeshResearch\\DomainMapping\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_file( $file ) ) {
			require $file;
		}
	}
);

if ( ! is_multisite() ) {
	add_action(
		'admin_notices',
		static function (): void {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Commons Domain Mapping only works on a multisite network.', 'commons-domain-mapping' ) . '</p></div>';
		}
	);
	return;
}

\MeshResearch\DomainMapping\Plugin::instance()->register();
