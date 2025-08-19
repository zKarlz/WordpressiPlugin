<?php
/**
 * Plugin Name: Woo Laser Photo Mockup
 * Description: Allows customers to upload and customize photos for laser-engraved wooden mockups.
 * Version: 1.0.0
 * Author: OpenAI Assistant
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Plugin constants.
define( 'LLP_PLUGIN_FILE', __FILE__ );
define( 'LLP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LLP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes.
require_once LLP_PLUGIN_DIR . 'traits/trait-singleton.php';
require_once LLP_PLUGIN_DIR . 'includes/class-llp-plugin.php';

// Bootstrap the plugin.
LLP_Plugin::instance();
