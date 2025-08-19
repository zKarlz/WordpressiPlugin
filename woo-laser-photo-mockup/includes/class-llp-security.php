<?php
/**
 * Security and validation helpers.
 */
class LLP_Security {
    use LLP_Singleton;

    /**
     * Validate user upload.
     */
    public function validate_upload( $file ) {
        if ( $file['error'] !== UPLOAD_ERR_OK ) {
            return new WP_Error( 'upload', __( 'Upload error', 'llp' ) );
        }
        $allowed = explode( ',', LLP_Settings::get( 'allowed_mimes', 'jpg,jpeg,png,webp' ) );
        $ext     = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
        if ( ! in_array( $ext, $allowed, true ) ) {
            return new WP_Error( 'mime', __( 'File type not allowed', 'llp' ) );
        }
        $max_size = LLP_Settings::get( 'max_file_size', 15 ) * 1024 * 1024;
        if ( $file['size'] > $max_size ) {
            return new WP_Error( 'size', __( 'File too large', 'llp' ) );
        }
        $info = getimagesize( $file['tmp_name'] );
        if ( false === $info ) {
            return new WP_Error( 'img', __( 'Invalid image', 'llp' ) );
        }
        return true;
    }
}
