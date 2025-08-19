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

        $base = imagecreatefrompng( $base_path );
        $user = imagecreatefromstring( file_get_contents( $paths['original'] ) );
        if ( ! $base || ! $user ) {
            return new WP_Error( 'img', __( 'Could not create images', 'llp' ) );
        }

        // Resize user image to bounds.
        $dst_w = intval( $bounds['width'] );
        $dst_h = intval( $bounds['height'] );
        $tmp   = imagecreatetruecolor( $dst_w, $dst_h );
        imagecopyresampled( $tmp, $user, 0, 0, 0, 0, $dst_w, $dst_h, imagesx( $user ), imagesy( $user ) );

        // Rotate if needed.
        if ( ! empty( $bounds['rotation'] ) ) {
            $tmp = imagerotate( $tmp, -floatval( $bounds['rotation'] ), 0 );
        }

        // Merge into base.
        imagecopy( $base, $tmp, intval( $bounds['x'] ), intval( $bounds['y'] ), 0, 0, imagesx( $tmp ), imagesy( $tmp ) );

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
}
