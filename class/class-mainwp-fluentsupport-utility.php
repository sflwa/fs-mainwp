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
		return $mainWPFluentSupportExtensionActivator->get_child_file();
	}
    
    /**
     * Get Websites
     * Gets all child sites through the 'mainwp_getsites' filter.
     * * 🔑 FINAL FIX: Pass the plugin file path for BOTH the file argument and 
     * the childKey argument. This is the required workaround for self-developed 
     * extensions where the official key is set to 'false'.
     */
	public static function get_websites( $site_id = null ) {
		global $mainWPFluentSupportExtensionActivator;
        $plugin_file = $mainWPFluentSupportExtensionActivator->get_child_file();
		return apply_filters( 'mainwp_getsites', $plugin_file, $plugin_file, $site_id, false );
	}
    
    // ... (Add other utility methods here if needed)
}
