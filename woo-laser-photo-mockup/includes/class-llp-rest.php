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
            'permission_callback' => [ $this, 'authorize_request' ],
        ] );
        register_rest_route( 'llp/v1', '/finalize', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_finalize' ],
            'permission_callback' => [ $this, 'authorize_request' ],
        ] );
    }

    /**
     * Permission callback for REST requests.
     *
     * Requires a valid wp_rest nonce or a custom token. When an order ID is
     * provided the current user must also have permission to manage that
     * specific order.
     */
    public function authorize_request( WP_REST_Request $request ) {
        // Verify wp_rest nonce from header or request parameter.
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce ) {
            $nonce = $request->get_param( '_wpnonce' );
        }
        if ( $nonce && wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return $this->check_order_capabilities( $request );
        }

        // Allow custom token for unauthenticated requests.
        $token = $request->get_header( 'X-LLP-Token' );
        if ( ! $token ) {
            $token = $request->get_param( 'token' );
        }
        if ( $token && defined( 'LLP_REST_TOKEN' ) && hash_equals( LLP_REST_TOKEN, $token ) ) {
            return $this->check_order_capabilities( $request );
        }

        return new WP_Error( 'rest_forbidden', __( 'Invalid nonce or token', 'llp' ), [ 'status' => rest_authorization_required_code() ] );
    }

    /**
     * Ensure user can act on the specified order if one is provided.
     */
    private function check_order_capabilities( WP_REST_Request $request ) {
        $order_id = absint( $request->get_param( 'order_id' ) );
        if ( $order_id && ! current_user_can( 'edit_shop_order', $order_id ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You are not allowed to access this order', 'llp' ), [ 'status' => rest_authorization_required_code() ] );
        }
        return true;
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
        $asset_id     = sanitize_text_field( $request['asset_id'] );
        $variation_id = absint( $request['variation_id'] );
        $transform    = $request['transform'];

        if ( empty( $asset_id ) ) {
            return new WP_Error( 'missing', __( 'Missing asset ID', 'llp' ), [ 'status' => 400 ] );
        }

        if ( ! $variation_id || 'product_variation' !== get_post_type( $variation_id ) ) {
            return new WP_Error( 'invalid_variation', __( 'Invalid variation ID', 'llp' ), [ 'status' => 400 ] );
        }

        if ( ! is_array( $transform ) ) {
            return new WP_Error( 'invalid_transform', __( 'Transform must be an object', 'llp' ), [ 'status' => 400 ] );
        }

        $transform = wp_parse_args( $transform, [ 'crop' => [], 'scale' => 1, 'rotation' => 0 ] );
        $crop      = wp_parse_args( $transform['crop'], [ 'x' => 0, 'y' => 0, 'width' => 0, 'height' => 0 ] );

        foreach ( [ 'x', 'y', 'width', 'height' ] as $key ) {
            $crop[ $key ] = floatval( $crop[ $key ] );
        }

        $transform = [
            'crop'     => $crop,
            'scale'    => floatval( $transform['scale'] ),
            'rotation' => floatval( $transform['rotation'] ),
        ];

        $renderer = LLP_Renderer::instance();
        $result   = $renderer->generate_composite( $asset_id, $variation_id, $transform );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( $result );
    }
}
