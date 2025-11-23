<?php
/**
 * ActiveCampaign integration client stub.
 *
 * @package InsightHub
 */

namespace InsightHub\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class ActiveCampaign_Client
 */
class ActiveCampaign_Client {
    /**
     * Stored token reference.
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
            'status'   => 'connected',
            'token'    => substr( $this->token, 0, 6 ) . '…',
            'campaign' => 'Welcome Series',
            'metric'   => 'CTR 3.2%',
        ];
    }
}
