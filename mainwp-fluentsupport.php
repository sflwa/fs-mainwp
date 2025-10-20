<?php
/**
 * Plugin Name: MainWP FluentSupport Extension
 * Plugin URI:  https://mainwp.dev/
 * Description: Integrates FluentSupport ticket data from a single "Support Site" into the MainWP Dashboard.
 * Version:     1.1.3
 * Author:      Your Name
 * Author URI:  https://yourwebsite.com
 *
 * Requires at least: 4.0
 * Tested up to: 6.5
 * MainWP compatible: 4.5
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class MainWP_FluentSupport_Extension {

    // Unique private instance
    private static $instance = null;
    
    // Extension properties
    public $plugin_slug = 'mainwp-fluentsupport';
    public $plugin_dir;

    public function __construct() {
        $this->plugin_dir = plugin_dir_path( __FILE__ );
        
        // 🔑 Core MainWP hooks for extension recognition and initialization
        add_action( 'mainwp_ext_load', array( $this, 'init' ) );
        add_filter( 'mainwp_getextensions', array( $this, 'get_extension_info' ) );
        
        // This is the hook that correctly renders the page content
        add_action( 'mainwp_ext_init', array( $this, 'init_admin' ) );
    }

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        // Includes the main class that handles the UI and remote calls
        if ( file_exists( $this->plugin_dir . 'class/class-mainwp-fluentsupport-admin.php' ) ) {
            require_once $this->plugin_dir . 'class/class-mainwp-fluentsupport-admin.php';
        }
    }
    
    // Initializes the Admin Class after MainWP is fully loaded
    public function init_admin() {
        if ( class_exists( 'MainWP_FluentSupport_Admin' ) ) {
            MainWP_FluentSupport_Admin::get_instance();
        }
        // Enqueue scripts
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    // 🔑 Defines the extension for MainWP's extension manager
    public function get_extension_info( $pArray = array() ) {
        $pArray[] = array(
            'plugin'            => __FILE__,
            'api'               => '', // For non-commercial extensions
            'mainwp'            => true,
            'callback'          => array( MainWP_FluentSupport_Admin::get_instance(), 'render_page' ), // Use the Admin class method for rendering
            'callback_settings' => array( MainWP_FluentSupport_Admin::get_instance(), 'render_page' ),
            'cap'               => 'manage_options', // Fixes the permission error
            'menu_title'        => 'FluentSupport Tickets', // Title used in the MainWP submenu
            'menu_icon'         => 'dashicons-tickets-alt', // Optional: adds an icon
        );
        return $pArray;
    }

    public function enqueue_scripts( $hook ) {
        if ( isset( $_GET['page'] ) && $_GET['page'] === $this->plugin_slug ) {
            wp_enqueue_script(
                $this->plugin_slug . '-js',
                plugin_dir_url( __FILE__ ) . 'js/mainwp-fluentsupport.js',
                array( 'jquery' ),
                '1.1.3',
                true
            );

            wp_localize_script( $this->plugin_slug . '-js', 'mainwpFluentSupport', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mainwp-fluentsupport-nonce' ),
                'action'  => 'mainwp_fluentsupport_fetch_tickets'
            ) );

            wp_add_inline_style( 'mainwp-style', '#fluentsupport-ticket-data .loading-row { background: #f9f9f9; } #fluentsupport-ticket-data .error-row { background: #fee; color: red; }' );
        }
    }
}

// Ensure it runs after MainWP is ready
add_action( 'mainwp_loaded', 'mainwp_fluentsupport_load' );

function mainwp_fluentsupport_load() {
    MainWP_FluentSupport_Extension::get_instance();
}
