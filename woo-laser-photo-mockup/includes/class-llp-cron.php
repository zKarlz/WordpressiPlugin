<?php
/**
 * Cron tasks such as purging old assets.
 */
class LLP_Cron {
    use LLP_Singleton;

    protected function __construct() {
        add_action( 'init', [ $this, 'schedule' ] );
        add_action( 'llp_daily_purge', [ $this, 'daily_purge' ] );
    }

    /**
     * Schedule daily event.
     */
    public function schedule() {
        if ( ! wp_next_scheduled( 'llp_daily_purge' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'llp_daily_purge' );
        }
    }

    /**
     * Purge assets older than retention days.
     */
    public function daily_purge() {
        $retention = LLP_Settings::get( 'retention_days', 30 );
        $upload    = wp_upload_dir();
        $base      = trailingslashit( $upload['basedir'] ) . LLP_Storage::BASE_DIR;
        if ( ! is_dir( $base ) ) {
            return;
        }
        $threshold = time() - ( DAY_IN_SECONDS * $retention );
        $used       = LLP_Order::instance()->get_referenced_asset_ids();
        foreach ( glob( $base . '/*', GLOB_ONLYDIR ) as $dir ) {
            $asset_id = basename( $dir );
            if ( in_array( $asset_id, $used, true ) ) {
                continue;
            }
            if ( filemtime( $dir ) < $threshold ) {
                $this->rrmdir( $dir );
            }
        }
    }

    /**
     * Recursively remove directory.
     */
    private function rrmdir( $dir ) {
        foreach ( glob( $dir . '/*' ) as $file ) {
            if ( is_dir( $file ) ) {
                $this->rrmdir( $file );
            } else {
                unlink( $file );
            }
        }
        rmdir( $dir );
    }
}
