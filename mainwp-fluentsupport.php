<?php
/*
  Plugin Name: MainWP FluentSupport Extension
  Plugin URI: https://mainwp.dev/
  Description: Integrates FluentSupport ticket data from a single "Support Site" into the MainWP Dashboard.
  Version: 1.1.4
  Author: Your Name
  Author URI: https://yourwebsite.com
 */

// 🔑 Use the required namespace structure
namespace MainWP\Extensions\FluentSupport;

if ( ! defined( 'MAINWP_FLUENTSUPPORT_PLUGIN_FILE' ) ) {
	define( 'MAINWP_FLUENTSUPPORT_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MAINWP_FLUENTSUPPORT_PLUGIN_DIR' ) ) {
	define( 'MAINWP_FLUENTSUPPORT_PLUGIN_DIR', plugin_dir_path( MAINWP_FLUENTSUPPORT_PLUGIN_FILE ) );
}

if ( ! defined( 'MAINWP_FLUENTSUPPORT_PLUGIN_URL' ) ) {
	define( 'MAINWP_FLUENTSUPPORT_PLUGIN_URL', plugin_dir_url( MAINWP_FLUENTSUPPORT_PLUGIN_FILE ) );
}

class MainWP_FluentSupport_Extension_Activator {

	protected $mainwpMainActivated = false;
	protected $childEnabled        = false;
	protected $childKey            = false;
	protected $childFile;
	protected $plugin_handle    = 'mainwp-fluentsupport';
	protected $product_id       = 'MainWP FluentSupport Extension';
	protected $software_version = '1.1.4';

	public function __construct() {
		$this->childFile = __FILE__;

		// 🔑 Critical for automatic class loading
		spl_autoload_register( array( $this, 'autoload' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_filter( 'mainwp_getextensions', array( &$this, 'get_this_extension' ) );
        
		$this->mainwpMainActivated = apply_filters( 'mainwp_activated_check', false );
		if ( $this->mainwpMainActivated !== false ) {
			$this->activate_this_plugin();
		} else {
			add_action( 'mainwp_activated', array( &$this, 'activate_this_plugin' ) );
		}

		add_action( 'admin_notices', array( &$this, 'admin_notices' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}
    
    // Autoload function
	public function autoload( $class_name ) {
		if ( 0 === strpos( $class_name, 'MainWP\Extensions\FluentSupport' ) ) {
			$class_name = str_replace( 'MainWP\Extensions\FluentSupport\\', '', $class_name );
		} else {
			return;
		}

		if ( 0 !== strpos( $class_name, 'MainWP_FluentSupport' ) ) {
			return;
		}
		$class_name = str_replace( '_', '-', strtolower( $class_name ) );
        // Resolve file path using WP_PLUGIN_DIR
		$class_file = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . str_replace( basename( __FILE__ ), '', plugin_basename( __FILE__ ) ) . 'class' . DIRECTORY_SEPARATOR . 'class-' . $class_name . '.php';
		
		if ( file_exists( $class_file ) ) {
			require_once $class_file;
		}
	}
    
    // Defines the extension for MainWP's extension manager
	public function get_this_extension( $pArray ) {
		$pArray[] = array(
			'plugin'     => __FILE__,
			'api'        => $this->plugin_handle,
			'mainwp'     => true,
			'callback'   => array( MainWP_FluentSupport_Overview::get_instance(), 'render_tabs' ), 
			'apiManager' => false, // 🔑 Set this to FALSE to bypass licensing management
            'cap'        => 'manage_options',
            'menu_title' => 'FluentSupport Tickets',
		);
		return $pArray;
	}

	public function activate_this_plugin() {
        // Ensures MainWP classes are initialized
		if ( function_exists( 'mainwp_current_user_can' ) && ! mainwp_current_user_can( 'extension', 'mainwp-fluentsupport' ) ) {
			return;
		}
        // Initializes core components
		MainWP_FluentSupport_Admin::get_instance();
        MainWP_FluentSupport_Overview::get_instance();
	}

	public function admin_notices() {
        // ... (standard admin notices logic)
	}

	public function activate() {
        // ... (standard activate logic)
	}

	public function deactivate() {
        // ... (standard deactivate logic)
	}
    
    // Utility getter methods needed by other classes
	public function get_child_key() {
		return $this->childKey;
	}

	public function get_child_file() {
		return $this->childFile;
	}

    // Enqueue scripts (needed here or in Admin class)
    public function enqueue_scripts( $hook ) {
        $plugin_slug = 'mainwp-fluentsupport';
        if ( isset( $_GET['page'] ) && ( $plugin_slug === $_GET['page'] || 'Extensions-Mainwp-FluentSupport' === $_GET['page'] ) ) {
            wp_enqueue_script( 
                $plugin_slug . '-js', 
                plugin_dir_url( __FILE__ ) . 'js/mainwp-fluentsupport.js', 
                array( 'jquery' ), 
                $this->software_version, 
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

// 🔑 Global instantiation, required by MainWP framework
global $mainWPFluentSupportExtensionActivator;
$mainWPFluentSupportExtensionActivator = new MainWP_FluentSupport_Extension_Activator();
