<?php
/**
 * Image rendering and compositing.
 */
class LLP_Renderer {
    use LLP_Singleton;

    /**
     * Generate composite image and thumbnail.
     *
     * @param string $asset_id   Asset identifier.
     * @param int    $variation_id Product variation ID.
     * @param string|array $transform Transform parameters as JSON string or array.
     * @return array|WP_Error
     */
    public function generate_composite( $asset_id, $variation_id, $transform ) {
        $storage = LLP_Storage::instance();
        $paths   = $storage->get_asset_paths( $asset_id );
        if ( empty( $paths ) || ! file_exists( $paths['original'] ) ) {
            return new WP_Error( 'missing', __( 'Original upload not found', 'llp' ) );
        }
        $base_id = get_post_meta( $variation_id, '_llp_base_image_id', true );
        $base_path = $base_id ? get_attached_file( $base_id ) : '';
        if ( ! $base_path || ! file_exists( $base_path ) ) {
            return new WP_Error( 'missing_base', __( 'Base image missing', 'llp' ) );
        }
        $bounds = get_post_meta( $variation_id, '_llp_bounds', true );
        $bounds = $bounds ? json_decode( $bounds, true ) : [ 'x' => 0, 'y' => 0, 'width' => 100, 'height' => 100, 'rotation' => 0 ];

        // Parse transform JSON for scale/position/rotation.
        $scale    = 1;
        $rotation = 0;
        $position = [ 'x' => 0, 'y' => 0 ];
        if ( is_string( $transform ) ) {
            $decoded = json_decode( $transform, true );
            if ( json_last_error() === JSON_ERROR_NONE ) {
                $transform = $decoded;
            } else {
                $transform = [];
            }
        }
        if ( is_array( $transform ) ) {
            $scale    = isset( $transform['scale'] ) ? floatval( $transform['scale'] ) : 1;
            $rotation = isset( $transform['rotation'] ) ? floatval( $transform['rotation'] ) : 0;
            if ( isset( $transform['position'] ) && is_array( $transform['position'] ) ) {
                $position['x'] = floatval( $transform['position']['x'] ?? 0 );
                $position['y'] = floatval( $transform['position']['y'] ?? 0 );
            }
        }

        // Detect base mime type and create image accordingly.
        $info = getimagesize( $base_path );
        if ( ! $info ) {
            return new WP_Error( 'img', __( 'Could not read base image', 'llp' ) );
        }
        $mime = $info['mime'] ?? '';
        switch ( $mime ) {
            case 'image/jpeg':
            case 'image/pjpeg':
                $base = imagecreatefromjpeg( $base_path );
                break;
            case 'image/gif':
                $base = imagecreatefromgif( $base_path );
                break;
            case 'image/webp':
                $base = function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $base_path ) : false;
                break;
            case 'image/png':
            default:
                $base = imagecreatefrompng( $base_path );
                break;
        }

        $user = imagecreatefromstring( file_get_contents( $paths['original'] ) );
        if ( ! $base || ! $user ) {
            return new WP_Error( 'img', __( 'Could not create images', 'llp' ) );
        }

        // Resize user image to bounds.
        $dst_w = intval( $bounds['width'] * $scale );
        $dst_h = intval( $bounds['height'] * $scale );
        $tmp   = imagecreatetruecolor( $dst_w, $dst_h );
        imagesavealpha( $tmp, true );
        imagealphablending( $tmp, false );
        imagecopyresampled( $tmp, $user, 0, 0, 0, 0, $dst_w, $dst_h, imagesx( $user ), imagesy( $user ) );

        // Rotate if needed.
        $total_rotation = floatval( $bounds['rotation'] ) + $rotation;
        if ( 0 !== $total_rotation ) {
            $tmp = imagerotate( $tmp, -$total_rotation, 0 );
        }

