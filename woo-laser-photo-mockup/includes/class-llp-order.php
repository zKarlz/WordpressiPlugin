<?php
/**
 * Order and email integration.
 */
class LLP_Order {
    use LLP_Singleton;

    protected function __construct() {
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'add_order_line_item' ], 10, 4 );
        add_action( 'woocommerce_email_after_order_table', [ $this, 'email_thumbnail' ], 10, 4 );
    }

    /**
     * Persist meta to order item.
     */
    public function add_order_line_item( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['llp_asset_id'] ) ) {
            $item->add_meta_data( '_llp_asset_id', $values['llp_asset_id'], true );
        }
        if ( isset( $values['llp_thumb_url'] ) ) {
            $item->add_meta_data( '_llp_thumb_url', $values['llp_thumb_url'], true );
        }
    }

    /**
     * Show thumbnail in emails.
     */
    public function email_thumbnail( $order, $sent_to_admin, $plain_text, $email ) {
        if ( $plain_text ) {
            return;
        }
        foreach ( $order->get_items() as $item ) {
            $thumb = $item->get_meta( '_llp_thumb_url', true );
            if ( $thumb ) {
                echo '<p><img src="' . esc_url( $thumb ) . '" alt="" style="max-width:80px;" /></p>';
            }
        }
    }
}
