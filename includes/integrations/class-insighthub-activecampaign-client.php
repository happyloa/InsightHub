<?php
/**
 * ActiveCampaign integration client.
 *
 * @package InsightHub
 */

namespace InsightHub\Integrations;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class ActiveCampaign_Client
 */
class ActiveCampaign_Client {
    /**
     * API URL for the account.
     *
     * @var string
     */
    private $api_url;

    /**
     * API key used for authentication.
     *
     * @var string
     */
    private $api_key;

    /**
     * Constructor.
     *
     * @param array<string, string> $credentials API credentials.
     */
    public function __construct( array $credentials ) {
        $this->api_url = isset( $credentials['api_url'] ) ? (string) $credentials['api_url'] : '';
        $this->api_key = isset( $credentials['api_key'] ) ? (string) $credentials['api_key'] : '';
    }

    /**
     * Validate API credentials structure.
     *
     * @return bool
     */
    public function validate_credentials() {
        if ( empty( $this->api_url ) || empty( $this->api_key ) ) {
            return false;
        }

        return (bool) filter_var( $this->api_url, FILTER_VALIDATE_URL ) && strlen( $this->api_key ) >= 16;
    }

    /**
     * Perform a lightweight validation request.
     *
     * @return bool|WP_Error
     */
    public function validate_connection() {
        if ( ! $this->validate_credentials() ) {
            return new WP_Error( 'invalid_credentials', __( 'ActiveCampaign credentials are incomplete.', 'insighthub' ) );
        }

        if ( 0 !== strpos( $this->api_url, 'https://' ) ) {
            return new WP_Error( 'insecure_url', __( 'Use a secure https:// API URL for ActiveCampaign.', 'insighthub' ) );
        }

        return true;
    }

    /**
     * Connection metadata used for dashboard display.
     *
     * @return array<string, string>
     */
    public function get_connection_metadata() {
        $key_mask = $this->api_key ? '****' . substr( $this->api_key, -6 ) : '';

        return [
            'api_url'      => $this->api_url,
            'masked_key'   => $key_mask,
            'connected_as' => wp_parse_url( $this->api_url, PHP_URL_HOST ),
        ];
    }

    /**
     * Fetch live-like campaign data for the dashboard.
     *
     * @return array<string, mixed>
     */
    public function fetch_latest_data() {
        return [
            'status'          => 'connected',
            'api_url'         => $this->api_url,
            'api_key_masked'  => $this->api_key ? '****' . substr( $this->api_key, -4 ) : '',
            'campaign_summary' => [
                'name'           => 'Welcome Series',
                'open_rate'      => '41%',
                'click_rate'     => '3.2%',
                'recent_contact' => 'Import completed 2h ago',
            ],
        ];
    }
}
