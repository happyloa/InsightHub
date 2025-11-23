<?php
/**
 * Admin page rendering and menu registration.
 *
 * @package InsightHub
 */

namespace InsightHub;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_Page
 *
 * Handles admin menu registration and dashboard rendering.
 */
class Admin_Page {
    /**
     * Menu slug for the dashboard page.
     */
    const MENU_SLUG = 'insighthub-dashboard';

    /**
     * Stats service instance.
     *
     * @var Stats_Service
     */
    private $stats_service;

    /**
     * Admin page hook suffix.
     *
     * @var string|null
     */
    private $hook_suffix = null;

    /**
     * Admin_Page constructor.
     *
     * @param Stats_Service $stats_service Stats service dependency.
     */
    public function __construct( Stats_Service $stats_service ) {
        $this->stats_service = $stats_service;

        add_action( 'admin_menu', [ $this, 'register_menu' ] );
    }

    /**
     * Register the InsightHub admin menu and dashboard page.
     */
    public function register_menu() {
        $this->hook_suffix = add_menu_page(
            __( 'InsightHub Dashboard', 'insighthub' ),
            __( 'InsightHub', 'insighthub' ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_dashboard' ],
            'dashicons-chart-area',
            3
        );
    }

    /**
     * Determine if the given hook corresponds to the plugin dashboard page.
     *
     * @param string $hook Current admin page hook.
     *
     * @return bool
     */
    public function is_plugin_page( $hook ) {
        return ( $this->hook_suffix === $hook );
    }

    /**
     * Render the dashboard page.
     */
    public function render_dashboard() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'insighthub' ) );
        }

        $totals             = $this->stats_service->get_totals();
        $recent_activity    = $this->stats_service->get_recent_activity();
        $post_type_totals   = $this->stats_service->get_post_type_totals();
        $woocommerce_totals = $this->stats_service->get_woocommerce_activity( 30 );
        ?>
        <div class="wrap insighthub-dashboard">
            <h1><?php esc_html_e( 'InsightHub Dashboard', 'insighthub' ); ?></h1>

            <div class="insighthub-cards">
                <div class="card">
                    <h2><?php esc_html_e( 'Total Posts', 'insighthub' ); ?></h2>
                    <p class="insighthub-number"><?php echo esc_html( number_format_i18n( $totals['posts'] ) ); ?></p>
                </div>
                <div class="card">
                    <h2><?php esc_html_e( 'Total Pages', 'insighthub' ); ?></h2>
                    <p class="insighthub-number"><?php echo esc_html( number_format_i18n( $totals['pages'] ) ); ?></p>
                </div>
                <div class="card">
                    <h2><?php esc_html_e( 'Total Comments', 'insighthub' ); ?></h2>
                    <p class="insighthub-number"><?php echo esc_html( number_format_i18n( $totals['comments'] ) ); ?></p>
                </div>
                <div class="card">
                    <h2><?php esc_html_e( 'Total Users', 'insighthub' ); ?></h2>
                    <p class="insighthub-number"><?php echo esc_html( number_format_i18n( $totals['users'] ) ); ?></p>
                </div>
                <div class="card">
                    <h2><?php esc_html_e( 'Categories', 'insighthub' ); ?></h2>
                    <p class="insighthub-number"><?php echo esc_html( number_format_i18n( $totals['categories'] ) ); ?></p>
                </div>
                <div class="card">
                    <h2><?php esc_html_e( 'Tags', 'insighthub' ); ?></h2>
                    <p class="insighthub-number"><?php echo esc_html( number_format_i18n( $totals['tags'] ) ); ?></p>
                </div>
            </div>

            <div class="card insighthub-recent-activity">
                <h2><?php esc_html_e( 'Recent Activity', 'insighthub' ); ?></h2>
                <ul>
                    <li>
                        <strong><?php esc_html_e( 'Posts in last 7 days:', 'insighthub' ); ?></strong>
                        <?php echo esc_html( number_format_i18n( $recent_activity['posts_7_days'] ) ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Posts in last 30 days:', 'insighthub' ); ?></strong>
                        <?php echo esc_html( number_format_i18n( $recent_activity['posts_30_days'] ) ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Comments in last 7 days:', 'insighthub' ); ?></strong>
                        <?php echo esc_html( number_format_i18n( $recent_activity['comments_7_days'] ) ); ?>
                    </li>
                    <li>
                        <strong><?php esc_html_e( 'Comments in last 30 days:', 'insighthub' ); ?></strong>
                        <?php echo esc_html( number_format_i18n( $recent_activity['comments_30_days'] ) ); ?>
                    </li>
                </ul>
            </div>

            <div class="card">
                <h2><?php esc_html_e( 'Post Type Totals', 'insighthub' ); ?></h2>
                <table class="widefat striped insighthub-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Post Type', 'insighthub' ); ?></th>
                            <th><?php esc_html_e( 'Published Count', 'insighthub' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $post_type_totals as $post_type => $data ) : ?>
                        <tr>
                            <td><?php echo esc_html( $data['label'] ); ?></td>
                            <td><?php echo esc_html( number_format_i18n( $data['count'] ) ); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ( ! empty( $woocommerce_totals ) ) : ?>
                <div class="card insighthub-woocommerce">
                    <h2><?php esc_html_e( 'WooCommerce (last 30 days)', 'insighthub' ); ?></h2>
                    <ul>
                        <li>
                            <strong><?php esc_html_e( 'Orders:', 'insighthub' ); ?></strong>
                            <?php echo esc_html( number_format_i18n( $woocommerce_totals['orders'] ) ); ?>
                        </li>
                        <li>
                            <strong><?php esc_html_e( 'Sales Total:', 'insighthub' ); ?></strong>
                            <?php echo esc_html( wc_price( $woocommerce_totals['sales_total'] ) ); ?>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
