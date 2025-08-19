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
        $wp_file = wp_check_filetype( $file['name'] );
        $ext     = strtolower( $wp_file['ext'] );

        if ( ! in_array( $ext, $allowed, true ) || empty( $wp_file['type'] ) ) {
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
