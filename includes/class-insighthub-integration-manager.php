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

            if ( $updated ) {
                $connections[ $tool ] = $connection;
                update_option( self::OPTION_NAME, $connections, false );
            }

            return $connection;
        }

        return null;
    }

    /**
     * Determine if a tool is connected.
     *
     * @param string $tool Tool slug.
     *
     * @return bool
     */
    public function is_connected( $tool ) {
        return null !== $this->get_connection( $tool );
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
                $this->persist_connection(
                    $tool,
                    [
                        'credentials' => [
                            'api_url' => $api_url,
                            'api_key' => $api_key,
                        ],
                        'metadata'    => $metadata,
                    ]
                );

                return true;

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
                $this->persist_connection(
                    $tool,
                    [
                        'credentials' => [
                            'project_id'  => $project_id,
                            'project_key' => $project_key,
                        ],
                        'metadata'    => $metadata,
                    ]
                );

                return true;
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

        $this->persist_connection(
            $tool,
            [
                'credentials' => $credentials,
                'metadata'    => $metadata,
            ]
        );

        return true;
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
     * Persist the connection data in the database.
     *
     * @param string               $tool       Tool slug.
     * @param array<string, mixed> $connection Connection payload.
     */
    private function persist_connection( $tool, array $connection ) {
        $connections         = get_option( self::OPTION_NAME, [] );
        $connection['stored_at'] = current_time( 'timestamp' );
        $connections[ $tool ] = $connection;

        update_option( self::OPTION_NAME, $connections, false );
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
}
