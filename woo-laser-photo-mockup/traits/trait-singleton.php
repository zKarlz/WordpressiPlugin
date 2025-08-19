<?php
/**
 * Simple singleton trait for plugin classes.
 */
trait LLP_Singleton {
    /**
     * Instance holder.
     *
     * @var static
     */
    protected static $instance = null;

    /**
     * Get class instance.
     *
     * @return static
     */
    public static function instance() {
        if ( null === static::$instance ) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    /**
     * Protect constructor from public access.
     */
    protected function __construct() {}

    /**
     * Disallow cloning.
     */
    private function __clone() {}

    /**
     * Disallow unserialization.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }
}
