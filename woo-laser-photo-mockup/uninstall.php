<?php
/**
 * Uninstall handler.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Remove plugin options.
delete_option( 'llp_settings' );

// Optionally delete uploads directory if option set.
$delete_assets = get_option( 'llp_delete_assets_on_uninstall', false );
if ( $delete_assets ) {
    require_once __DIR__ . '/traits/trait-singleton.php';
    require_once __DIR__ . '/includes/class-llp-storage.php';

    $dir = LLP_Storage::instance()->base_dir();
    if ( is_dir( $dir ) ) {
        // Recursively remove files.
        $it    = new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS );
        $files = new RecursiveIteratorIterator( $it, RecursiveIteratorIterator::CHILD_FIRST );
        foreach ( $files as $file ) {
            if ( $file->isDir() ) {
                rmdir( $file->getRealPath() );
            } else {
                unlink( $file->getRealPath() );
            }
        }
        rmdir( $dir );
    }
}
