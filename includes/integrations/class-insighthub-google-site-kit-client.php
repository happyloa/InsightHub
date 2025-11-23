<?php
/**
 * Google Site Kit integration client stub.
 *
 * @package InsightHub
 */

namespace InsightHub\Integrations;

defined( 'ABSPATH' ) || exit;

/**
 * Class Google_Site_Kit_Client
 */
class Google_Site_Kit_Client {
    /**
     * Token used for API calls.
     *
     * @var string
     */
    private $token;

    /**
     * Constructor.
     *
     * @param string $token OAuth token placeholder.
     */
    public function __construct( $token ) {
        $this->token = $token;
    }

    /**
     * Fetch sample data to demonstrate a connected integration.
     *
     * @return array<string, mixed>
     */
    public function fetch_latest_data() {
        return [
            'status' => 'connected',
            'token'  => substr( $this->token, 0, 6 ) . '…',
            'data'   => [
                'analytics' => 'Traffic up 12% week over week.',
                'search'    => 'Top query: WordPress analytics.',
            ],
        ];
    }
}