        // Apply mask if defined.
        $mask_id   = get_post_meta( $variation_id, '_llp_mask_image_id', true );
        $mask_path = $mask_id ? get_attached_file( $mask_id ) : '';
        if ( $mask_path && file_exists( $mask_path ) ) {
            $mask_info = getimagesize( $mask_path );
            $mask      = false;
            if ( $mask_info ) {
                $mask_mime = $mask_info['mime'] ?? '';
                switch ( $mask_mime ) {
                    case 'image/jpeg':
                    case 'image/pjpeg':
                        $mask = imagecreatefromjpeg( $mask_path );
                        break;
                    case 'image/gif':
                        $mask = imagecreatefromgif( $mask_path );
                        break;
                    case 'image/webp':
                        $mask = function_exists( 'imagecreatefromwebp' ) ? imagecreatefromwebp( $mask_path ) : false;
                        break;
                    case 'image/png':
                    default:
                        $mask = imagecreatefrompng( $mask_path );
                        break;
                }
            }
            if ( $mask ) {
                $resized = imagecreatetruecolor( imagesx( $tmp ), imagesy( $tmp ) );
                imagecopyresampled( $resized, $mask, 0, 0, 0, 0, imagesx( $tmp ), imagesy( $tmp ), imagesx( $mask ), imagesy( $mask ) );
                $tmp = $this->apply_mask( $tmp, $resized );
                imagedestroy( $mask );
                imagedestroy( $resized );
            }
        }

        // Merge into base.
        $dst_x = intval( $bounds['x'] + $position['x'] );
        $dst_y = intval( $bounds['y'] + $position['y'] );
        imagecopy( $base, $tmp, $dst_x, $dst_y, 0, 0, imagesx( $tmp ), imagesy( $tmp ) );

        // Save composite.
        $storage->ensure_asset_dir( $asset_id );
        imagepng( $base, $paths['composite'] );

        // Create thumbnail.
        $thumb_w = 200;
        $thumb_h = 200;
        $thumb   = imagecreatetruecolor( $thumb_w, $thumb_h );
        imagecopyresampled( $thumb, $base, 0, 0, 0, 0, $thumb_w, $thumb_h, imagesx( $base ), imagesy( $base ) );
        imagejpeg( $thumb, $paths['thumb'], 90 );

        imagedestroy( $base );
        imagedestroy( $user );
        imagedestroy( $tmp );
        imagedestroy( $thumb );

        return [
            'asset_id'  => $asset_id,
            'composite' => $storage->url_for( $asset_id, 'composite' ),
            'thumb'     => $storage->url_for( $asset_id, 'thumb' ),
        ];
    }

    /**
     * Apply alpha mask to image.
     *
     * @param resource $image Image resource to mask.
     * @param resource $mask  Mask image resource.
     * @return resource
     */
    protected function apply_mask( $image, $mask ) {
        $width  = imagesx( $image );
        $height = imagesy( $image );
        imagesavealpha( $image, true );
        imagealphablending( $image, false );

        for ( $x = 0; $x < $width; $x++ ) {
            for ( $y = 0; $y < $height; $y++ ) {
                $mask_color = imagecolorsforindex( $mask, imagecolorat( $mask, $x, $y ) );
                $img_color  = imagecolorsforindex( $image, imagecolorat( $image, $x, $y ) );
                $alpha      = $mask_color['alpha'];
                $color      = imagecolorallocatealpha( $image, $img_color['red'], $img_color['green'], $img_color['blue'], $alpha );
                imagesetpixel( $image, $x, $y, $color );
            }
        }

        return $image;
    }

    /**
     * Regenerate composites for all items in an order.
     *
     * @param int $order_id Order ID.
     * @return true|WP_Error
     */
    public function rerender_order( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return new WP_Error( 'invalid_order', __( 'Order not found', 'llp' ) );
        }

        $storage = LLP_Storage::instance();

        foreach ( $order->get_items() as $item ) {
            $asset_id     = $item->get_meta( '_llp_asset_id', true );
            $transform    = $item->get_meta( '_llp_transform', true );
            $variation_id = $item->get_variation_id();

            if ( ! $asset_id || ! $transform || ! $variation_id ) {
                continue;
            }

            $transform_arr = json_decode( $transform, true );
            $result        = $this->generate_composite( $asset_id, $variation_id, $transform_arr );
            if ( is_wp_error( $result ) ) {
                continue;
            }

            $item->update_meta_data( '_llp_thumb_url', $result['thumb'] ?? '' );
            $item->update_meta_data( '_llp_composite_url', $result['composite'] ?? '' );
            $urls = $storage->get_asset_urls( $asset_id );
            if ( ! empty( $urls['original'] ) ) {
                $item->update_meta_data( '_llp_original_url', $urls['original'] );
            }
        }

        $order->save();

        return true;
    }
}
