<?php
/**
 * Microsoft Clarity integration client stub.
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
     * Token used for API calls.
     *
     * @var string
     */
    private $token;

    /**
     * Constructor.
     *
     * @param string $token API token placeholder.
     */
    public function __construct( $token ) {
        $this->token = $token;
    }

    /**
     * Fetch sample data.
     *
     * @return array<string, mixed>
     */
    public function fetch_latest_data() {
        return [
            'status' => 'connected',
            'token'  => substr( $this->token, 0, 6 ) . '…',
            'notes'  => 'Recording heatmaps for top landing page.',
        ];
    }
}
