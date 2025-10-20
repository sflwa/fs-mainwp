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
        
        // New filter hook for the Dashboard to pass all client site URLs to the Support Site
        add_filter( 'mainwp_before_do_actions', array( $this, 'inject_client_sites_data' ), 10, 3 );
    }

    /**
     * Get the configured Support Site ID.
     */
    private function get_support_site_id() {
        return get_option( $this->support_site_option_key, 0 );
    }

    /**
     * Inject all MainWP Child Site URLs into the remote call data.
     * This is needed so the Support Site can look up a site name based on the cf_website_url.
     */
    public function inject_client_sites_data( $data, $action, $website_id ) {
        if ( 'fluent_support_tickets_all' === $action ) {
            global $mainwp;
            $all_websites = $mainwp->get_websites_current_user();
            
            $client_sites = array();
            foreach ( $all_websites as $website ) {
                // Store URL (as key) and Site Name (as value) for easy lookup on the child site
                $client_sites[ $website->url ] = $website->name;
            }
            $data['client_sites'] = $client_sites;
        }
        return $data;
    }


    /**
     * Add the "FluentSupport" subpage with two tabs: Overview and Settings.
     * (Remains the same as previous iteration)
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
     * (Remains the same as previous iteration)
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
     * (Remains the same as previous iteration)
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
     * (Remains the same as previous iteration)
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
                        <th width="15%">Client Site</th>
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
     * (Remains the same as previous iteration)
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
     * (Remains the same as previous iteration, but relies on injected data)
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

        $website = mainwp_get_websites_by_id( $support_site_id );
        if ( empty( $website ) ) {
            wp_send_json_error( array( 'message' => 'Configured Support Site not found or disconnected.' ) );
        }
        $website = $website[0];

        // This action uses the `mainwp_before_do_actions` filter to inject the list of client sites
        $result = apply_filters( 'mainwp_do_actions_on_child_site', 'fluent_support_tickets_all', $website->id );
        
        $html_output = '';

        if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] === true ) {
            if ( ! empty( $result['tickets'] ) ) {
                foreach ( $result['tickets'] as $ticket ) {
                    
                    // We now use the actual site name found in the client_site_name field
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
     * This function now extracts the cf_website_url custom field and maps it to a MainWP site name.
     */
    public function get_support_site_tickets( $data, $website_id ) {
        // FluentSupport check
        if ( ! function_exists( 'FluentSupportApi' ) ) {
            return array( 'error' => 'FluentSupport is not installed/active on this Support Site.' );
        }

        try {
            $ticket_api = FluentSupportApi( 'tickets' );
            
            // The list of all MainWP client sites is passed in the $data array
            $client_sites_map = isset( $data['client_sites'] ) ? $data['client_sites'] : array();
            
            // FluentSupport API call to fetch tickets
            $tickets_data = $ticket_api->getTickets( array( 
                'per_page' => 10, // Adjust this number for more/fewer tickets
                'order_by' => 'updated_at',
                'order_type' => 'DESC',
                'filters' => array( 'status_type' => 'open' ) // Only open tickets
            ) );

            $parsed_tickets = array();

            if ( isset( $tickets_data['tickets'] ) && is_array( $tickets_data['tickets'] ) ) {
                
                // Get the FluentSupport DB instance to query custom fields.
                // This bypasses the default ticket data which doesn't include CFs directly.
                global $wpdb;
                $table_name = $wpdb->prefix . 'fst_ticket_custom_fields';

                foreach ( $tickets_data['tickets'] as $ticket ) {
                    
                    // --- 🔑 LOOKUP CUSTOM FIELD VALUE ---
                    $website_url = '';
                    $cf_data = $wpdb->get_results( 
                        $wpdb->prepare(
                            "SELECT field_value FROM {$table_name} WHERE ticket_id = %d AND field_name = %s",
                            $ticket['id'],
                            'cf_website_url'
                        ),
                        ARRAY_A
                    );

                    if ( ! empty( $cf_data ) && ! empty( $cf_data[0]['field_value'] ) ) {
                        $website_url = rtrim( $cf_data[0]['field_value'], '/' );
                    }

                    // --- 🧭 MAP URL TO MAINWP SITE NAME ---
                    $client_site_name = 'Unmapped Site (URL: ' . $website_url . ')';
                    
                    // Attempt to find the site name using the provided map
                    if ( ! empty( $website_url ) && isset( $client_sites_map[ $website_url ] ) ) {
                         $client_site_name = $client_sites_map[ $website_url ];
                    } else if ( ! empty( $website_url ) ) {
                         // Try to match URL with or without trailing slash
                         $client_site_name = $client_sites_map[ rtrim($website_url, '/') ] ?? 
                                             $client_sites_map[ $website_url . '/' ] ?? 
                                             $client_site_name;
                    }
                    
                    // --- 📝 BUILD PARSED TICKET DATA ---
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
