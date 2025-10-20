<?php
/**
 * MainWP FluentSupport Admin Class
 */

class MainWP_FluentSupport_Admin {

    private static $instance = null;
    private $plugin_slug = 'mainwp-fluentsupport';

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Add the submenu item
        add_action( 'mainwp_getsubpages_sites', array( $this, 'admin_menu' ) );

        // AJAX handler for fetching tickets
        add_action( 'wp_ajax_mainwp_fluentsupport_fetch_tickets', array( $this, 'ajax_fetch_tickets' ) );

        // MainWP filter hook that executes code on the CHILD SITE
        add_filter( 'mainwp_site_actions_fluent_support_tickets', array( $this, 'get_child_site_tickets' ), 10, 2 );
    }

    /**
     * Add the "FluentSupport" subpage under the MainWP "Sites" menu.
     */
    public function admin_menu( $subpages ) {
        $subpages[] = array(
            'title'      => 'FluentSupport',
            'slug'       => $this->plugin_slug,
            'func'       => array( $this, 'render_page' ),
            'menu_title' => 'FluentSupport Tickets',
        );
        return $subpages;
    }

    /**
     * Render the main extension page content (The Dashboard UI).
     */
    public function render_page() {
        ?>
        <div id="mainwp-fluentsupport-extension" class="wrap">
            <h2>FluentSupport Tickets Overview</h2>
            <div class="mainwp-notice mainwp-notice-blue">
                Click the button below to securely fetch the latest ticket counts from all connected child sites where FluentSupport is active.
            </div>
            
            <p><button id="mainwp-fluentsupport-fetch-btn" class="button button-primary">Fetch All Ticket Data</button></p>
            
            <div id="mainwp-fluentsupport-message" style="display:none; margin: 10px 0;"></div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="30%">Site</th>
                        <th width="20%">Open Tickets</th>
                        <th width="20%">New Tickets</th>
                        <th width="30%">Last Updated Ticket</th>
                    </tr>
                </thead>
                <tbody id="fluentsupport-ticket-data">
                    <tr id="initial-load-row"><td colspan="4">Click "Fetch All Ticket Data" to run a sync and retrieve data.</td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * 🟢 AJAX Handler (Runs on MainWP Dashboard)
     * Kicks off the remote execution on all child sites.
     */
    public function ajax_fetch_tickets() {
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        // Get all connected websites (MainWP core function)
        global $mainwp;
        $websites = $mainwp->get_websites_current_user();
        
        $sites_to_check = array();
        foreach ( $websites as $website ) {
            $sites_to_check[] = $website;
        }

        if ( empty( $sites_to_check ) ) {
            wp_send_json_success( array( 'html' => '<tr><td colspan="4">No connected sites found.</td></tr>' ) );
        }

        // This function securely performs the action ('fluent_support_tickets') 
        // on all child sites and collects the results.
        $results = apply_filters( 'mainwp_do_actions_on_child_sites', 'fluent_support_tickets', $sites_to_check );
        
        $html_output = '';
        foreach ( $sites_to_check as $website ) {
            $site_id = $website->id;
            $site_name = $website->name;
            $result = isset( $results[ $site_id ] ) ? $results[ $site_id ] : null;
            
            if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] === true ) {
                // Success: Display data
                $html_output .= '
                    <tr data-siteid="' . esc_attr( $site_id ) . '">
                        <td><a href="' . esc_url( $website->url ) . '" target="_blank">' . esc_html( $site_name ) . '</a></td>
                        <td><strong>' . intval( $result['open_tickets'] ) . '</strong></td>
                        <td>' . intval( $result['new_tickets'] ) . '</td>
                        <td>' . esc_html( $result['last_updated'] ) . '</td>
                    </tr>';
            } else {
                // Error: Site inaccessible or plugin missing
                $error_message = 'Site inaccessible or FluentSupport not found.';
                if ( is_array( $result ) && isset( $result['error'] ) ) {
                    $error_message = esc_html( $result['error'] );
                }
                
                $html_output .= '
                    <tr class="error-row" data-siteid="' . esc_attr( $site_id ) . '">
                        <td>' . esc_html( $site_name ) . '</td>
                        <td colspan="3">' . $error_message . '</td>
                    </tr>';
            }
        }
        
        wp_send_json_success( array( 'html' => $html_output ) );
    }

    /**
     * 🟢 CORE INTEGRATION LOGIC (Runs on the MainWP CHILD SITE)
     * This function is triggered securely by the mainwp_do_actions_on_child_sites filter.
     * * @param array $data Existing data array (empty for this action).
     * @param array $website MainWP website object (ignored here, but useful for context).
     * @return array Ticket summary data or error.
     */
    public function get_child_site_tickets( $data, $website ) {
        // FluentSupport check: uses a simple check for its internal API function
        if ( ! function_exists( 'FluentSupportApi' ) ) {
            return array( 'error' => 'FluentSupport is not installed/active.' );
        }

        try {
            // Get the FluentSupport API Handler
            $ticket_api = FluentSupportApi( 'tickets' );

            // 1. Get total open tickets (status_type: open includes new and active)
            $open_tickets = $ticket_api->getTickets( array( 
                'per_page' => 1, 
                'filters' => array( 'status_type' => 'open' )
            ) );
            $open_count = isset( $open_tickets['total'] ) ? $open_tickets['total'] : 0;

            // 2. Get total 'new' tickets (status_type: new)
            $new_tickets = $ticket_api->getTickets( array( 
                'per_page' => 1, 
                'filters' => array( 'status_type' => 'new' ) 
            ) );
            $new_count = isset( $new_tickets['total'] ) ? $new_tickets['total'] : 0;
            
            // 3. Get last updated time from the latest ticket
            $latest_tickets = $ticket_api->getTickets( array( 
                'per_page' => 1, 
                'order_by' => 'updated_at',
                'order_type' => 'DESC'
            ) );
            // Format the timestamp for better readability on the dashboard
            $last_updated = 'N/A';
            if ( isset( $latest_tickets['tickets'][0]['updated_at'] ) ) {
                $last_updated_timestamp = strtotime( $latest_tickets['tickets'][0]['updated_at'] );
                $last_updated = date( 'Y-m-d H:i:s', $last_updated_timestamp ); 
            }

            // Return the summary data back to the MainWP Dashboard
            return array(
                'open_tickets'   => $open_count,
                'new_tickets'    => $new_count,
                'last_updated'   => $last_updated,
                'success'        => true,
            );

        } catch ( Exception $e ) {
            // Handle any exceptions during API call
            return array( 'error' => 'FluentSupport Error: ' . $e->getMessage() );
        }
    }
}
