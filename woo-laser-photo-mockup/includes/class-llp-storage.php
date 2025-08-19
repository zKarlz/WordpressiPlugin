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
        $ext     = pathinfo( $file['name'], PATHINFO_EXTENSION );
        $dest    = $dir . 'original.' . $ext;
        if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
            return new WP_Error( 'move', __( 'Could not move uploaded file', 'llp' ) );
        }
        $meta = [
            'asset_id' => $asset_id,
            'original_path' => $dest,
        ];
        file_put_contents( $dir . 'meta.json', wp_json_encode( $meta ) );
        return [
            'asset_id'    => $asset_id,
            'original'    => $this->url_for( $asset_id, 'original.' . $ext ),
            'original_sha256' => hash_file( 'sha256', $dest ),
        ];
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
