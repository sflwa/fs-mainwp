<?php
/**
 * MainWP FluentSupport Admin
 * Final Code - Handles persistence, fetching, and display logic.
 */

namespace MainWP\Extensions\FluentSupport;

class MainWP_FluentSupport_Admin {

	public static $instance = null;

	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function __construct() {
		// Initialize the DB class (Required for MainWP structural pattern)
		MainWP_FluentSupport_DB::get_instance()->install();

		// AJAX handlers for fetching tickets and saving settings
		add_action( 'wp_ajax_mainwp_fluentsupport_fetch_tickets', array( $this, 'ajax_fetch_tickets' ) );
		add_action( 'wp_ajax_mainwp_fluentsupport_save_settings', array( $this, 'ajax_save_settings' ) );

		// MainWP filter hooks (remote execution)
		add_filter( 'mainwp_site_actions_fluent_support_tickets_all', array( $this, 'get_support_site_tickets' ), 10, 2 );
		add_filter( 'mainwp_before_do_actions', array( $this, 'inject_client_sites_data' ), 10, 3 );
	}

    /**
     * Retrieves the stored MainWP Site ID for the FluentSupport site, bypassing the object cache.
     * @return int
     */
    private function get_support_site_id() {
        return (int) get_option( 'mainwp_fluentsupport_site_id', 0, false );
    }

    /**
     * Retrieves the stored URL for the FluentSupport site, bypassing the object cache and normalizing it.
     * @return string
     */
    private function get_support_site_url() {
        // Normalizes the URL on retrieval, removing trailing slash
        return rtrim( get_option( 'mainwp_fluentsupport_site_url', '', false ), '/' );
    }

    // -----------------------------------------------------------------
    // RENDERING METHODS
    // -----------------------------------------------------------------

    /**
     * Renders the tab for configuring the single FluentSupport site (Text Field).
     */
    public function render_settings_tab() {
        $current_site_url = $this->get_support_site_url();
        
        // DEBUG VALUES: Get raw option values, forcing uncached read for accurate display
        $debug_raw_url = get_option('mainwp_fluentsupport_site_url', 'NOT SET', false);
        $debug_raw_id = get_option('mainwp_fluentsupport_site_id', 'NOT SET', false);

        ?>
        <div class="mainwp-padd-cont">
            <h3>Support Site Configuration</h3>
            <p>Enter the full URL of the single site where **FluentSupport** is installed. This site **must** also be connected as a MainWP Child Site for secure fetching to work.</p>
            
            <form method="post" id="mainwp-fluentsupport-settings-form">
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="fluentsupport_site_url">Support Site URL</label>
                        </th>
                        <td>
                            <input 
                                type="url" 
                                id="fluentsupport_site_url" 
                                name="fluentsupport_site_url" 
                                value="<?php echo esc_url( $current_site_url ); ?>" 
                                class="regular-text" 
                                placeholder="https://your-support-site.com"
                                required
                            />
                            <p class="description">Enter the full URL (including https://). This URL must match a connected MainWP Child Site and also match the value stored in the FluentSupport custom field <code>cf_website_url</code> for client ticket mapping.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" id="mainwp-fluentsupport-save-settings-btn" class="button button-primary">Save Settings</button>
                </p>
            </form>

            <hr/>
            <h4>⚙️ Settings Debug Output</h4>
            <table class="form-table">
                <tr>
                    <th>Raw DB Option (URL)</th>
                    <td><code>mainwp_fluentsupport_site_url</code>: **<?php echo esc_html($debug_raw_url); ?>**</td>
                </tr>
                <tr>
                    <th>Raw DB Option (ID)</th>
                    <td><code>mainwp_fluentsupport_site_id</code>: **<?php echo esc_html($debug_raw_id); ?>**</td>
                </tr>
                <tr>
                    <th>Site Connection Check</th>
                    <td>**Is Site ID Found & Set?** <?php echo ($this->get_support_site_id() > 0) ? '✅ YES' : '❌ NO'; ?></td>
                </tr>
            </table>
        </div>
        <?php
    }

    /**
     * Renders the main Overview tab.
     */
    public function render_overview_tab() {
        $support_site_id = $this->get_support_site_id();
        $support_site_url = $this->get_support_site_url();
        
        // This check ensures the "save first" warning is correctly displayed.
        if ( empty( $support_site_id ) || empty( $support_site_url ) ) {
            echo '<div class="mainwp-notice mainwp-notice-red">Please go to the **Settings** tab and configure your FluentSupport site URL. The site must be a connected MainWP Child Site.</div>';
            return;
        }
        
        $site_name = $support_site_url;
        
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
            
            <hr/>
            <h4>⚙️ Fetch Debug Output</h4>
            <table class="form-table">
                <tr>
                    <th>Fetch Action</th>
                    <td><code>mainwp_fluentsupport_fetch_tickets</code></td>
                </tr>
                <tr>
                    <th>Target Site URL</th>
                    <td>**<?php echo esc_html($support_site_url); ?>**</td>
                </tr>
                <tr>
                    <th>Target Site ID (for remote call)</th>
                    <td>**<?php echo esc_html($support_site_id); ?>**</td>
                </tr>
                <tr>
                    <td colspan="2">**If button does nothing:** The JavaScript file `mainwp-fluentsupport.js` is not loading. Check your browser's **Network** tab to confirm it's loaded and your browser's **Console** for errors.</td>
                </tr>
            </table>
        </div>
        <?php
    }
    
    // ... (All other methods remain the same) ...
    public function inject_client_sites_data( $data, $action, $website_id ) {
        if ( 'fluent_support_tickets_all' === $action ) {
            $all_websites = MainWP_FluentSupport_Utility::get_websites();
            
            $client_sites = array();
            foreach ( $all_websites as $website ) {
                $client_sites[ rtrim($website['url'], '/') ] = $website['name'];
            }
            $data['client_sites'] = $client_sites;
        }
        return $data;
    }

    public function ajax_save_settings() {
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }

        $site_url = isset( $_POST['fluentsupport_site_url'] ) ? sanitize_text_field( wp_unslash( $_POST['fluentsupport_site_url'] ) ) : '';
        $site_url = rtrim( $site_url, '/' ); 
        if ( empty( $site_url ) ) {
            wp_send_json_error( array( 'message' => 'The Support Site URL cannot be empty.' ) );
        }
        
        $websites = MainWP_FluentSupport_Utility::get_websites();
        $found_site_id = 0;
        
        foreach ( $websites as $website ) {
            if ( rtrim( $website['url'], '/' ) === $site_url ) {
                $found_site_id = $website['id']; 
                break;
            }
        }

        $url_saved = update_option( 'mainwp_fluentsupport_site_url', $site_url ); 
        $id_saved = update_option( 'mainwp_fluentsupport_site_id', $found_site_id ); 

        if ( $url_saved === false && $id_saved === false && $this->get_support_site_url() === $site_url ) {
            wp_send_json_success( array( 'message' => 'Settings already up to date.' ) );
        }

        $message = 'Settings saved successfully! You can now view tickets in the Overview tab.';

        if ($found_site_id === 0) {
            $message = 'Settings saved. WARNING: The entered URL does not match a connected MainWP Child Site. Ticket fetching will fail until the FluentSupport site is connected to this MainWP Dashboard.';
        }

        wp_send_json_success( array( 'message' => $message ) );
    }

