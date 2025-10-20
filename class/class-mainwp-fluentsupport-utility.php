<?php
/**
 * MainWP FluentSupport Utility
 *
 * This class provides utility methods for the MainWP FluentSupport Extension.
 * It is structured to align with MainWP's recommended development pattern.
 */

class MainWP_FluentSupport_Utility {

    /**
     * @var MainWP_FluentSupport_Utility
     */
    private static $instance = null;

    /**
     * Get class instance.
     * @return MainWP_FluentSupport_Utility
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    public function __construct() {
        // No specific actions needed in the constructor for now.
    }

    /**
     * Method to get the MainWP Dashboard Directory path.
     * * This is crucial for fixing pathing issues.
     * * @return string
     */
    public static function get_mainwp_dir() {
        // MainWP uses this constant to define its directory.
        if ( defined( 'MAINWP_DIR' ) ) {
            return trailingslashit( MAINWP_DIR ) . 'widgets/';
        } else {
            return '';
        }
    }

    /**
     * Get the current file path.
     * * Used for retrieving the correct plugin file path after installation.
     * * @return string
     */
    public static function get_file_name() {
        return dirname( dirname( __FILE__ ) ) . '/mainwp-fluentsupport.php';
    }
}
