<?php
/**
 * Variation level settings for mockup.
 */
class LLP_Variation_Fields {
    use LLP_Singleton;

    protected function __construct() {
        add_action( 'woocommerce_product_after_variable_attributes', [ $this, 'render_fields' ], 10, 3 );
        add_action( 'woocommerce_save_product_variation', [ $this, 'save_fields' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
    }

    /**
     * Enqueue admin scripts for media uploader and field handling.
     */
    public function enqueue_admin_scripts( $hook ) {
        $screen = get_current_screen();

        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || empty( $screen ) || 'product' !== $screen->post_type ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'llp-admin', LLP_PLUGIN_URL . 'assets/css/admin.css', [], '1.0.0' );
        wp_enqueue_script(
            'llp-admin-variation',
            LLP_PLUGIN_URL . 'assets/js/admin-variation.js',
            [ 'jquery', 'jquery-ui-draggable', 'jquery-ui-resizable' ],
            '1.0.0',
            true
        );
    }

    /**
     * Render variation fields.
     */
    public function render_fields( $loop, $variation_data, $variation ) {
        $base_id = get_post_meta( $variation->ID, '_llp_base_image_id', true );
        $mask_id = get_post_meta( $variation->ID, '_llp_mask_image_id', true );
        $bounds  = get_post_meta( $variation->ID, '_llp_bounds', true );
        $base_src = $base_id ? wp_get_attachment_image_url( $base_id, 'full' ) : '';
        $aspect  = get_post_meta( $variation->ID, '_llp_aspect_ratio', true );
        $min_res = get_post_meta( $variation->ID, '_llp_min_resolution', true );
        $dpi     = get_post_meta( $variation->ID, '_llp_output_dpi', true );
        ?>
        <div class="llp-variation-fields">
            <p>
                <label><?php esc_html_e( 'Base Image', 'llp' ); ?></label>
                <input type="hidden" class="llp-media-field" name="llp_base_image_id[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $base_id ); ?>" />
                <button class="button llp-select-media"><?php esc_html_e( 'Select Image', 'llp' ); ?></button>
            </p>
            <p>
                <label><?php esc_html_e( 'Mask Image', 'llp' ); ?></label>
                <input type="hidden" class="llp-media-field" name="llp_mask_image_id[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $mask_id ); ?>" />
                <button class="button llp-select-media"><?php esc_html_e( 'Select Mask', 'llp' ); ?></button>
            </p>
            <p class="llp-bounds-field">
                <label><?php esc_html_e( 'Bounds', 'llp' ); ?></label>
                <input type="hidden" class="llp-bounds-input" name="llp_bounds[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $bounds ); ?>" />
            </p>
            <div class="llp-bounds-wrapper">
                <?php if ( $base_src ) : ?>
                    <img src="<?php echo esc_url( $base_src ); ?>" class="llp-base-image" alt="" />
                <?php endif; ?>
                <div class="llp-overlay"></div>
            </div>
            <p class="llp-rotation-field">
                <label><?php esc_html_e( 'Rotation', 'llp' ); ?></label>
                <input type="range" class="llp-rotation" min="0" max="360" value="0" />
            </p>
            <p>
                <label><?php esc_html_e( 'Aspect Ratio (e.g. 4:3)', 'llp' ); ?></label>
                <input type="text" name="llp_aspect_ratio[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $aspect ); ?>" />
            </p>
            <p>
                <label><?php esc_html_e( 'Minimum Resolution (JSON width,height)', 'llp' ); ?></label>
                <input type="text" name="llp_min_resolution[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $min_res ); ?>" />
            </p>
            <p>
                <label><?php esc_html_e( 'Output DPI', 'llp' ); ?></label>
                <input type="number" name="llp_output_dpi[<?php echo esc_attr( $variation->ID ); ?>]" value="<?php echo esc_attr( $dpi ); ?>" />
            </p>
        </div>
        <?php
    }

    /**
     * Save variation fields.
     */
    public function save_fields( $variation_id, $i ) {
        $fields = [
            '_llp_base_image_id'   => isset( $_POST['llp_base_image_id'][ $variation_id ] ) ? absint( $_POST['llp_base_image_id'][ $variation_id ] ) : '',
            '_llp_mask_image_id'   => isset( $_POST['llp_mask_image_id'][ $variation_id ] ) ? absint( $_POST['llp_mask_image_id'][ $variation_id ] ) : '',
            '_llp_bounds'          => isset( $_POST['llp_bounds'][ $variation_id ] ) ? wp_kses_post( wp_unslash( $_POST['llp_bounds'][ $variation_id ] ) ) : '',
            '_llp_aspect_ratio'    => isset( $_POST['llp_aspect_ratio'][ $variation_id ] ) ? sanitize_text_field( $_POST['llp_aspect_ratio'][ $variation_id ] ) : '',
            '_llp_min_resolution'  => isset( $_POST['llp_min_resolution'][ $variation_id ] ) ? sanitize_text_field( $_POST['llp_min_resolution'][ $variation_id ] ) : '',
            '_llp_output_dpi'      => isset( $_POST['llp_output_dpi'][ $variation_id ] ) ? absint( $_POST['llp_output_dpi'][ $variation_id ] ) : '',
        ];
        foreach ( $fields as $meta_key => $value ) {
            if ( '' !== $value ) {
                update_post_meta( $variation_id, $meta_key, $value );
            } else {
                delete_post_meta( $variation_id, $meta_key );
            }
        }
    }
}
