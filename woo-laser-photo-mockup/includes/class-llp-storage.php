<?php
/**
 * Handles file storage.
 */
class LLP_Storage {
    use LLP_Singleton;

    const BASE_DIR = 'llp';

    /**
     * Store an uploaded file to private directory.
     */
    public function store_upload( $file ) {
        $asset_id = wp_generate_uuid4();
        $dir      = $this->asset_dir( $asset_id );
        wp_mkdir_p( $dir );

        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime  = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : '';
        if ( $finfo ) {
            finfo_close( $finfo );
        }

        $ext  = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        $dest = $dir . 'original.' . $ext;

        $processed = $this->reencode_image( $file['tmp_name'], $dest, $mime );
        if ( is_wp_error( $processed ) ) {
            return $processed;
        }

        @unlink( $file['tmp_name'] );

        $meta = [
            'asset_id' => $asset_id,
            'original_path' => $dest,
        ];
        file_put_contents( $dir . 'meta.json', wp_json_encode( $meta ) );

        return [
            'asset_id'        => $asset_id,
            'original'        => $this->url_for( $asset_id, 'original.' . $ext ),
            'original_sha256' => hash_file( 'sha256', $dest ),
        ];
    }

    /**
     * Re-encode image, strip metadata and normalise orientation.
     */
    private function reencode_image( $source, $dest, $mime ) {
        switch ( $mime ) {
            case 'image/jpeg':
                $image = imagecreatefromjpeg( $source );
                if ( ! $image ) {
                    return new WP_Error( 'img', __( 'Invalid image', 'llp' ) );
                }
                if ( function_exists( 'exif_read_data' ) ) {
                    $exif = @exif_read_data( $source );
                    if ( ! empty( $exif['Orientation'] ) ) {
                        switch ( (int) $exif['Orientation'] ) {
                            case 3:
                                $image = imagerotate( $image, 180, 0 );
                                break;
                            case 6:
                                $image = imagerotate( $image, -90, 0 );
                                break;
                            case 8:
                                $image = imagerotate( $image, 90, 0 );
                                break;
                        }
                    }
                }
                if ( false === imagejpeg( $image, $dest, 100 ) ) {
                    imagedestroy( $image );
                    return new WP_Error( 'encode', __( 'Could not save image', 'llp' ) );
                }
                imagedestroy( $image );
                break;
            case 'image/png':
                $image = imagecreatefrompng( $source );
                if ( ! $image ) {
                    return new WP_Error( 'img', __( 'Invalid image', 'llp' ) );
                }
                if ( false === imagepng( $image, $dest ) ) {
                    imagedestroy( $image );
                    return new WP_Error( 'encode', __( 'Could not save image', 'llp' ) );
                }
                imagedestroy( $image );
                break;
            case 'image/webp':
                if ( ! function_exists( 'imagecreatefromwebp' ) ) {
                    return new WP_Error( 'img', __( 'WebP not supported', 'llp' ) );
                }
                $image = imagecreatefromwebp( $source );
                if ( ! $image ) {
                    return new WP_Error( 'img', __( 'Invalid image', 'llp' ) );
                }
                if ( false === imagewebp( $image, $dest, 100 ) ) {
                    imagedestroy( $image );
                    return new WP_Error( 'encode', __( 'Could not save image', 'llp' ) );
                }
                imagedestroy( $image );
                break;
            default:
                return new WP_Error( 'mime', __( 'Unsupported image type', 'llp' ) );
        }

        return true;
    }

    /**
     * Ensure asset directory exists.
     */
    public function ensure_asset_dir( $asset_id ) {
        $dir = $this->asset_dir( $asset_id );
        if ( ! file_exists( $dir ) ) {
            wp_mkdir_p( $dir );
        }
    }

    /**
     * Get asset paths.
     */
    public function get_asset_paths( $asset_id ) {
        $dir = $this->asset_dir( $asset_id );
        $paths = [
            'dir'       => $dir,
            'original'  => glob( $dir . 'original.*' ) ? glob( $dir . 'original.*' )[0] : '',
            'composite' => $dir . 'composite.png',
            'thumb'     => $dir . 'thumb.jpg',
        ];
        return $paths;
    }

    /**
     * Build asset directory.
     */
    private function asset_dir( $asset_id ) {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['basedir'] ) . self::BASE_DIR . '/' . $asset_id . '/';
    }

    /**
     * Get URL for file.
     */
    public function url_for( $asset_id, $file ) {
        $upload = wp_upload_dir();
        return trailingslashit( $upload['baseurl'] ) . self::BASE_DIR . '/' . $asset_id . '/' . $file;
    }
}
