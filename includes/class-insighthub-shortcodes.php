<?php
/**
 * Shortcode handler registration.
 *
 * @package InsightHub
 */

namespace InsightHub;

defined( 'ABSPATH' ) || exit;

/**
 * Class Shortcodes
 *
 * Registers plugin shortcodes.
 */
class Shortcodes {
    /**
     * Stats service instance.
     *
     * @var Stats_Service
     */
    private $stats_service;

    /**
     * Shortcodes constructor.
     *
     * @param Stats_Service $stats_service Stats service dependency.
     */
    public function __construct( Stats_Service $stats_service ) {
        $this->stats_service = $stats_service;

        add_shortcode( 'insighthub_stats', [ $this, 'render_stats_shortcode' ] );
    }

    /**
     * Render the [insighthub_stats] shortcode content.
     *
     * @return string
     */
    public function render_stats_shortcode() {
        $totals = $this->stats_service->get_totals();

        ob_start();
        ?>
        <div class="insighthub-stats-box">
            <p><strong><?php esc_html_e( 'Total Posts:', 'insighthub' ); ?></strong> <?php echo esc_html( number_format_i18n( $totals['posts'] ) ); ?></p>
            <p><strong><?php esc_html_e( 'Total Comments:', 'insighthub' ); ?></strong> <?php echo esc_html( number_format_i18n( $totals['comments'] ) ); ?></p>
            <p><strong><?php esc_html_e( 'Total Users:', 'insighthub' ); ?></strong> <?php echo esc_html( number_format_i18n( $totals['users'] ) ); ?></p>
        </div>
        <?php
        return trim( ob_get_clean() );
    }
}