    public function ajax_fetch_tickets() {
        check_ajax_referer( 'mainwp-fluentsupport-nonce', 'security' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Permission denied.' ) );
        }
        
        $support_site_id = $this->get_support_site_id();
        if ( empty( $support_site_id ) ) {
            wp_send_json_error( array( 'message' => 'Support Site not configured. Please check settings.' ) );
        }

        $websites = MainWP_FluentSupport_Utility::get_websites( $support_site_id );
        $website = current( $websites ); 

        if ( empty( $website ) ) {
            wp_send_json_error( array( 'message' => 'Configured Support Site not found or disconnected. Please re-sync your MainWP Dashboard.' ) );
        }

        $result = apply_filters( 'mainwp_do_actions_on_child_site', 'fluent_support_tickets_all', $website['id'] );
        
        $html_output = '';

        if ( is_array( $result ) && isset( $result['success'] ) && $result['success'] === true ) {
            if ( ! empty( $result['tickets'] ) ) {
                foreach ( $result['tickets'] as $ticket ) {
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
    
    public function get_support_site_tickets( $data, $website_id ) {
        if ( ! function_exists( 'FluentSupportApi' ) ) {
            return array( 'error' => 'FluentSupport is not installed/active on this Support Site.' );
        }

        try {
            $ticket_api = FluentSupportApi( 'tickets' );
            $client_sites_map = isset( $data['client_sites'] ) ? $data['client_sites'] : array();
            
            $tickets_data = $ticket_api->getTickets( array( 
                'per_page' => 10,
                'order_by' => 'updated_at',
                'order_type' => 'DESC',
                'filters' => array( 'status_type' => 'open' )
            ) );

            $parsed_tickets = array();
            
            if ( isset( $tickets_data['tickets'] ) && is_array( $tickets_data['tickets'] ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'fst_ticket_custom_fields';

                foreach ( $tickets_data['tickets'] as $ticket ) {
                    
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

                    $client_site_name = 'Unmapped Site (URL: ' . $website_url . ')';
                    
                    if ( ! empty( $website_url ) && isset( $client_sites_map[ $website_url ] ) ) {
                         $client_site_name = $client_sites_map[ $website_url ];
                    } else if ( ! empty( $website_url ) && isset( $client_sites_map[ $website_url . '/' ] ) ) {
                         $client_site_name = $client_sites_map[ $website_url . '/' ];
                    }
                    
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

            return array(
                'tickets' => $parsed_tickets,
                'success' => true,
            );

        } catch ( Exception $e ) {
            return array( 'error' => 'FluentSupport API Exception: ' . $e->getMessage() );
        }
    }
}
