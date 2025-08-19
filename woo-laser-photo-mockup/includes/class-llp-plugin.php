<?php
/**
 * Main plugin bootstrap.
 */
class LLP_Plugin {
    use LLP_Singleton;

    /**
     * Setup hooks.
     */
    protected function __construct() {
        // Load textdomain.
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

        // Include required classes.
        $this->includes();

        // Initialise subsystems on init to ensure WooCommerce loaded.
        add_action( 'init', [ $this, 'init_subsystems' ] );
    }

    /**
     * Load textdomain.
     */
    public function load_textdomain() {
        load_plugin_textdomain( 'llp', false, dirname( plugin_basename( LLP_PLUGIN_FILE ) ) . '/languages' );
    }

    /**
     * Include class files.
     */
    private function includes() {
        require_once LLP_PLUGIN_DIR . 'includes/class-llp-settings.php';
        require_once LLP_PLUGIN_DIR . 'includes/class-llp-variation-fields.php';
        require_once LLP_PLUGIN_DIR . 'includes/class-llp-frontend.php';
        require_once LLP_PLUGIN_DIR . 'includes/class-llp-rest.php';
        require_once LLP_PLUGIN_DIR . 'includes/class-llp-renderer.php';
        require_once LLP_PLUGIN_DIR . 'includes/class-llp-order.php';
        require_once LLP_PLUGIN_DIR . 'includes/class-llp-storage.php';
        require_once LLP_PLUGIN_DIR . 'includes/class-llp-security.php';
        require_once LLP_PLUGIN_DIR . 'includes/class-llp-cron.php';
    }

    /**
     * Instantiate subsystems.
     */
    public function init_subsystems() {
        LLP_Settings::instance();
        LLP_Variation_Fields::instance();
        LLP_Frontend::instance();
        LLP_REST::instance();
        LLP_Order::instance();
        LLP_Storage::instance();
        LLP_Security::instance();
        LLP_Cron::instance();
    }
}
