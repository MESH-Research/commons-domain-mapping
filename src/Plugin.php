<?php
declare( strict_types=1 );

namespace MeshResearch\DomainMapping;

use MeshResearch\DomainMapping\Admin\Access;
use MeshResearch\DomainMapping\Admin\FormHandler;
use MeshResearch\DomainMapping\Admin\Screen;
use MeshResearch\DomainMapping\Cli\Command;
use MeshResearch\DomainMapping\Rest\SitesController;
use MeshResearch\DomainMapping\Storage\WpdbMappingStore;
use MeshResearch\DomainMapping\Storage\WpSiteDirectory;

/**
 * Composition root. The only class that touches WordPress globals and hooks.
 */
final class Plugin {
	public const REST_NAMESPACE = 'commons-domain-mapping/v1';

	private static ?Plugin $instance = null;

	private ?MappingService $service = null;

	public static function instance(): self {
		return self::$instance ??= new self();
	}

	public function register(): void {
		$screen = new Screen( $this, $this->form_handler() );
		$screen->register();

		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'commons-domain', new Command( $this->service() ) );
		}
	}

	public function register_rest_routes(): void {
		$controller = new SitesController( $this->sites(), $this->access() );

		register_rest_route(
			self::REST_NAMESPACE,
			'/sites',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'permission_callback' => fn(): bool => $this->access()->granted(),
				'args'                => array(
					'search' => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
				'callback'            => static function ( \WP_REST_Request $request ) use ( $controller ) {
					try {
						return rest_ensure_response( $controller->search( (string) $request->get_param( 'search' ) ) );
					} catch ( AccessDenied $e ) {
						return new \WP_Error( 'cdm_forbidden', $e->getMessage(), array( 'status' => 403 ) );
					}
				},
			)
		);
	}

	public function access(): Access {
		return new Access( static fn(): bool => is_super_admin() );
	}

	public function service(): MappingService {
		return $this->service ??= new MappingService( $this->store(), $this->sites() );
	}

	public function form_handler(): FormHandler {
		return new FormHandler( $this->service(), $this->access() );
	}

	public function store(): WpdbMappingStore {
		global $wpdb;
		return new WpdbMappingStore( $wpdb );
	}

	public function sites(): WpSiteDirectory {
		global $wpdb;
		return new WpSiteDirectory( $wpdb );
	}
}
