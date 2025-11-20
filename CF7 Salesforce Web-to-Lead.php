<?php
/**
 * Plugin Name: CF7 Salesforce Web-to-Lead
 * Description: Sends Contact Form 7 submissions to Salesforce as Leads using Web-to-Lead (no API required).
 * Version: 1.0.2
 * Author: Your Name
 * Text Domain: cf7-salesforce-webtolead
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class KSF_CF7_Salesforce_WebToLead {

    private $option_name     = 'ksf_cf7sf_wtl_options';
    private $log_option_name = 'ksf_cf7sf_wtl_log';

    public function __construct() {
        // Admin UI
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // CF7 hook
        add_action( 'wpcf7_mail_sent', array( $this, 'handle_cf7_submission' ), 10, 1 );
    }

    /**
     * Add settings page under Settings menu
     */
    public function add_settings_page() {
        add_options_page(
            'CF7 → Salesforce Web-to-Lead',
            'CF7 → Salesforce',
            'manage_options',
            'ksf-cf7-salesforce-wtl',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting(
            'ksf_cf7sf_wtl_options_group',
            $this->option_name,
            array( $this, 'sanitize_options' )
        );
    }

    /**
     * Sanitize options before saving
     */
    public function sanitize_options( $input ) {
        $output = array();

        $output['enabled']         = ! empty( $input['enabled'] ) ? 1 : 0;
        $output['org_id']          = isset( $input['org_id'] ) ? sanitize_text_field( $input['org_id'] ) : '';
        $output['ret_url']         = isset( $input['ret_url'] ) ? esc_url_raw( $input['ret_url'] ) : '';
        $output['lead_source']     = isset( $input['lead_source'] ) ? sanitize_text_field( $input['lead_source'] ) : 'Website Contact Form';
        $output['default_company'] = isset( $input['default_company'] ) ? sanitize_text_field( $input['default_company'] ) : 'Website Visitor';

        // Multiple form IDs
        if ( isset( $input['form_ids'] ) && is_array( $input['form_ids'] ) ) {
            $form_ids = array();
            foreach ( $input['form_ids'] as $fid ) {
                $form_ids[] = intval( $fid );
            }
            $output['form_ids'] = $form_ids;
        } else {
            $output['form_ids'] = array();
        }

        // Field mapping as JSON text
        if ( isset( $input['field_map'] ) ) {
            $raw = trim( stripslashes( $input['field_map'] ) );
            $output['field_map'] = $raw;
        } else {
            $output['field_map'] = '';
        }

        return $output;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $options = get_option( $this->option_name, array() );

        $enabled         = isset( $options['enabled'] ) ? (int) $options['enabled'] : 0;
        $org_id          = isset( $options['org_id'] ) ? esc_attr( $options['org_id'] ) : '00D300000000Yat';
        $ret_url         = isset( $options['ret_url'] ) ? esc_url( $options['ret_url'] ) : 'https://www.knockout.co.nz/';
        $lead_source     = isset( $options['lead_source'] ) ? esc_attr( $options['lead_source'] ) : 'Website Contact Form';
        $default_company = isset( $options['default_company'] ) ? esc_attr( $options['default_company'] ) : 'Website Visitor';

        $form_ids = array();
        if ( isset( $options['form_ids'] ) && is_array( $options['form_ids'] ) ) {
            $form_ids = array_map( 'intval', $options['form_ids'] );
        }

        // Default mapping for your form:
        // your-name     -> last_name
        // your-email    -> email
        // text-810      -> phone
        // text-749      -> company (Club Name)
        // your-message  -> description
        $field_map = isset( $options['field_map'] ) && $options['field_map']
            ? $options['field_map']
            : json_encode( array(
                array( 'cf7' => 'your-name',    'sf' => 'last_name' ),
                array( 'cf7' => 'your-email',   'sf' => 'email' ),
                array( 'cf7' => 'text-810',     'sf' => 'phone' ),
                array( 'cf7' => 'text-749',     'sf' => 'company' ),
                array( 'cf7' => 'your-message', 'sf' => 'description' ),
            ), JSON_PRETTY_PRINT );

        $logs = get_option( $this->log_option_name, array() );

        // Get CF7 forms
        $cf7_forms = get_posts( array(
            'post_type'      => 'wpcf7_contact_form',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        ?>
        <div class="wrap ksf-cf7sf-wrap">
            <h1>CF7 → Salesforce Web-to-Lead</h1>
            <p>Send Contact Form 7 submissions to Salesforce as Leads using Web-to-Lead (works in Professional Edition, no API required).</p>

            <style>
                .ksf-cf7sf-wrap .card {
                    background: #ffffff;
                    border-radius: 12px;
                    padding: 20px;
                    margin-bottom: 20px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
                    border: 1px solid #e5e7eb;
                }
                .ksf-cf7sf-wrap h2 {
                    margin-top: 0;
                }
                .ksf-cf7sf-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    grid-gap: 20px;
                }
                @media (max-width: 960px) {
                    .ksf-cf7sf-grid {
                        grid-template-columns: 1fr;
                    }
                }
                .ksf-cf7sf-field label {
                    font-weight: 600;
                    display: block;
                    margin-bottom: 4px;
                }
                .ksf-cf7sf-field input[type="text"],
                .ksf-cf7sf-field input[type="password"],
                .ksf-cf7sf-field select,
                .ksf-cf7sf-field textarea {
                    width: 100%;
                    max-width: 100%;
                }
                .ksf-cf7sf-badge {
                    display: inline-block;
                    padding: 2px 8px;
                    border-radius: 999px;
                    background: #eef2ff;
                    color: #4338ca;
                    font-size: 11px;
                    font-weight: 600;
                    text-transform: uppercase;
                    letter-spacing: 0.05em;
                    margin-left: 8px;
                }
                .ksf-cf7sf-log-table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .ksf-cf7sf-log-table th,
                .ksf-cf7sf-log-table td {
                    border: 1px solid #e5e7eb;
                    padding: 6px 8px;
                    font-size: 12px;
                }
                .ksf-cf7sf-log-table th {
                    background: #f3f4f6;
                }
                .ksf-status-ok {
                    color: #16a34a;
                    font-weight: 600;
                }
                .ksf-status-fail {
                    color: #dc2626;
                    font-weight: 600;
                }
                .ksf-notice {
                    padding: 10px 12px;
                    border-radius: 6px;
                    margin-bottom: 15px;
                    border: 1px solid transparent;
                }
                .ksf-notice-info {
                    background: #eff6ff;
                    border-color: #bfdbfe;
                    color: #1d4ed8;
                }
            </style>

            <div class="card">
                <h2>How this works</h2>
                <div class="ksf-notice ksf-notice-info">
                    <p>
                        This plugin posts your Contact Form 7 submissions to Salesforce's
                        <strong>Web-to-Lead</strong> endpoint:
                        <code>https://webto.salesforce.com/servlet/servlet.WebToLead</code><br>
                        No API, no username/password/token required. Works in Professional Edition.
                    </p>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields( 'ksf_cf7sf_wtl_options_group' ); ?>

                <div class="ksf-cf7sf-grid">
                    <div class="card">
                        <h2>Salesforce Web-to-Lead Settings</h2>

                        <div class="ksf-cf7sf-field">
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr( $this->option_name ); ?>[enabled]" value="1" <?php checked( 1, $enabled ); ?> />
                                Enable Salesforce Lead Sync (Web-to-Lead)
                            </label>
                        </div>

                        <div class="ksf-cf7sf-field">
                            <label>Salesforce.com Organization ID (oid)</label>
                            <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[org_id]" value="<?php echo $org_id; ?>" />
                            <p class="description">
                                From Salesforce Setup → Company Information → <strong>Salesforce.com Organization ID</strong>.<br>
                                Example: <code>00D300000000Yat</code>
                            </p>
                        </div>

                        <div class="ksf-cf7sf-field">
                            <label>Return URL (retURL)</label>
                            <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[ret_url]" value="<?php echo $ret_url; ?>" />
                            <p class="description">
                                After Salesforce accepts the lead, it redirects here.
                                Example: <code>https://www.knockout.co.nz/</code>
                            </p>
                        </div>

                        <div class="ksf-cf7sf-field">
                            <label>Lead Source (lead_source)</label>
                            <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[lead_source]" value="<?php echo $lead_source; ?>" />
                            <p class="description">
                                Optional. If set, will be sent as <code>lead_source</code> (standard Lead field).
                                Example: <code>Website Contact Form</code>
                            </p>
                        </div>

                        <div class="ksf-cf7sf-field">
                            <label>Default Company</label>
                            <input type="text" name="<?php echo esc_attr( $this->option_name ); ?>[default_company]" value="<?php echo $default_company; ?>" />
                            <p class="description">
                                Used when no <code>company</code> is mapped or provided. Example: <code>Website Visitor</code>
                            </p>
                        </div>
                    </div>

                    <div class="card">
                        <h2>Contact Form 7 Integration</h2>

                        <div class="ksf-cf7sf-field">
                            <label>Contact Form 7 Forms (multi-select)</label>
                            <select name="<?php echo esc_attr( $this->option_name ); ?>[form_ids][]" multiple size="5">
                                <?php
                                if ( $cf7_forms ) {
                                    foreach ( $cf7_forms as $form ) {
                                        $selected = in_array( $form->ID, $form_ids, true ) ? 'selected="selected"' : '';
                                        echo '<option value="' . esc_attr( $form->ID ) . '" ' . $selected . '>' . esc_html( $form->post_title ) . ' (ID: ' . intval( $form->ID ) . ')</option>';
                                    }
                                } else {
                                    echo '<option value="">No CF7 forms found.</option>';
                                }
                                ?>
                            </select>
                            <p class="description">
                                Hold Ctrl / Cmd to select multiple forms. Any selected form will sync to Salesforce.
                                For you, select <strong>Request Quote (ID: 205747)</strong>.
                            </p>
                        </div>

                        <div class="ksf-cf7sf-field">
                            <label>Field Mapping <span class="ksf-cf7sf-badge">Web-to-Lead</span></label>
                            <p>
                                Map Contact Form 7 fields (by <strong>name</strong>) to Salesforce Web-to-Lead field names.
                                For standard fields Web-to-Lead uses:
                            </p>
                            <ul style="list-style: disc; margin-left: 20px;">
                                <li><code>last_name</code></li>
                                <li><code>email</code></li>
                                <li><code>phone</code></li>
                                <li><code>company</code></li>
                                <li><code>description</code></li>
                                <li><code>lead_source</code> (optional)</li>
                            </ul>
                            <p><strong>Example mapping for your form:</strong></p>
<pre>[
  { "cf7": "your-name",    "sf": "last_name" },
  { "cf7": "your-email",   "sf": "email" },
  { "cf7": "text-810",     "sf": "phone" },
  { "cf7": "text-749",     "sf": "company" },
  { "cf7": "your-message", "sf": "description" }
]</pre>
                            <textarea name="<?php echo esc_attr( $this->option_name ); ?>[field_map]" rows="12"><?php echo esc_textarea( $field_map ); ?></textarea>
                            <p class="description">
                                The same mapping is applied to all selected forms.<br>
                                <strong>Required:</strong> at least <code>last_name</code>. If <code>company</code> is missing, the Default Company will be used.<br>
                                Any CF7 fields not mapped here will be appended to the <code>description</code> text automatically,
                                and we've formatted Club / Location / Sport / Quantities nicely at the top.
                            </p>
                        </div>
                    </div>
                </div>

                <?php submit_button(); ?>
            </form>

            <div class="card">
                <h2>Recent Sync Log</h2>
                <?php if ( ! empty( $logs ) && is_array( $logs ) ) : ?>
                    <table class="ksf-cf7sf-log-table">
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( array_reverse( $logs ) as $entry ) : ?>
                            <tr>
                                <td><?php echo esc_html( $entry['time'] ); ?></td>
                                <td><?php echo ! empty( $entry['success'] ) ? '<span class="ksf-status-ok">Success</span>' : '<span class="ksf-status-fail">Failed</span>'; ?></td>
                                <td><?php echo esc_html( $entry['message'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else : ?>
                    <p>No log entries yet.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * CF7 submission handler
     */
    public function handle_cf7_submission( $contact_form ) {
        $options = get_option( $this->option_name, array() );
        if ( empty( $options['enabled'] ) ) {
            return;
        }

        $configured_form_ids = array();
        if ( isset( $options['form_ids'] ) && is_array( $options['form_ids'] ) ) {
            $configured_form_ids = array_map( 'intval', $options['form_ids'] );
        }

        if ( ! empty( $configured_form_ids ) && ! in_array( intval( $contact_form->id() ), $configured_form_ids, true ) ) {
            // Not one of the forms we want to sync
            return;
        }

        $submission = WPCF7_Submission::get_instance();
        if ( ! $submission ) {
            $this->add_log( false, 'No CF7 submission object found.' );
            return;
        }

        $data = $submission->get_posted_data();
        if ( empty( $data ) || ! is_array( $data ) ) {
            $this->add_log( false, 'Empty posted data.' );
            return;
        }

        $lead_fields = $this->build_webtolead_fields_from_posted_data( $data, $options );

        if ( empty( $lead_fields['last_name'] ) ) {
            $this->add_log( false, 'Skipping: last_name is missing after mapping.' );
            return;
        }

        if ( empty( $lead_fields['company'] ) ) {
            $lead_fields['company'] = ! empty( $options['default_company'] ) ? $options['default_company'] : 'Website Visitor';
        }

        if ( ! empty( $options['lead_source'] ) ) {
            $lead_fields['lead_source'] = $options['lead_source'];
        }

        $result = $this->submit_web_to_lead( $lead_fields, $options );

        if ( is_wp_error( $result ) ) {
            $this->add_log( false, 'Web-to-Lead error: ' . $result->get_error_message() );
        } else {
            $this->add_log( true, 'Lead sent via Web-to-Lead.' );
        }
    }

    /**
     * Build Web-to-Lead fields from CF7 posted data and mapping
     * Formats Club / Location / Sport / Quantities / Message nicely in description,
     * and adds an "--- Additional Info ---" section with bold labels.
     */
    private function build_webtolead_fields_from_posted_data( $data, $options ) {
        $lead = array();

        // ---- 1. Decode mapping JSON ----
        $map_json = isset( $options['field_map'] ) ? $options['field_map'] : '';
        $mapping  = array();

        if ( $map_json ) {
            $decoded = json_decode( $map_json, true );
            if ( is_array( $decoded ) ) {
                // Accept array of objects [{cf7:"",sf:""}, ...] OR simple map {"your-name":"last_name"}
                if ( isset( $decoded[0] ) && is_array( $decoded[0] ) && isset( $decoded[0]['cf7'] ) ) {
                    foreach ( $decoded as $row ) {
                        if ( ! empty( $row['cf7'] ) && ! empty( $row['sf'] ) ) {
                            $mapping[ $row['cf7'] ] = $row['sf'];
                        }
                    }
                } else {
                    foreach ( $decoded as $cf7 => $sf ) {
                        $mapping[ $cf7 ] = $sf;
                    }
                }
            }
        }

        // ---- 2. Apply mapping to non-description fields first ----
        foreach ( $mapping as $cf7_field => $sf_field ) {
            if ( $sf_field === 'description' ) {
                // We'll handle description formatting separately
                continue;
            }

            if ( isset( $data[ $cf7_field ] ) ) {
                $value = $data[ $cf7_field ];

                // Handle arrays (e.g. multiselect)
                if ( is_array( $value ) ) {
                    $value = implode( ', ', $value );
                }

                $lead[ $sf_field ] = sanitize_textarea_field( $value );
            }
        }

        // Ensure last_name is set if possible
        if ( empty( $lead['last_name'] ) && ! empty( $data['your-name'] ) ) {
            $lead['last_name'] = sanitize_text_field( $data['your-name'] );
        }

        // ---- 3. Build a nicely formatted description ----
        // Known CF7 field names from your form
        $club_name = isset( $data['text-749'] ) ? sanitize_text_field( is_array( $data['text-749'] ) ? implode( ', ', $data['text-749'] ) : $data['text-749'] ) : '';
        $location  = isset( $data['text-314'] ) ? sanitize_text_field( is_array( $data['text-314'] ) ? implode( ', ', $data['text-314'] ) : $data['text-314'] ) : '';
        $sport     = isset( $data['menu-194'] ) ? ( is_array( $data['menu-194'] ) ? implode( ', ', $data['menu-194'] ) : $data['menu-194'] ) : '';
        $sport     = sanitize_text_field( $sport );
        $quantity  = isset( $data['menu-729'] ) ? sanitize_text_field( is_array( $data['menu-729'] ) ? implode( ', ', $data['menu-729'] ) : $data['menu-729'] ) : '';

        // Message: from mapping (if any) or from your-message field
        $message = '';
        if ( isset( $mapping['your-message'] ) && $mapping['your-message'] === 'description' && ! empty( $data['your-message'] ) ) {
            $raw     = is_array( $data['your-message'] ) ? implode( "\n", $data['your-message'] ) : $data['your-message'];
            $message = sanitize_textarea_field( $raw );
        }

        $desc_lines = array();

        if ( $club_name ) {
            $desc_lines[] = '**Club Name:** ' . $club_name;
        }
        if ( $location ) {
            $desc_lines[] = '**Location:** ' . $location;
        }
        if ( $sport ) {
            $desc_lines[] = '**Sport(s):** ' . $sport;
        }
        if ( $quantity ) {
            $desc_lines[] = '**Quantities:** ' . $quantity;
        }

        $description = '';

        if ( ! empty( $desc_lines ) ) {
            $description .= implode( "\n", $desc_lines );
        }

        if ( $message ) {
            if ( $description !== '' ) {
                $description .= "\n\n**Message:**\n" . $message;
            } else {
                $description = "**Message:**\n" . $message;
            }
        }

        // ---- 4. Append unmapped fields as "--- Additional Info ---" ----

        // Friendly labels for Additional Details
        $label_map = array(
            'text-619' => 'Your Position',
            'text-447' => 'What type of gear you looking for?',
            'text-641' => 'How did you hear about us?',
            'text-186' => 'When is the best time to call you?',
        );

        $extra_lines = array();
        foreach ( $data as $key => $value ) {
            // Skip internal CF7 keys
            if ( strpos( $key, '_wpcf7' ) === 0 ) {
                continue;
            }

            // Skip the fields we've already formatted explicitly
            $skip_keys = array(
                'your-message',
                'text-749',  // Club Name
                'text-314',  // Location
                'menu-194',  // Sport
                'menu-729',  // Quantities
            );
            if ( in_array( $key, $skip_keys, true ) ) {
                continue;
            }

            // Skip mapped fields (we already handled those above)
            if ( isset( $mapping[ $key ] ) ) {
                continue;
            }

            if ( is_array( $value ) ) {
                $value = implode( ', ', $value );
            }
            $value = trim( $value );
            if ( $value === '' ) {
                continue;
            }

            // Use friendly label if available
            $label = isset( $label_map[ $key ] ) ? $label_map[ $key ] : $key;

            $extra_lines[] = '**' . $label . ':** ' . $value;
        }

        if ( ! empty( $extra_lines ) ) {
            $extra_text = "\n\n--- Additional Info ---\n" . implode( "\n", $extra_lines );
            if ( $description !== '' ) {
                $description .= $extra_text;
            } else {
                $description = ltrim( $extra_text );
            }
        }

        if ( $description !== '' ) {
            $lead['description'] = $description;
        }

        return $lead;
    }

    /**
     * Submit data to Salesforce Web-to-Lead endpoint
     */
    private function submit_web_to_lead( $lead_fields, $options ) {
        $org_id  = isset( $options['org_id'] ) ? trim( $options['org_id'] ) : '';
        $ret_url = isset( $options['ret_url'] ) ? trim( $options['ret_url'] ) : '';

        if ( empty( $org_id ) ) {
            return new WP_Error( 'no_org_id', 'Salesforce Org ID (oid) is not configured.' );
        }

        $endpoint = 'https://webto.salesforce.com/servlet/servlet.WebToLead?encoding=UTF-8';

        $payload = $lead_fields;
        $payload['oid'] = $org_id;
        if ( ! empty( $ret_url ) ) {
            $payload['retURL'] = $ret_url;
        }

        $response = wp_remote_post( $endpoint, array(
            'body'        => $payload,
            'timeout'     => 20,
            'redirection' => 3,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 400 ) {
            $body = wp_remote_retrieve_body( $response );
            return new WP_Error( 'webtolead_http_error', 'Web-to-Lead HTTP error: ' . $code . ' - ' . substr( wp_strip_all_tags( $body ), 0, 400 ) );
        }

        return true;
    }

    /**
     * Add log entry
     */
    private function add_log( $success, $message ) {
        $logs = get_option( $this->log_option_name, array() );
        if ( ! is_array( $logs ) ) {
            $logs = array();
        }

        $logs[] = array(
            'time'    => current_time( 'mysql' ),
            'success' => $success ? 1 : 0,
            'message' => $message,
        );

        // Keep only last 50 entries
        if ( count( $logs ) > 50 ) {
            $logs = array_slice( $logs, -50 );
        }

        update_option( $this->log_option_name, $logs, false );
    }
}

new KSF_CF7_Salesforce_WebToLead();
