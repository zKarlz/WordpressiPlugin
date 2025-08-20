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
            // Logged-in customers are allowed via authorize_request().
            'permission_callback' => [ $this, 'authorize_request' ],
        ] );
        register_rest_route( 'llp/v1', '/finalize', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_finalize' ],
            // Logged-in customers are allowed via authorize_request().
            'permission_callback' => [ $this, 'authorize_request' ],
        ] );

        register_rest_route( 'llp/v1', '/file/(?P<asset>[a-z0-9-]+)/(?P<type>[a-z0-9._-]+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_file' ],
            'permission_callback' => '__return_true',
        ] );

        register_rest_route( 'llp/v1', '/order/(?P<id>\d+)/purge', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_order_purge' ],
            'permission_callback' => [ $this, 'can_manage_order' ],
        ] );

        register_rest_route( 'llp/v1', '/order/(?P<id>\d+)/rerender', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_order_rerender' ],
            'permission_callback' => [ $this, 'can_manage_order' ],
        ] );
    }

    /**
     * Stream a secured file after verifying the signed token.
     */
    public function handle_file( WP_REST_Request $request ) {
        $asset   = sanitize_text_field( $request['asset'] );
        $type    = sanitize_text_field( $request['type'] );
        $storage = LLP_Storage::instance();

        $paths = $storage->get_asset_paths( $asset );
        switch ( $type ) {
            case 'original':
                $file = $paths['original'] ? basename( $paths['original'] ) : '';
                break;
            case 'composite':
                $file = 'composite.png';
                break;
            case 'thumb':
                $file = 'thumb.jpg';
                break;
            default:
                $file = $type;
        }

        if ( empty( $file ) ) {
            return new WP_Error( 'not_found', __( 'File not found', 'llp' ), [ 'status' => 404 ] );
        }

        $request->set_param( 'asset_id', $asset );
        $request->set_param( 'file', $file );

        $storage->serve_file( $request );
    }

    /**
     * Capability check for order routes.
     */
    public function can_manage_order( WP_REST_Request $request ) {
        $order_id = absint( $request['id'] );
        return current_user_can( 'edit_shop_order', $order_id );
    }

    /**
     * Purge stored assets for an order.
     */
    public function handle_order_purge( WP_REST_Request $request ) {
        $order_id = absint( $request['id'] );
        $result   = LLP_Storage::instance()->purge_order( $order_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( [ 'purged' => true ] );
    }

    /**
     * Re-render composites for order items.
     */
    public function handle_order_rerender( WP_REST_Request $request ) {
        $order_id = absint( $request['id'] );
        $result   = LLP_Renderer::instance()->rerender_order( $order_id );
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        return rest_ensure_response( [ 'rerendered' => true ] );
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

        // Allow a custom token as an alternative authorization header.
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
     * Ensure the user is logged in and has basic access before acting on the specified order.
     */
    private function check_order_capabilities( WP_REST_Request $request ) {
        // Allow any logged-in user with read access (e.g. customers).
        if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
            return new WP_Error( 'rest_forbidden', __( 'You are not allowed to perform this action', 'llp' ), [ 'status' => rest_authorization_required_code() ] );
        }

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
        $asset_id     = sanitize_text_field( $request->get_param( 'asset_id' ) );
        $variation_id = absint( $request->get_param( 'variation_id' ) );
        $transform    = $request->get_param( 'transform' );

        // Allow transform to be passed as JSON string and sanitize fields.
        if ( is_string( $transform ) ) {
            $transform = json_decode( wp_unslash( $transform ), true );
        }

        if ( empty( $asset_id ) ) {
            return new WP_Error( 'missing', __( 'Missing asset ID', 'llp' ), [ 'status' => 400 ] );
        }

        if ( ! $variation_id ) {
            return new WP_Error( 'missing_variation', __( 'Missing variation ID', 'llp' ), [ 'status' => 400 ] );
        }

        if ( 'product_variation' !== get_post_type( $variation_id ) ) {
            return new WP_Error( 'invalid_variation', __( 'Invalid variation ID', 'llp' ), [ 'status' => 400 ] );
        }

        if ( ! is_array( $transform ) ) {
            return new WP_Error( 'invalid_transform', __( 'Transform must be an object', 'llp' ), [ 'status' => 400 ] );
        }

        $transform = wp_parse_args( $transform, [ 'crop' => [], 'scale' => 1, 'rotation' => 0 ] );
        $crop      = is_array( $transform['crop'] ) ? $transform['crop'] : [];
        $crop      = wp_parse_args( $crop, [ 'x' => 0, 'y' => 0, 'width' => 0, 'height' => 0 ] );
        $crop      = array_map( 'floatval', array_intersect_key( $crop, array_flip( [ 'x', 'y', 'width', 'height' ] ) ) );

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
