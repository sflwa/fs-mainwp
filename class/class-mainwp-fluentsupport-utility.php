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
     */
	public static function get_websites( $site_id = null ) {
		global $mainWPFluentSupportExtensionActivator;
		return apply_filters( 'mainwp_getsites', $mainWPFluentSupportExtensionActivator->get_child_file(), $mainWPFluentSupportExtensionActivator->get_child_key(), $site_id, false );
	}
    
    // ... (Add other utility methods here if needed)
}
