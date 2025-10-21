<?php
/**
 * MainWP FluentSupport Utility
 */

namespace MainWP\Extensions\FluentSupport;

class MainWP_FluentSupport_Utility {

	private static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
    
    // Get the correct filename for MainWP hooks
	public static function get_file_name() {
		global $mainWPFluentSupportExtensionActivator;
		return $mainWluentSupportExtensionActivator->get_child_file();
	}
    
    /**
     * Get Websites
     * Gets all child sites through the 'mainwp_getsites' filter.
     * 🔑 FIX: Passing the plugin file path for the key argument. This is often required for 
     * non-commercial/self-developed extensions to correctly register with MainWP filters 
     * and retrieve the site list when the default child key is false or empty.
     */
	public static function get_websites( $site_id = null ) {
		global $mainWPFluentSupportExtensionActivator;
        
        $plugin_file = $mainWPFluentSupportExtensionActivator->get_child_file();
        
        // Pass the plugin file path for both the file and the key arguments.
		return apply_filters( 'mainwp_getsites', $plugin
