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
     * * 🔑 FIX: Passing an empty string for the childKey to satisfy the filter requirement 
     * for non-commercial extensions, resolving the "Total Sites Found: 0" issue.
     */
	public static function get_websites( $site_id = null ) {
		global $mainWPFluentSupportExtensionActivator;
        // Use empty string '' instead of the default false returned by get_child_key()
		return apply_filters( 'mainwp_getsites', $mainWPFluentSupportExtensionActivator->get_child_file(), '', $site_id, false );
	}
    
    // ... (Add other utility methods here if needed)
}
