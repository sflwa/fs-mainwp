<?php
/**
 * Plugin Name: MainWP FluentSupport Extension
 * Plugin URI:  https://mainwp.dev/
 * Description: Integrates FluentSupport ticket data from a single "Support Site" into the MainWP Dashboard.
 * Version:     1.1.2
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

class MainWP_FluentSupport_Extension_Activator {
    
    private $plugin_file; 
    
    public function __construct() {
        $this->plugin_file = __FILE__;
        
        // This is the CRITICAL line we are fixing
        add_filter( 'mainwp_getextensions', array( $this, 'get_extension_info' ) );
        
        add_action( 'mainwp_ext_init', array( $this, 'init_extension' ) );
    }

    // MainWP calls this filter to get the extension's basic info
    public function get_extension_info( $pArray = array() ) {
        $pArray[] = array( 
            'plugin'            => $this->plugin_file, 
            'api'               => '', 
            'mainwp'            => true,
            'callback'          => array( $this, 'mainwp_display_fluent_support' ),
            'callback_settings' => array( $this, 'mainwp_display_fluent_support' ),
            // 🔑 NEW: Add the capability requirement
            'cap'               => 'manage_options', 
            // 🔑 NEW: Use a standard menu slug MainWP recognizes for the menu display
            'menu_title'        => 'FluentSupport', 
        );
        return $pArray;
    }
    
    // ... (rest of the class remains the same) ...
    public function init_extension() {
        if ( file_exists( dirname( $this->plugin_file ) . '/class/class-mainwp-fluentsupport-admin.php' ) ) {
            require_once dirname( $this->plugin_file ) . '/class/class-mainwp-fluentsupport-admin.php';
        }
        
        MainWP_FluentSupport_Admin::get_instance();
        
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
    }

    public function mainwp_display_fluent_support() {
        if ( file_exists( dirname( $this->plugin_file ) . '/class/class-mainwp-fluentsupport-admin.php' ) ) {
            require_once dirname( $this->plugin_file ) . '/class/class-mainwp-fluentsupport-admin.php';
        }
        MainWP_FluentSupport_Admin::get_instance()->render_page();
    }
    
    public function enqueue_scripts( $hook ) {
        $plugin_slug = 'mainwp-fluentsupport';
        if ( isset( $_GET['page'] ) && $_GET['page'] === $plugin_slug ) {
            wp_enqueue_script( 
                $plugin_slug . '-js', 
                plugin_dir_url( __FILE__ ) . 'js/mainwp-fluentsupport.js', 
                array( 'jquery' ), 
                '1.1.2', 
                true 
            );
            
            wp_localize_script( $plugin_slug . '-js', 'mainwpFluentSupport', array(
                'ajaxurl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'mainwp-fluentsupport-nonce' ),
                'action'  => 'mainwp_fluentsupport_fetch_tickets'
            ) );

            wp_add_inline_style( 'mainwp-style', '#fluentsupport-ticket-data .loading-row { background: #f9f9f9; } #fluentsupport-ticket-data .error-row { background: #fee; color: red; }' );
        }
    }
}

new MainWP_FluentSupport_Extension_Activator();
