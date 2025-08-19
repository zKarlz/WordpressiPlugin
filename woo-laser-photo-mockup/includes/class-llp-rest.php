<?php
/**
 * REST API endpoints.
 */
class LLP_REST {
    use LLP_Singleton;

    protected function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST routes.
     */
    public function register_routes() {
        register_rest_route( 'llp/v1', '/upload', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_upload' ],
            'permission_callback' => '__return_true',
        ] );
        register_rest_route( 'llp/v1', '/finalize', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_finalize' ],
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Handle file upload.
     */
    public function handle_upload( WP_REST_Request $request ) {
        if ( empty( $_FILES['file'] ) ) {
            return new WP_Error( 'no_file', __( 'No file uploaded', 'llp' ), [ 'status' => 400 ] );
        }
        $file     = $_FILES['file'];
        $security = LLP_Security::instance();
        $checked  = $security->validate_upload( $file );
        if ( is_wp_error( $checked ) ) {
            return $checked;
        }
        $storage = LLP_Storage::instance();
        $result  = $storage->store_upload( $file );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( $result );
    }

    /**
     * Finalize and generate composite.
     */
    public function handle_finalize( WP_REST_Request $request ) {
        $asset_id    = sanitize_text_field( $request['asset_id'] );
        $variation_id = absint( $request['variation_id'] );
        $transform   = $request['transform'];
        if ( empty( $asset_id ) || empty( $variation_id ) || empty( $transform ) ) {
            return new WP_Error( 'missing', __( 'Missing data', 'llp' ), [ 'status' => 400 ] );
        }
        $renderer = LLP_Renderer::instance();
        $result   = $renderer->generate_composite( $asset_id, $variation_id, $transform );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( $result );
    }
}
