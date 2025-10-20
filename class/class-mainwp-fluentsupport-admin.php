<?php
/**
 * MainWP FluentSupport Admin Class - NEW ARCHITECTURE
 * Handles UI, settings, and communication with the single Support Site.
 */

class MainWP_FluentSupport_Admin {

    private static $instance = null;
    private $plugin_slug = 'mainwp-fluentsupport';
    private $support_site_option_key = 'mainwp_fluentsupport_site_id';

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Add the submenu item (Overview and Settings)
        add_filter( 'mainwp_getsubpages_sites', array( $this, 'admin_menu' ) );

        // AJAX handlers for fetching tickets and saving settings
        add_action( 'wp_ajax_mainwp_fluentsupport_fetch_tickets', array( $this, 'ajax_fetch_tickets' ) );
        add_action( 'wp_ajax_mainwp_fluentsupport_save_settings', array( $this, 'ajax_save_settings' ) );

        // MainWP filter hook that executes code on the single SUPPORT SITE
        add_filter( 'mainwp_site_actions_fluent_support_tickets_all', array( $this, 'get_support_site_tickets' ), 10, 2 );
    }

    /**
     * Get the configured Support Site ID.
     */
    private function get_support_site_id() {
        return get_option( $this->support_site_option_key, 0 );
    }

    /**
     * Add the "FluentSupport" subpage with two tabs: Overview and Settings.
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
     * Render the main extension page content (Handles tab switching).
     */
    public function render_page() {
        $current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'overview';
        ?>
        <div id="mainwp-fluentsupport-extension" class="wrap">
            <h2>FluentSupport Integration</h2>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=<?php echo $this->plugin_slug; ?>&tab=overview" class="nav-tab <?php echo ( $current_tab == 'overview' ) ? 'nav-tab-active' : ''; ?>">Overview</a>
                <a href="?page=<?php echo $this->plugin_slug; ?>&tab=settings" class="nav-tab <?php echo ( $current_tab == 'settings' ) ? 'nav-tab-active' : ''; ?>">Settings</a>
            </h2>
            
            <div id="mainwp-fluentsupport-message" style="display:none; margin: 10px 0;"></div>
            
            <?php 
            if ( $current_tab == 'settings' ) {
                $this->render_settings_tab();
            } else {
                $this->render_overview_tab();
            }
            ?>
        </div>
        <?php
    }

    /**
     * Renders the tab for configuring the single FluentSupport site.
     */
    private function render_settings_tab() {
        global $mainwp;
        // Get all connected sites to populate the dropdown
        $websites = $mainwp->get_websites_current_user();
        $current_site_id = $this->get_support_site_id();
        ?>
        <div class="mainwp-padd-cont">
            <h3>Support Site Configuration</h3>
            <p>Select the single child site where **FluentSupport** is installed and actively managing tickets.</p>
            
            <form method="post" id="mainwp-fluentsupport-settings-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fluentsupport_site_id">Support Site</label>
                        </th>
                        <td>
                            <select id="fluentsupport_site_id" name="fluentsupport_site_id" class="regular-text">
                                <option value="0">-- Select a Site --</option>
                                <?php foreach ( $websites as $website ) : ?>
                                    <option value="<?php echo esc_attr( $website->id ); ?>" <?php selected( $current_site_id, $website->id ); ?>>
                                        <?php echo esc_html( $website->name ); ?> (<?php echo esc_html( $website->url ); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">This site will be queried for all FluentSupport ticket data.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" id="mainwp-fluentsupport-save-settings-btn" class="button button-primary">Save Settings</button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Renders the main Overview tab.
     */
    private function render_overview_tab() {
        $support_site_id = $this->get_support_site_id();
        
        if ( empty( $support_site_id ) ) {
            echo '<div class="mainwp-notice mainwp-notice-red">Please go to the **Settings** tab and configure your FluentSupport site.</div>';
            return;
        }
        
        // Find the site object for display
        $support_site = mainwp_get_websites_by_id( $support_site_id );
        $site_name = isset( $support_site[0] ) ? $support_site[0]->name : 'Unknown Site';
        
        ?>
        <div class="mainwp-padd-cont">
            <div class="mainwp-notice mainwp-notice-blue">
                Fetching ticket data from **<?php echo esc_html( $site_name ); ?>**. This site holds all your FluentSupport tickets.
            </div>
            
            <p><button id="mainwp-fluentsupport-fetch-btn" class="button button-primary">Fetch Latest Tickets</button></p>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="15%">Site (Client)</th>
                        <th width="40%">Ticket Title</th>
                        <th width="15%">Status</th>
                        <th width="15%">Priority</th>
                        <th width="15%">Last Update</th>
                    </tr>
                </thead>
                <tbody id="fluentsupport-ticket-data">
                    <tr id="initial-load-row"><td colspan="5">Click "Fetch Latest Tickets" to retrieve the list.</td></tr>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * AJAX Handler (Runs on MainWP Dashboard)
     * Handles saving the Support Site ID.
     */
    public function ajax_save_settings() {
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $site_id = isset( $_POST['fluentsupport_site_id'] ) ? intval( $_POST['fluentsupport_site_id'] ) : 0;
        
        if ( update_option( $this->support_site_option_key, $site_id ) ) {
            wp_send_json_success( array( 'message' => 'Settings saved successfully! You can now view tickets in the Overview tab.' ) );
        } else {
            wp_send_json_error( array( 'message' => 'No changes detected or failed to save option.' ) );
        }
    }

    /**
     * AJAX Handler (Runs on MainWP Dashboard)
     * Kicks off the remote execution on the single designated Support Site.
     */
    public function ajax_fetch_tickets() {
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        
        $support_site_id = $this->get_support_site_id();
        if ( empty( $support_site_id ) ) {
            wp_send_json_error( array( 'message' => 'Support Site not configured. Please check settings.' ) );
        }

        // Get the single site object for the targeted call
        $website = mainwp_get_websites_by_id( $support_site_id );
        if ( empty( $website ) ) {
            wp_send_json_error( array( 'message' => 'Configured Support Site not found or disconnected.' ) );
        }
        $website = $website[0];

        // Perform the action only on the designated site
        $result = apply_filters( 'mainwp_do_actions_on_child_site', 'fluent_support_tickets_all', $website->id );
        
        $html_output = '';

        if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] === true ) {
            if ( ! empty( $result['tickets'] ) ) {
                foreach ( $result['tickets'] as $ticket ) {
                    // Assuming 'client_site_name' and 'ticket_url' are returned by the child site function
                    $html_output .= '
                        <tr>
                            <td>' . esc_html( $ticket['client_site_name'] ) . '</td>
                            <td><a href="' . esc_url( $ticket['ticket_url'] ) . '" target="_blank">' . esc_html( $ticket['title'] ) . '</a></td>
                            <td>' . esc_html( $ticket['status'] ) . '</td>
                            <td>' . esc_html( $ticket['priority'] ) . '</td>
                            <td>' . esc_html( $ticket['updated_at'] ) . '</td>
                        </tr>';
                }
            } else {
                 $html_output = '<tr><td colspan="5">No open tickets found on the Support Site.</td></tr>';
            }
            wp_send_json_success( array( 'html' => $html_output ) );

        } else {
            // Error handling from the remote call
            $error_message = 'Failed to get tickets. Check MainWP connection.';
            if ( is_array( $result ) && isset( $result['error'] ) ) {
                $error_message = esc_html( $result['error'] );
            }
            $html_output = '<tr class="error-row"><td colspan="5">' . $error_message . '</td></tr>';
            wp_send_json_error( array( 'html' => $html_output, 'message' => $error_message ) );
        }
    }

    /**
     * 🟢 CORE INTEGRATION LOGIC (Runs on the single SUPPORT SITE)
     * This function runs remotely on the site where FluentSupport is installed.
     * It fetches the most recent 10 tickets, open tickets, or whatever is configured.
     * It is crucial to have some way to link a ticket to a "client site" for this to be useful.
     */
    public function get_support_site_tickets( $data, $website_id ) {
        // FluentSupport check
        if ( ! function_exists( 'FluentSupportApi' ) ) {
            return array( 'error' => 'FluentSupport is not installed/active on this Support Site.' );
        }

        try {
            $ticket_api = FluentSupportApi( 'tickets' );
            
            // Fetch top 10 *open* tickets, ordered by last update
            $tickets_data = $ticket_api->getTickets( array( 
                'per_page' => 10, 
                'order_by' => 'updated_at',
                'order_type' => 'DESC',
                'filters' => array( 'status_type' => 'open' )
            ) );

            $parsed_tickets = array();

            if ( isset( $tickets_data['tickets'] ) && is_array( $tickets_data['tickets'] ) ) {
                foreach ( $tickets_data['tickets'] as $ticket ) {
                    
                    // --- 🔑 CRITICAL ASSUMPTION ---
                    // FluentSupport ties a ticket to a "customer". 
                    // To link this to a MainWP Child Site, you need to use client data.
                    // For this example, we'll assume the customer's email or custom field 
                    // could be used to identify the client/site, but we'll use a placeholder.

                    $client_site_name = 'Client Site #' . $ticket['customer_id']; // Placeholder client name
                    
                    // You must manually construct the link to view the ticket inside FluentSupport's UI
                    $ticket_url = admin_url( 'admin.php?page=fluent-support#/tickets/' . $ticket['id'] );

                    $parsed_tickets[] = array(
                        'title'            => $ticket['title'],
                        'status'           => ucfirst( $ticket['status'] ),
                        'priority'         => ucfirst( $ticket['priority'] ),
                        'updated_at'       => date( 'M j, Y H:i', strtotime( $ticket['updated_at'] ) ),
                        'ticket_url'       => $ticket_url,
                        'client_site_name' => $client_site_name,
                    );
                }
            }

            // Return the list of tickets back to the MainWP Dashboard
            return array(
                'tickets' => $parsed_tickets,
                'success' => true,
            );

        } catch ( Exception $e ) {
            return array( 'error' => 'FluentSupport API Exception: ' . $e->getMessage() );
        }
    }
}
