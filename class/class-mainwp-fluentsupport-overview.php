<?php
/**
 * MainWP FluentSupport Overview
 * Handles the main extension page and tabs.
 */

namespace MainWP\Extensions\FluentSupport;

class MainWP_FluentSupport_Overview {

    private static $instance = null;
    private $plugin_slug = 'mainwp-fluentsupport';

    public static function get_instance() {
        if ( null == self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // No hooks here, as the Activator controls the rendering call
    }

    /**
     * Renders the tabbed extension page content (called by the Activator).
     */
    public function render_tabs() {
        $current_tab = 'overview';

        // MainWP uses the Extensions-SLUG format for its internal menu page
        $base_page_slug = 'Extensions-Mainwp-FluentSupport';
        
        if ( isset( $_GET['tab'] ) ) {
            if ( $_GET['tab'] == 'overview' ) {
                $current_tab = 'overview';
            } elseif ( $_GET['tab'] == 'settings' ) {
                $current_tab = 'settings';
            }
        }
        
        // 🔑 Start MainWP Wrapper
        do_action( 'mainwp_pageheader_extensions', MAINWP_FLUENTSUPPORT_PLUGIN_FILE );

        ?>
		<div class="ui labeled icon inverted menu mainwp-sub-submenu" id="mainwp-fluentsupport-menu">
			<a href="admin.php?page=<?php echo $base_page_slug; ?>&tab=overview" class="item <?php echo ( $current_tab == 'overview' ) ? 'active' : ''; ?>"><i class="tasks icon"></i> <?php esc_html_e( 'Tickets Overview', 'mainwp-fluentsupport' ); ?></a>
			<a href="admin.php?page=<?php echo $base_page_slug; ?>&tab=settings" class="item <?php echo ( $current_tab == 'settings' || $current_tab == '' ) ? 'active' : ''; ?>"><i class="file alternate outline icon"></i> <?php esc_html_e( 'Settings', 'mainwp-fluentsupport' ); ?></a>
		</div>

        <div id="mainwp-fluentsupport-extension" class="wrap">
            <div id="mainwp-fluentsupport-message" style="display:none; margin: 10px 0;"></div>
            
            <?php 
            // Call the correct rendering method from the Admin class
            if ( $current_tab == 'settings' ) {
                MainWP_FluentSupport_Admin::get_instance()->render_settings_tab();
            } else {
                MainWP_FluentSupport_Admin::get_instance()->render_overview_tab();
            }
            ?>
        </div>
        <?php
        
        // 🔑 End MainWP Wrapper
        do_action( 'mainwp_pagefooter_extensions', MAINWP_FLUENTSUPPORT_PLUGIN_FILE );
    }
}
