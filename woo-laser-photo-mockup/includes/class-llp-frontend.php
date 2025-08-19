<?php
/**
 * Frontend customer interactions.
 */
class LLP_Frontend {
    use LLP_Singleton;

    protected function __construct() {
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_customizer' ] );
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'add_cart_item_data' ], 10, 3 );
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'validate_add_to_cart' ], 10, 3 );
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item' ], 10, 2 );
        add_filter( 'woocommerce_get_cart_item_from_session', [ $this, 'restore_from_session' ], 10, 3 );
    }

    /**
     * Enqueue frontend scripts.
     */
    public function enqueue_scripts() {
        wp_enqueue_style( 'llp-frontend', LLP_PLUGIN_URL . 'assets/css/frontend.css', [], '1.0.0' );
        wp_enqueue_script( 'llp-frontend', LLP_PLUGIN_URL . 'assets/js/frontend.js', [ 'jquery' ], '1.0.0', true );
        wp_localize_script( 'llp-frontend', 'llpVars', [
            'restUrl' => esc_url_raw( rest_url( 'llp/v1' ) ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
        ] );
    }

    /**
     * Render customizer template.
     */
    public function render_customizer() {
        global $product;
        if ( ! $product || ! $product->is_type( 'variable' ) ) {
            return;
        }
        wc_get_template( 'single-product/customizer.php', [], '', LLP_PLUGIN_DIR . 'templates/' );
    }

    /**
     * Validate add to cart.
     */
    public function validate_add_to_cart( $passed, $product_id, $quantity ) {
        if ( isset( $_POST['llp_asset_id'] ) && ! empty( $_POST['llp_asset_id'] ) ) {
            return $passed;
        }
        wc_add_notice( __( 'Please upload and finalize your photo.', 'llp' ), 'error' );
        return false;
    }

    /**
     * Add data to cart item.
     */
    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        if ( isset( $_POST['llp_asset_id'] ) ) {
            $cart_item_data['llp_asset_id'] = sanitize_text_field( $_POST['llp_asset_id'] );
        }
        if ( isset( $_POST['llp_thumb_url'] ) ) {
            $cart_item_data['llp_thumb_url'] = esc_url_raw( $_POST['llp_thumb_url'] );
        }
        return $cart_item_data;
    }

    /**
     * Display thumbnail in cart and checkout.
     */
    public function display_cart_item( $item_data, $cart_item ) {
        if ( isset( $cart_item['llp_thumb_url'] ) ) {
            $item_data[] = [
                'name'  => __( 'Preview', 'llp' ),
                'value' => '<img src="' . esc_url( $cart_item['llp_thumb_url'] ) . '" alt="" style="max-width:80px;" />',
            ];
        }
        return $item_data;
    }

    /**
     * Restore data from session.
     */
    public function restore_from_session( $cart_item, $values, $key ) {
        if ( isset( $values['llp_asset_id'] ) ) {
            $cart_item['llp_asset_id'] = $values['llp_asset_id'];
        }
        if ( isset( $values['llp_thumb_url'] ) ) {
            $cart_item['llp_thumb_url'] = $values['llp_thumb_url'];
        }
        return $cart_item;
    }
}
