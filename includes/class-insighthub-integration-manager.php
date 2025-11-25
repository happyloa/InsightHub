<?php
/**
 * Manages marketing integrations and connection tokens.
 *
 * @package InsightHub
 */

namespace InsightHub;

use InsightHub\Integrations\ActiveCampaign_Client;
use InsightHub\Integrations\Clarity_Client;
use InsightHub\Integrations\Google_Site_Kit_Client;
use WP_Error;

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'integrations/class-insighthub-google-site-kit-client.php';
require_once plugin_dir_path( __FILE__ ) . 'integrations/class-insighthub-activecampaign-client.php';
require_once plugin_dir_path( __FILE__ ) . 'integrations/class-insighthub-clarity-client.php';

/**
 * Class Integration_Manager
 *
 * Handles marketing tool connections and tokens.
 */
class Integration_Manager {
    const OPTION_NAME = 'insighthub_integration_tokens';
    const CRON_HOOK = 'insighthub_sync_integrations';
    const SUMMARY_CACHE_PREFIX = 'insighthub_summary_';
    const SYNC_LOCK_KEY = 'insighthub_sync_lock';
    const SYNC_STATUS_KEY = 'insighthub_sync_status';
    const SUMMARY_TTL = 30 * MINUTE_IN_SECONDS;

    /**
     * Supported tools definition.
     *
     * @var array<string, array<string, string>>
     */
    private $tools = [
        'google_site_kit' => [
            'label'       => 'Google Site Kit',
            'description' => 'Connect Google Analytics and Search Console insights.',
            'client'      => Google_Site_Kit_Client::class,
            'type'        => 'oauth',
        ],
        'activecampaign' => [
            'label'       => 'ActiveCampaign',
            'description' => 'Sync marketing automation and campaign performance.',
            'client'      => ActiveCampaign_Client::class,
            'type'        => 'api_key',
        ],
        'clarity'        => [
            'label'       => 'Microsoft Clarity',
            'description' => 'View session recordings and heatmap highlights.',
            'client'      => Clarity_Client::class,
            'type'        => 'project_key',
        ],
    ];

    /**
     * Get supported tools metadata.
     *
     * @return array<string, array<string, string>>
     */
    public function get_tools() {
        return $this->tools;
    }

    /**
     * Register hooks for background sync and AJAX handlers.
     */
    public function register_hooks() {
        add_action( 'init', [ $this, 'maybe_schedule_background_sync' ] );
        add_action( self::CRON_HOOK, [ $this, 'run_background_sync' ], 10, 1 );

        add_action( 'wp_ajax_insighthub_refresh_integrations', [ $this, 'handle_ajax_refresh' ] );
        add_action( 'admin_post_insighthub_refresh_integrations', [ $this, 'handle_manual_refresh' ] );
    }

