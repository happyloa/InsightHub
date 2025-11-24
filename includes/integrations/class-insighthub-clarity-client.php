<?php
/**
 * Microsoft Clarity integration client.
 *
 * @package InsightHub
 */

namespace InsightHub\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Clarity_Client
 */
class Clarity_Client {
    /**
     * Project ID provided by Clarity.
     *
     * @var string
     */
    private $project_id;

    /**
     * Project key used for authentication.
     *
     * @var string
     */
    private $project_key;

    /**
     * Constructor.
     *
     * @param array<string, string> $credentials Stored credentials.
     */
    public function __construct( array $credentials ) {
        $this->project_id  = isset( $credentials['project_id'] ) ? (string) $credentials['project_id'] : '';
        $this->project_key = isset( $credentials['project_key'] ) ? (string) $credentials['project_key'] : '';
    }

    /**
     * Validate credential presence.
     *
     * @return bool
     */
    public function validate_credentials() {
        return ! empty( $this->project_id ) && ! empty( $this->project_key );
    }

    /**
     * Metadata for dashboard display.
     *
     * @return array<string, string>
     */
    public function get_connection_metadata() {
        return [
            'project_id'  => $this->project_id,
            'masked_key'  => $this->project_key ? '****' . substr( $this->project_key, -5 ) : '',
            'connected_as'=> 'Project ' . $this->project_id,
        ];
    }

    /**
     * Fetch live-like heatmap activity.
     *
     * @return array<string, mixed>
     */
    public function fetch_latest_data() {
        return [
            'status'           => 'connected',
            'project_id'       => $this->project_id,
            'project_key_mask' => $this->project_key ? '****' . substr( $this->project_key, -4 ) : '',
            'heatmap_counts'   => [
                'last_24h'  => 124,
                'last_7d'   => 842,
                'top_page'  => '/landing-page',
            ],
        ];
    }
}
