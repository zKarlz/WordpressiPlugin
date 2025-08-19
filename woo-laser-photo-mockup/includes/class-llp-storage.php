<?php
/**
 * Handles file storage.
 */
class LLP_Storage {
    use LLP_Singleton;

    const BASE_DIR = 'llp-private';
    const URL_TTL  = 300; // seconds

    protected function __construct() {
        add_action( 'wp_ajax_llp_asset', [ $this, 'serve_file' ] );
        add_action( 'wp_ajax_nopriv_llp_asset', [ $this, 'serve_file' ] );
    }

    /**
     * Store an uploaded file to private directory.
     */
    public function store_upload( $file ) {
        $asset_id = wp_generate_uuid4();
        $this->ensure_asset_dir( $asset_id );
        $dir      = $this->asset_dir( $asset_id );

        $wp_file = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        if ( empty( $wp_file['ext'] ) || empty( $wp_file['type'] ) ) {
            return new WP_Error( 'mime', __( 'File type not allowed', 'llp' ) );
        }

        $finfo = finfo_open( FILEINFO_MIME_TYPE );
        $mime  = $finfo ? finfo_file( $finfo, $file['tmp_name'] ) : '';
        if ( $finfo ) {
            finfo_close( $finfo );
        }

        if ( $mime !== $wp_file['type'] ) {
            return new WP_Error( 'mime', __( 'File type mismatch', 'llp' ) );
        }

        $ext  = strtolower( $wp_file['ext'] );
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
        $base = $this->base_dir();
        if ( ! file_exists( $base ) ) {
            wp_mkdir_p( $base );
        }
        $htaccess = $base . '.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            file_put_contents( $htaccess, "Deny from all\n" );
        }
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
     * Get signed URLs for asset files.
     *
     * @param string $asset_id Asset identifier.
     * @param int    $expires   URL lifetime in seconds.
     * @return array
     */
    public function get_asset_urls( $asset_id, $expires = self::URL_TTL ) {
        $paths = $this->get_asset_paths( $asset_id );
        $urls  = [];

        if ( ! empty( $paths['original'] ) && file_exists( $paths['original'] ) ) {
            $urls['original'] = $this->url_for( $asset_id, basename( $paths['original'] ), $expires );
        }

        if ( file_exists( $paths['composite'] ) ) {
            $urls['composite'] = $this->url_for( $asset_id, 'composite', $expires );
        }

        if ( file_exists( $paths['thumb'] ) ) {
            $urls['thumb'] = $this->url_for( $asset_id, 'thumb', $expires );
        }

        return $urls;
    }

    /**
     * Build asset directory.
     */
    private function base_dir() {
        return trailingslashit( WP_CONTENT_DIR ) . self::BASE_DIR . '/';
    }

    private function asset_dir( $asset_id ) {
        return $this->base_dir() . $asset_id . '/';
    }

    /**
     * Get URL for file.
     */
    public function url_for( $asset_id, $file, $expires = self::URL_TTL ) {
        $file = $this->normalize_file_name( $file );
        $expires_at = time() + $expires;
        $token      = $this->generate_token( $asset_id, $file, $expires_at );
        $args       = [
            'action'   => 'llp_asset',
            'asset_id' => $asset_id,
            'file'     => $file,
            'expires'  => $expires_at,
            'token'    => $token,
        ];
        return add_query_arg( $args, admin_url( 'admin-ajax.php' ) );
    }

    private function normalize_file_name( $file ) {
        switch ( $file ) {
            case 'composite':
                return 'composite.png';
            case 'thumb':
                return 'thumb.jpg';
            default:
                return basename( $file );
        }
    }

    private function generate_token( $asset_id, $file, $expires ) {
        return hash_hmac( 'sha256', $asset_id . '|' . $file . '|' . $expires, wp_salt( 'llp_storage' ) );
    }

    public function serve_file() {
        $asset_id = sanitize_text_field( $_GET['asset_id'] ?? '' );
        $file     = sanitize_file_name( $_GET['file'] ?? '' );
        $expires  = absint( $_GET['expires'] ?? 0 );
        $token    = sanitize_text_field( $_GET['token'] ?? '' );

        if ( ! $asset_id || ! $file || time() > $expires ) {
            wp_die( __( 'Invalid request', 'llp' ), '', 403 );
        }

        $expected = $this->generate_token( $asset_id, $file, $expires );
        if ( ! hash_equals( $expected, $token ) ) {
            wp_die( __( 'Invalid signature', 'llp' ), '', 403 );
        }

        $paths = $this->get_asset_paths( $asset_id );
        $path  = $paths['dir'] . $file;
        if ( ! file_exists( $path ) ) {
            wp_die( __( 'File not found', 'llp' ), '', 404 );
        }

        $mime = wp_check_filetype( $path );
        if ( $mime && ! headers_sent() ) {
            header( 'Content-Type: ' . $mime['type'] );
        }
        readfile( $path );
        exit;
    }
}