    /**
     * Ensure the recurring cron event exists.
     */
    public function maybe_schedule_background_sync() {
        if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
            wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', self::CRON_HOOK );
        }
    }

    /**
     * Retrieve stored connection data for a tool.
     *
     * @param string $tool Tool slug.
     *
     * @return array<string, mixed>|null
     */
    public function get_connection( $tool ) {
        $connections = get_option( self::OPTION_NAME, [] );

        if ( ! isset( $connections[ $tool ] ) ) {
            return null;
        }

        $connection = $connections[ $tool ];
        $updated    = false;

        // Migrate legacy string tokens into the new structured format.
        if ( is_string( $connection ) ) {
            $connection = [
                'credentials' => [ 'token' => $connection ],
                'metadata'    => [],
            ];
            $updated    = true;
        }

        if ( is_array( $connection ) ) {
            if ( ! isset( $connection['credentials'] ) ) {
                $connection['credentials'] = [];
                $updated                   = true;
            }

            if ( ! isset( $connection['metadata'] ) || ! is_array( $connection['metadata'] ) ) {
                $connection['metadata'] = [];
                $updated                = true;
            }

            if ( ! isset( $connection['stored_at'] ) ) {
                $connection['stored_at'] = current_time( 'timestamp' );
                $updated                 = true;
            }

            if ( ! isset( $connection['validation'] ) || ! is_array( $connection['validation'] ) ) {
                $connection['validation'] = [
                    'status'         => 'unknown',
                    'checked_at'     => null,
                    'last_success_at' => null,
                    'message'        => '',
                ];
                $updated                   = true;
            }

            if ( $updated ) {
                $connections[ $tool ] = $connection;
                update_option( self::OPTION_NAME, $connections, false );
            }

            return $connection;
        }

        return null;
    }

    /**
     * Retrieve cached dashboard summary for a tool.
     *
     * @param string $tool Tool slug.
     *
     * @return array<string, mixed>
     */
    public function get_cached_summary( $tool ) {
        $cached = get_transient( self::SUMMARY_CACHE_PREFIX . $tool );

        if ( ! is_array( $cached ) ) {
            return [
                'data'      => [],
                'cached_at' => null,
            ];
        }

        return wp_parse_args(
            $cached,
            [
                'data'      => [],
                'cached_at' => null,
            ]
        );
    }

    /**
     * Cache a dashboard summary payload for the tool.
     *
     * @param string               $tool Tool slug.
     * @param array<string, mixed> $data Summary payload.
     */
    public function cache_summary( $tool, array $data ) {
        set_transient(
            self::SUMMARY_CACHE_PREFIX . $tool,
            [
                'data'      => $data,
                'cached_at' => current_time( 'timestamp' ),
            ],
            self::SUMMARY_TTL
        );
    }

    /**
     * Clear the cached summary for a tool.
     *
     * @param string $tool Tool slug.
     */
    public function clear_cached_summary( $tool ) {
        delete_transient( self::SUMMARY_CACHE_PREFIX . $tool );
    }

    /**
     * Check if a sync is currently running.
     *
     * @return bool
     */
    public function is_sync_running() {
        return (bool) get_transient( self::SYNC_LOCK_KEY );
    }

    /**
     * Mark sync running state.
     *
     * @param bool $running Whether the sync is running.
     */
    private function set_sync_running( $running ) {
        if ( $running ) {
            set_transient( self::SYNC_LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );
            set_transient( self::SYNC_STATUS_KEY, [
                'started_at' => current_time( 'timestamp' ),
                'state'      => 'running',
            ], 5 * MINUTE_IN_SECONDS );
            return;
        }

        delete_transient( self::SYNC_LOCK_KEY );
        set_transient( self::SYNC_STATUS_KEY, [
            'ended_at' => current_time( 'timestamp' ),
            'state'    => 'idle',
        ], MINUTE_IN_SECONDS );
    }

    /**
     * Retrieve the sync status metadata.
     *
     * @return array<string, mixed>
     */
    public function get_sync_status() {
        $status = get_transient( self::SYNC_STATUS_KEY );

        return is_array( $status ) ? $status : [ 'state' => 'idle' ];
    }

    /**
     * Schedule an immediate sync run.
     */
    public function trigger_immediate_sync() {
        set_transient(
            self::SYNC_STATUS_KEY,
            [
                'state'      => 'queued',
                'started_at' => current_time( 'timestamp' ),
            ],
            5 * MINUTE_IN_SECONDS
        );

        wp_schedule_single_event( time() + 1, self::CRON_HOOK, [ 'manual' => true ] );
    }

    /**
     * Determine if a tool is connected.
     *
     * @param string $tool Tool slug.
     *
     * @return bool
     */
    public function is_connected( $tool ) {
        $validation = $this->get_validation_state( $tool );

        return ( null !== $this->get_connection( $tool ) && 'success' === $validation['status'] );
    }

    /**
     * Connect to a tool using the appropriate handler.
     *
     * @param string               $tool Tool slug.
     * @param array<string, mixed> $data Additional connection data.
     *
     * @return array<string, string>|true|WP_Error
     */
    public function connect_tool( $tool, array $data = [] ) {
        if ( ! isset( $this->tools[ $tool ] ) ) {
            return new WP_Error( 'invalid_tool', __( 'Unknown marketing tool.', 'insighthub' ) );
        }

        switch ( $tool ) {
            case 'google_site_kit':
                $state     = $this->generate_oauth_state( $tool );
                $auth_url  = $this->get_google_oauth_url( $state );
                $response  = [ 'redirect' => $auth_url ];
                return $response;

            case 'activecampaign':
                $api_url = isset( $data['api_url'] ) ? $data['api_url'] : '';
                $api_key = isset( $data['api_key'] ) ? $data['api_key'] : '';

                $client = new ActiveCampaign_Client( [
                    'api_url' => $api_url,
                    'api_key' => $api_key,
                ] );

                if ( ! $client->validate_credentials() ) {
                    return new WP_Error( 'invalid_credentials', __( 'Invalid ActiveCampaign API URL or key.', 'insighthub' ) );
                }

                $metadata = $client->get_connection_metadata();

                $result = $this->finalize_connection(
                    $tool,
                    $client,
                    [
                        'credentials' => [
                            'api_url' => $api_url,
                            'api_key' => $api_key,
                        ],
                        'metadata'    => $metadata,
                    ]
                );

                if ( ! is_wp_error( $result ) ) {
                    $this->clear_cached_summary( $tool );
                    $this->trigger_immediate_sync();
                }

                return is_wp_error( $result ) ? $result : true;

            case 'clarity':
                $project_id  = isset( $data['project_id'] ) ? $data['project_id'] : '';
                $project_key = isset( $data['project_key'] ) ? $data['project_key'] : '';

                $client = new Clarity_Client( [
                    'project_id'  => $project_id,
                    'project_key' => $project_key,
                ] );

                if ( ! $client->validate_credentials() ) {
                    return new WP_Error( 'invalid_credentials', __( 'Invalid Clarity project credentials.', 'insighthub' ) );
                }

                $metadata = $client->get_connection_metadata();

                $result = $this->finalize_connection(
                    $tool,
                    $client,
                    [
                        'credentials' => [
                            'project_id'  => $project_id,
                            'project_key' => $project_key,
                        ],
                        'metadata'    => $metadata,
                    ]
                );

                if ( ! is_wp_error( $result ) ) {
                    $this->clear_cached_summary( $tool );
                    $this->trigger_immediate_sync();
                }

                return is_wp_error( $result ) ? $result : true;
        }

        return new WP_Error( 'invalid_handler', __( 'Unable to connect with the provided tool.', 'insighthub' ) );
    }

    /**
     * Disconnect a tool.
     *
     * @param string $tool Tool slug.
     *
     * @return true|WP_Error
     */
    public function disconnect_tool( $tool ) {
        if ( ! isset( $this->tools[ $tool ] ) ) {
            return new WP_Error( 'invalid_tool', __( 'Unknown marketing tool.', 'insighthub' ) );
        }

        $connections = get_option( self::OPTION_NAME, [] );

        if ( isset( $connections[ $tool ] ) ) {
            unset( $connections[ $tool ] );
            update_option( self::OPTION_NAME, $connections, false );
        }

        $this->clear_cached_summary( $tool );

        return true;
    }

    /**
     * Create a client instance for the given tool.
     *
     * @param string $tool Tool slug.
     *
     * @return object|WP_Error
     */
    public function get_client( $tool ) {
        if ( ! isset( $this->tools[ $tool ] ) ) {
            return new WP_Error( 'invalid_tool', __( 'Unknown marketing tool.', 'insighthub' ) );
        }

        $connection = $this->get_connection( $tool );
        if ( null === $connection ) {
            return new WP_Error( 'missing_token', __( 'Connect the integration to continue.', 'insighthub' ) );
        }

        $class = $this->tools[ $tool ]['client'];

        if ( ! class_exists( $class ) ) {
            return new WP_Error( 'missing_client', __( 'Integration client not available.', 'insighthub' ) );
        }

        return new $class( $connection['credentials'] );
    }

    /**
     * Handle OAuth callback for Google Site Kit.
     *
     * @param string $tool  Tool slug.
     * @param string $code  Authorization code.
     * @param string $state State nonce.
     *
     * @return true|WP_Error
     */
    public function handle_oauth_callback( $tool, $code, $state ) {
        if ( 'google_site_kit' !== $tool ) {
            return new WP_Error( 'invalid_tool', __( 'Unknown OAuth tool.', 'insighthub' ) );
        }

        if ( empty( $code ) || empty( $state ) ) {
            return new WP_Error( 'invalid_oauth', __( 'Missing authorization details.', 'insighthub' ) );
        }

        if ( ! $this->verify_oauth_state( $tool, $state ) ) {
            return new WP_Error( 'invalid_oauth_state', __( 'OAuth state did not match. Please retry.', 'insighthub' ) );
        }

        $client       = new Google_Site_Kit_Client( [] );
        $credentials  = $client->exchange_code_for_tokens( $code );
        $metadata     = $client->get_connection_metadata();

        $result = $this->finalize_connection(
            $tool,
            $client,
            [
                'credentials' => $credentials,
                'metadata'    => $metadata,
            ]
        );

        if ( ! is_wp_error( $result ) ) {
            $this->clear_cached_summary( $tool );
            $this->trigger_immediate_sync();
        }

        return is_wp_error( $result ) ? $result : true;
    }

    /**
     * Retrieve saved connection metadata for dashboard display.
     *
     * @param string $tool Tool slug.
     *
     * @return array<string, mixed>
     */
    public function get_connection_metadata( $tool ) {
        $connection = $this->get_connection( $tool );

        if ( isset( $connection['metadata'] ) && is_array( $connection['metadata'] ) ) {
            return $connection['metadata'];
        }

        return [];
    }

    /**
     * Get validation state for the integration.
     *
     * @param string $tool Tool slug.
     *
     * @return array<string, mixed>
     */
    public function get_validation_state( $tool ) {
        $connection = $this->get_connection( $tool );

        if ( isset( $connection['validation'] ) && is_array( $connection['validation'] ) ) {
            return wp_parse_args(
                $connection['validation'],
                [
                    'status'          => 'unknown',
                    'checked_at'      => null,
                    'last_success_at' => null,
                    'message'         => '',
                ]
            );
        }

        return [
            'status'          => 'unknown',
            'checked_at'      => null,
            'last_success_at' => null,
            'message'         => '',
        ];
    }

    /**
     * Record a successful sync or validation for the integration.
     *
     * @param string $tool    Tool slug.
     * @param string $message Optional message to store alongside the validation.
     */
    public function mark_successful_sync( $tool, $message = '' ) {
        $connection = $this->get_connection( $tool );

        if ( null === $connection ) {
            return;
        }

        $connection = $this->apply_validation_result(
            $tool,
            $connection,
            true,
            $message ?: __( 'Validation refreshed from dashboard.', 'insighthub' )
        );

        $this->persist_connection( $tool, $connection );
    }

    /**
     * Persist the connection data in the database.
     *
     * @param string               $tool       Tool slug.
     * @param array<string, mixed> $connection Connection payload.
     */
    private function persist_connection( $tool, array $connection ) {
        $connections         = get_option( self::OPTION_NAME, [] );
        $connection['stored_at'] = isset( $connection['stored_at'] ) ? $connection['stored_at'] : ( isset( $connections[ $tool ]['stored_at'] ) ? $connections[ $tool ]['stored_at'] : current_time( 'timestamp' ) );
        $connections[ $tool ] = $connection;

        update_option( self::OPTION_NAME, $connections, false );
    }

    /**
     * Validate a connection using the provided client and persist the state.
     *
     * @param string $tool       Tool slug.
     * @param object $client     Integration client instance.
     * @param array  $connection Connection payload.
     *
     * @return bool|WP_Error
     */
    private function finalize_connection( $tool, $client, array $connection ) {
        $validation_result = $this->validate_with_client( $tool, $client );
        $connection        = $this->apply_validation_result( $tool, $connection, $validation_result );

        $this->persist_connection( $tool, $connection );

        return $validation_result;
    }

    /**
     * Run a validation call on the integration client when available.
     *
     * @param string $tool   Tool slug.
     * @param object $client Integration client instance.
     *
     * @return bool|WP_Error
     */
    private function validate_with_client( $tool, $client ) {
        if ( ! is_object( $client ) ) {
            return new WP_Error( 'missing_client', __( 'Integration client not available.', 'insighthub' ) );
        }

        if ( method_exists( $client, 'validate_connection' ) ) {
            $result = $client->validate_connection();

            if ( is_wp_error( $result ) ) {
                return $result;
            }

            if ( ! $result ) {
                return new WP_Error( 'validation_failed', __( 'The integration could not be validated.', 'insighthub' ) );
            }

            return true;
        }

        return true;
    }

    /**
     * Append validation metadata to a connection.
     *
     * @param string          $tool              Tool slug.
     * @param array           $connection        Connection payload.
     * @param bool|WP_Error   $validation_result Validation result to record.
     * @param string          $message_override  Optional message to override default validation message.
     *
     * @return array<string, mixed>
     */
    private function apply_validation_result( $tool, array $connection, $validation_result, $message_override = '' ) {
        $existing            = $this->get_connection( $tool );
        $previous_validation = [];

        if ( isset( $existing['validation'] ) && is_array( $existing['validation'] ) ) {
            $previous_validation = $existing['validation'];
        }

        $timestamp     = current_time( 'timestamp' );
        $status        = is_wp_error( $validation_result ) ? 'failed' : 'success';
        $message       = $message_override ?: ( is_wp_error( $validation_result ) ? $validation_result->get_error_message() : __( 'Validation succeeded.', 'insighthub' ) );
        $last_success  = ( 'success' === $status ) ? $timestamp : ( isset( $previous_validation['last_success_at'] ) ? $previous_validation['last_success_at'] : null );

        $connection['validation'] = [
            'status'          => $status,
            'checked_at'      => $timestamp,
            'last_success_at' => $last_success,
            'message'         => $message,
        ];

        return $connection;
    }

    /**
     * Build the Google OAuth consent URL.
     *
     * @param string $state State nonce.
     *
     * @return string
     */
    private function get_google_oauth_url( $state ) {
        $redirect = admin_url( 'admin-post.php?action=insighthub_oauth_callback&tool=google_site_kit' );

        $params = [
            'response_type' => 'code',
            'client_id'     => 'insighthub-demo-client',
            'redirect_uri'  => $redirect,
            'scope'         => 'https://www.googleapis.com/auth/siteverification https://www.googleapis.com/auth/analytics.readonly',
            'state'         => $state,
        ];

        return add_query_arg( $params, 'https://accounts.google.com/o/oauth2/v2/auth' );
    }

    /**
     * Create an OAuth state nonce stored temporarily.
     *
     * @param string $tool Tool slug.
     *
     * @return string
     */
    private function generate_oauth_state( $tool ) {
        $state = wp_generate_uuid4();
        set_transient( 'insighthub_oauth_state_' . $state, $tool, 10 * MINUTE_IN_SECONDS );

        return $state;
    }

    /**
     * Validate the OAuth state nonce.
     *
     * @param string $tool  Tool slug.
     * @param string $state Received state value.
     *
     * @return bool
     */
    private function verify_oauth_state( $tool, $state ) {
        $stored_tool = get_transient( 'insighthub_oauth_state_' . $state );
        delete_transient( 'insighthub_oauth_state_' . $state );

        return ( $stored_tool === $tool );
    }

    /**
     * Handle AJAX refresh request.
     */
    public function handle_ajax_refresh() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Insufficient permissions.', 'insighthub' ) );
        }

        $this->trigger_immediate_sync();
        wp_send_json_success( [ 'status' => 'queued' ] );
    }

    /**
     * Handle manual refresh form submissions.
     */
    public function handle_manual_refresh() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'insighthub' ) );
        }

        check_admin_referer( 'insighthub_refresh_now' );

        $this->trigger_immediate_sync();

        $redirect = add_query_arg(
            [
                'page'              => Admin_Page::MENU_SLUG,
                'insighthub_notice' => 'refresh_queued',
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Execute background sync for all connected tools.
     *
     * @param array<string, mixed> $args Optional cron args.
     */
    public function run_background_sync( $args = [] ) {
        if ( $this->is_sync_running() ) {
            return;
        }

        $this->set_sync_running( true );

        foreach ( $this->tools as $slug => $tool ) {
            if ( ! $this->is_connected( $slug ) ) {
                $this->clear_cached_summary( $slug );
                continue;
            }

            $client = $this->get_client( $slug );

            if ( is_wp_error( $client ) ) {
                $this->clear_cached_summary( $slug );
                continue;
            }

            if ( method_exists( $client, 'fetch_latest_data' ) ) {
                $data = $client->fetch_latest_data();
                $this->cache_summary( $slug, is_array( $data ) ? $data : [] );
                $this->mark_successful_sync( $slug, __( 'Cached summary updated.', 'insighthub' ) );
            }
        }

        $this->set_sync_running( false );
    }
}
