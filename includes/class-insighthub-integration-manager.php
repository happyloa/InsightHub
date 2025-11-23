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
        ],
        'activecampaign' => [
            'label'       => 'ActiveCampaign',
            'description' => 'Sync marketing automation and campaign performance.',
            'client'      => ActiveCampaign_Client::class,
        ],
        'clarity'        => [
            'label'       => 'Microsoft Clarity',
            'description' => 'View session recordings and heatmap highlights.',
            'client'      => Clarity_Client::class,
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
     * Retrieve stored token for a tool.
     *
     * @param string $tool Tool slug.
     *
     * @return string|null
     */
    public function get_token( $tool ) {
        $tokens = get_option( self::OPTION_NAME, [] );

        if ( isset( $tokens[ $tool ] ) ) {
            return $tokens[ $tool ];
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
        return null !== $this->get_token( $tool );
    }

    /**
     * Connect to a tool by generating a token placeholder.
     *
     * @param string $tool Tool slug.
     *
     * @return string|WP_Error
     */
    public function connect_tool( $tool ) {
        if ( ! isset( $this->tools[ $tool ] ) ) {
            return new WP_Error( 'invalid_tool', __( 'Unknown marketing tool.', 'insighthub' ) );
        }

        $tokens          = get_option( self::OPTION_NAME, [] );
        $generated_token = wp_generate_password( 32, false, false );

        $tokens[ $tool ] = $generated_token;
        update_option( self::OPTION_NAME, $tokens, false );

        return $generated_token;
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

        $tokens = get_option( self::OPTION_NAME, [] );

        if ( isset( $tokens[ $tool ] ) ) {
            unset( $tokens[ $tool ] );
            update_option( self::OPTION_NAME, $tokens, false );
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

        $token = $this->get_token( $tool );
        if ( null === $token ) {
            return new WP_Error( 'missing_token', __( 'Connect the integration to continue.', 'insighthub' ) );
        }

        $class = $this->tools[ $tool ]['client'];

        if ( ! class_exists( $class ) ) {
            return new WP_Error( 'missing_client', __( 'Integration client not available.', 'insighthub' ) );
        }

        return new $class( $token );
    }
}
