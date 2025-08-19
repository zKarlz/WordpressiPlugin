<?php
/**
 * Plugin settings.
 */
class LLP_Settings {
    use LLP_Singleton;

    const OPTION_KEY = 'llp_settings';

    /**
     * Register hooks.
     */
    protected function __construct() {
        add_action( 'admin_menu', [ $this, 'admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    /**
     * Add settings page under WooCommerce menu.
     */
    public function admin_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'Laser Photo Mockup', 'llp' ),
            __( 'Laser Photo Mockup', 'llp' ),
            'manage_woocommerce',
            'llp-settings',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register settings fields.
     */
    public function register_settings() {
        register_setting( 'llp_settings', self::OPTION_KEY, [ $this, 'sanitize' ] );

        add_settings_section( 'llp_general', __( 'General', 'llp' ), '__return_false', 'llp-settings' );

        add_settings_field(
            'allowed_mimes',
            __( 'Allowed MIME types', 'llp' ),
            [ $this, 'field_allowed_mimes' ],
            'llp-settings',
            'llp_general'
        );
        add_settings_field(
            'max_file_size',
            __( 'Max file size (MB)', 'llp' ),
            [ $this, 'field_max_file_size' ],
            'llp-settings',
            'llp_general'
        );
        add_settings_field(
            'retention_days',
            __( 'Retention days', 'llp' ),
            [ $this, 'field_retention_days' ],
            'llp-settings',
            'llp_general'
        );
    }

    /**
     * Sanitize options.
     */
    public function sanitize( $input ) {
        $output = [];
        $output['allowed_mimes'] = isset( $input['allowed_mimes'] ) ? sanitize_text_field( $input['allowed_mimes'] ) : 'jpg,jpeg,png,webp';
        $output['max_file_size'] = isset( $input['max_file_size'] ) ? absint( $input['max_file_size'] ) : 15;
        $output['retention_days'] = isset( $input['retention_days'] ) ? absint( $input['retention_days'] ) : 30;
        return $output;
    }

    /**
     * Render settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Laser Photo Mockup', 'llp' ); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields( 'llp_settings' );
                do_settings_sections( 'llp-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Field: allowed mimes.
     */
    public function field_allowed_mimes() {
        $options = get_option( self::OPTION_KEY );
        $value   = isset( $options['allowed_mimes'] ) ? esc_attr( $options['allowed_mimes'] ) : 'jpg,jpeg,png,webp';
        echo '<input type="text" name="' . self::OPTION_KEY . '[allowed_mimes]" value="' . $value . '" class="regular-text" />';
        echo '<p class="description">' . esc_html__( 'Comma separated list of allowed MIME extensions.', 'llp' ) . '</p>';
    }

    /**
     * Field: max file size.
     */
    public function field_max_file_size() {
        $options = get_option( self::OPTION_KEY );
        $value   = isset( $options['max_file_size'] ) ? absint( $options['max_file_size'] ) : 15;
        echo '<input type="number" name="' . self::OPTION_KEY . '[max_file_size]" value="' . $value . '" />';
    }

    /**
     * Field: retention days.
     */
    public function field_retention_days() {
        $options = get_option( self::OPTION_KEY );
        $value   = isset( $options['retention_days'] ) ? absint( $options['retention_days'] ) : 30;
        echo '<input type="number" name="' . self::OPTION_KEY . '[retention_days]" value="' . $value . '" />';
    }

    /**
     * Helper: get option.
     */
    public static function get( $key, $default = false ) {
        $options = get_option( self::OPTION_KEY, [] );
        return isset( $options[ $key ] ) ? $options[ $key ] : $default;
    }
}
