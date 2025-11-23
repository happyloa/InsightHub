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
     * Integration manager instance.
     *
     * @var Integration_Manager
     */
    private $integration_manager;

    /**
     * Admin_Page constructor.
     *
     * @param Stats_Service       $stats_service       Stats service dependency.
     * @param Integration_Manager $integration_manager Integration manager dependency.
     */
    public function __construct( Stats_Service $stats_service, Integration_Manager $integration_manager ) {
        $this->stats_service       = $stats_service;
        $this->integration_manager = $integration_manager;

        add_action( 'admin_menu', [ $this, 'register_menu' ] );
        add_action( 'admin_post_insighthub_connect_tool', [ $this, 'handle_connect_tool' ] );
        add_action( 'admin_post_insighthub_disconnect_tool', [ $this, 'handle_disconnect_tool' ] );
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
        $tools              = $this->integration_manager->get_tools();
        ?>
        <div class="wrap insighthub-dashboard">
            <h1><?php esc_html_e( 'InsightHub Dashboard', 'insighthub' ); ?></h1>

            <?php $this->render_notices(); ?>

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

            <div class="card insighthub-integrations">
                <h2><?php esc_html_e( 'Marketing Integrations', 'insighthub' ); ?></h2>
                <p><?php esc_html_e( 'Connect your marketing tools to pull key insights into InsightHub.', 'insighthub' ); ?></p>
                <table class="widefat striped insighthub-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Tool', 'insighthub' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'insighthub' ); ?></th>
                            <th><?php esc_html_e( 'Latest Data', 'insighthub' ); ?></th>
                            <th><?php esc_html_e( 'Action', 'insighthub' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $tools as $slug => $tool ) :
                        $connected = $this->integration_manager->is_connected( $slug );
                        $client    = $this->integration_manager->get_client( $slug );
                        $data      = is_wp_error( $client ) ? [] : $client->fetch_latest_data();
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html( $tool['label'] ); ?></strong>
                                <p class="description"><?php echo esc_html( $tool['description'] ); ?></p>
                            </td>
                            <td>
                                <?php if ( $connected ) : ?>
                                    <span class="status-connected"><?php esc_html_e( 'Connected', 'insighthub' ); ?></span>
                                <?php else : ?>
                                    <span class="status-disconnected"><?php esc_html_e( 'Not connected', 'insighthub' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( ! empty( $data ) ) : ?>
                                    <ul>
                                        <?php foreach ( $data as $key => $value ) : ?>
                                            <li><strong><?php echo esc_html( ucfirst( $key ) ); ?>:</strong> <?php echo esc_html( is_array( $value ) ? wp_json_encode( $value ) : $value ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else : ?>
                                    <span class="description"><?php esc_html_e( 'Connect to start syncing data.', 'insighthub' ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ( $connected ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <input type="hidden" name="action" value="insighthub_disconnect_tool" />
                                        <input type="hidden" name="tool" value="<?php echo esc_attr( $slug ); ?>" />
                                        <?php wp_nonce_field( 'insighthub_disconnect_' . $slug ); ?>
                                        <button class="button button-secondary" type="submit"><?php esc_html_e( 'Disconnect', 'insighthub' ); ?></button>
                                    </form>
                                <?php else : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <input type="hidden" name="action" value="insighthub_connect_tool" />
                                        <input type="hidden" name="tool" value="<?php echo esc_attr( $slug ); ?>" />
                                        <?php wp_nonce_field( 'insighthub_connect_' . $slug ); ?>
                                        <button class="button button-primary" type="submit"><?php esc_html_e( 'Connect', 'insighthub' ); ?></button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * Handle integration connect requests.
     */
    public function handle_connect_tool() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'insighthub' ) );
        }

        $tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';
        check_admin_referer( 'insighthub_connect_' . $tool );

        $result = $this->integration_manager->connect_tool( $tool );

        $notice = is_wp_error( $result ) ? 'connect_error' : 'connected';
        $redirect = add_query_arg(
            [
                'page'               => self::MENU_SLUG,
                'insighthub_notice'  => $notice,
                'insighthub_tool'    => $tool,
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Handle integration disconnect requests.
     */
    public function handle_disconnect_tool() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'insighthub' ) );
        }

        $tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';
        check_admin_referer( 'insighthub_disconnect_' . $tool );

        $result = $this->integration_manager->disconnect_tool( $tool );

        $notice = is_wp_error( $result ) ? 'disconnect_error' : 'disconnected';
        $redirect = add_query_arg(
            [
                'page'               => self::MENU_SLUG,
                'insighthub_notice'  => $notice,
                'insighthub_tool'    => $tool,
            ],
            admin_url( 'admin.php' )
        );

        wp_safe_redirect( $redirect );
        exit;
    }

    /**
     * Render admin notices for integration actions.
     */
    private function render_notices() {
        if ( empty( $_GET['insighthub_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $notice = sanitize_key( wp_unslash( $_GET['insighthub_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $tool   = isset( $_GET['insighthub_tool'] ) ? sanitize_key( wp_unslash( $_GET['insighthub_tool'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $messages = [
            'connected'        => __( 'Integration connected successfully.', 'insighthub' ),
            'disconnected'     => __( 'Integration disconnected.', 'insighthub' ),
            'connect_error'    => __( 'Unable to connect. Please try again.', 'insighthub' ),
            'disconnect_error' => __( 'Unable to disconnect. Please try again.', 'insighthub' ),
        ];

        if ( ! isset( $messages[ $notice ] ) ) {
            return;
        }

        $class = in_array( $notice, [ 'connect_error', 'disconnect_error' ], true ) ? 'notice-error' : 'notice-success';

        echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible">';
        echo '<p>' . esc_html( $messages[ $notice ] ) . ' ' . esc_html( $tool ) . '</p>';
        echo '</div>';
    }
}
