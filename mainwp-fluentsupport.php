<?php
/**
 * Plugin Name: MainWP FluentSupport Extension
 * Plugin URI:  https://mainwp.dev/
 * Description: Integrates FluentSupport ticket data from a single "Support Site" into the MainWP Dashboard.
 * Version:     1.1.0
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

    private static $instance = null;
    public $plugin_slug = 'mainwp-fluentsupport';
    public $plugin_dir;

    public function __construct() {
        $this->plugin_dir = plugin_dir_path( __FILE__ );
        
        add_action( 'mainwp_ext_load', array( $this, 'init' ) );
    }

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init() {
        if ( ! class_exists( 'MainWP_Admin_Settings' ) ) {
            return; // MainWP Dashboard not active
        }
        
        $this->includes();
        MainWP_FluentSupport_Admin::get_instance();

        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function includes() {
        if ( file_exists( $this->plugin_dir . 'class/class-mainwp-fluentsupport-admin.php' ) ) {
            include_once $this->plugin_dir . 'class/class-mainwp-fluentsupport-admin.php';
        }
    }

    public function enqueue_scripts( $hook ) {
        if ( isset( $_GET['page'] ) && $_GET['page'] === $this->plugin_slug ) {
            wp_enqueue_script( 
                $this->plugin_slug . '-js', 
                plugin_dir_url( __FILE__ ) . 'js/mainwp-fluentsupport.js', 
                array( 'jquery' ), 
                '1.1.0', 
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

MainWP_FluentSupport_Extension::get_instance();
