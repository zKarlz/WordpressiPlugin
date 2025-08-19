<?php
/**
 * Order and email integration.
 */
class LLP_Order {
    use LLP_Singleton;

    protected function __construct() {
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_line_item' ], 10, 4 );
        add_action( 'woocommerce_order_item_meta_end', [ $this, 'order_item_preview' ], 10, 4 );
    }

    /**
     * Persist meta to order item.
     *
     * Values are sanitized before storage so they can safely surface later in
     * order screens and emails.
     */
    public function add_order_line_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['llp_asset_id'] ) ) {
            $item->add_meta_data( '_llp_asset_id', sanitize_text_field( $values['llp_asset_id'] ), true );
        }
        if ( isset( $values['llp_thumb_url'] ) ) {
            $item->add_meta_data( '_llp_thumb_url', esc_url_raw( $values['llp_thumb_url'] ), true );
        }
        if ( isset( $values['llp_composite_url'] ) ) {
            $item->add_meta_data( '_llp_composite_url', esc_url_raw( $values['llp_composite_url'] ), true );
        }
        if ( isset( $values['llp_original_url'] ) ) {
            $item->add_meta_data( '_llp_original_url', esc_url_raw( $values['llp_original_url'] ), true );
        }
        if ( isset( $values['llp_transform'] ) ) {
            $item->add_meta_data( '_llp_transform', sanitize_textarea_field( $values['llp_transform'] ), true );
        }
    }

    /**
     * Output preview and meta in order item display.
     *
     * @param int      $item_id    Order item ID.
     * @param WC_Order_Item $item  Order item object.
     * @param WC_Order $order      Order object.
     * @param bool     $plain_text Whether outputting plain text.
     */
    public function order_item_preview( $item_id, $item, $order, $plain_text = false ) {
        if ( $plain_text ) {
            return;
        }

        $thumb      = $item->get_meta( '_llp_thumb_url', true );
        $asset_id   = $item->get_meta( '_llp_asset_id', true );
        $composite  = $item->get_meta( '_llp_composite_url', true );
        $original   = $item->get_meta( '_llp_original_url', true );
        $transform  = $item->get_meta( '_llp_transform', true );

        if ( ! $thumb && ! $asset_id && ! $composite && ! $original && ! $transform ) {
            return;
        }

        wc_get_template(
            'emails/line-item-preview.php',
            [
                'thumb_url'      => $thumb,
                'asset_id'       => $asset_id,
                'composite_url'  => $composite,
                'original_url'   => $original,
                'transform_json' => $transform,
            ],
            '',
            LLP_PLUGIN_DIR . 'templates/'
        );
    }

    /**
     * Retrieve all asset IDs referenced by orders.
     *
     * @return array
     */
    public function get_referenced_asset_ids() {
        global $wpdb;

        $table = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $results = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM {$table} WHERE meta_key = %s", '_llp_asset_id' ) );

        return array_filter( array_map( 'sanitize_text_field', (array) $results ) );
    }
}
