<?php
/**
 * Image rendering and compositing.
 */
class LLP_Renderer {
    use LLP_Singleton;

    /**
     * Generate composite image and thumbnail.
     *
     * @param string $asset_id Asset identifier.
     * @param int    $variation_id Product variation ID.
     * @param array  $transform Transform parameters.
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

        // Parse transform for scale/position/rotation.
        $scale    = 1;
        $rotation = 0;
        $position = [ 'x' => 0, 'y' => 0 ];
        if ( is_string( $transform ) ) {
            if ( preg_match( '/scale\((-?[0-9\.]+)\)/', $transform, $m ) ) {
                $scale = floatval( $m[1] );
            }
            if ( preg_match( '/translate\((-?[0-9\.]+)\s*,\s*(-?[0-9\.]+)\)/', $transform, $m ) ) {
                $position['x'] = floatval( $m[1] );
                $position['y'] = floatval( $m[2] );
            }
            if ( preg_match( '/rotate\((-?[0-9\.]+)\)/', $transform, $m ) ) {
                $rotation = floatval( $m[1] );
            }
        } elseif ( is_array( $transform ) ) {
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
        switch ( $info[2] ) {
            case IMAGETYPE_JPEG:
                $base = imagecreatefromjpeg( $base_path );
                break;
            case IMAGETYPE_GIF:
                $base = imagecreatefromgif( $base_path );
                break;
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
            switch ( $mask_info[2] ) {
                case IMAGETYPE_JPEG:
                    $mask = imagecreatefromjpeg( $mask_path );
                    break;
                case IMAGETYPE_GIF:
                    $mask = imagecreatefromgif( $mask_path );
                    break;
                default:
                    $mask = imagecreatefrompng( $mask_path );
                    break;
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
}
