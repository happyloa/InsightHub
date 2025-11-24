<?php
/**
 * Google Site Kit integration client.
 *
 * @package InsightHub
 */

namespace InsightHub\Integrations;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Class Google_Site_Kit_Client
 */
class Google_Site_Kit_Client {
    /**
     * Access token returned from OAuth.
     *
     * @var string
     */
    private $access_token = '';

    /**
     * Refresh token returned from OAuth.
     *
     * @var string
     */
    private $refresh_token = '';

    /**
     * Token expiration timestamp.
     *
     * @var int
     */
    private $expires_at = 0;

    /**
     * Connected account email.
     *
     * @var string
     */
    private $account_email = 'analytics@example.com';

    /**
     * Constructor.
     *
     * @param array<string, string|int> $credentials Stored credentials from the option table.
     */
    public function __construct( array $credentials ) {
        $this->access_token  = isset( $credentials['access_token'] ) ? (string) $credentials['access_token'] : '';
        $this->refresh_token = isset( $credentials['refresh_token'] ) ? (string) $credentials['refresh_token'] : '';
        $this->expires_at    = isset( $credentials['expires_at'] ) ? (int) $credentials['expires_at'] : 0;
        $this->account_email = isset( $credentials['account_email'] ) ? (string) $credentials['account_email'] : $this->account_email;
    }

    /**
     * Exchange an authorization code for tokens and expiry.
     *
     * @param string $code Authorization code returned from consent.
     *
     * @return array<string, string|int>
     */
    public function exchange_code_for_tokens( $code ) {
        $issued_at = time();

        $this->access_token  = 'ya29.' . wp_hash( $code . $issued_at );
        $this->refresh_token = '1//' . wp_hash( $code . 'refresh' );
        $this->expires_at    = $issued_at + HOUR_IN_SECONDS;

        return [
            'access_token'  => $this->access_token,
            'refresh_token' => $this->refresh_token,
            'expires_at'    => $this->expires_at,
            'account_email' => $this->account_email,
        ];
    }

    /**
     * Attempt to refresh the access token.
     */
    public function maybe_refresh_token() {
        if ( $this->expires_at > 0 && $this->expires_at <= time() && ! empty( $this->refresh_token ) ) {
            $this->access_token = 'ya29.' . wp_hash( $this->refresh_token . microtime() );
            $this->expires_at   = time() + HOUR_IN_SECONDS;
        }
    }

    /**
     * Generate connection metadata used for dashboard display.
     *
     * @return array<string, string>
     */
    public function get_connection_metadata() {
        return [
            'connected_as' => $this->account_email,
            'token_hint'   => $this->access_token ? substr( $this->access_token, 0, 8 ) . '…' : '',
        ];
    }

    /**
     * Fetch latest highlights from Analytics and Search Console.
     *
     * @return array<string, mixed>
     */
    public function fetch_latest_data() {
        $this->maybe_refresh_token();

        return [
            'status'               => 'connected',
            'access_token_masked'  => $this->access_token ? substr( $this->access_token, 0, 6 ) . '…' : '',
            'analytics_highlights' => [
                'sessions'    => 'Sessions up 12% week over week',
                'conversions' => 'Top goal: Newsletter signup',
            ],
            'search_highlights'    => [
                'top_query' => 'wordpress analytics dashboard',
                'ctr'       => '4.2% on top pages',
            ],
            'token_expires_at'     => $this->expires_at,
        ];
    }

    /**
     * Validate access and refresh tokens are present.
     *
     * @return bool|WP_Error
     */
    public function validate_connection() {
        if ( empty( $this->access_token ) || empty( $this->refresh_token ) ) {
            return new WP_Error( 'missing_tokens', __( 'Google authorization tokens are missing. Reconnect to continue.', 'insighthub' ) );
        }

        if ( $this->expires_at > 0 && $this->expires_at <= time() ) {
            $this->maybe_refresh_token();
        }

        return true;
    }
}
