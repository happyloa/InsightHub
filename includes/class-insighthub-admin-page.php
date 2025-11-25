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
        add_action( 'admin_post_insighthub_oauth_callback', [ $this, 'handle_oauth_callback' ] );
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
        $sync_status        = $this->integration_manager->get_sync_status();
        $sync_running       = $this->integration_manager->is_sync_running();
        ?>
        <div class="wrap insighthub-dashboard">
            <div class="insighthub-hero">
                <div>
                    <p class="insighthub-hero__eyebrow"><?php esc_html_e( 'WordPress Insights', 'insighthub' ); ?></p>
                    <h1><?php esc_html_e( 'InsightHub Dashboard', 'insighthub' ); ?></h1>
                    <p class="insighthub-hero__lede"><?php esc_html_e( 'Monitor content performance, recent activity, and integration health in one place.', 'insighthub' ); ?></p>
                </div>
                <div class="insighthub-hero__meta">
                    <span class="insighthub-badge">
                        <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                        <?php echo esc_html( sprintf( __( 'Updated %s', 'insighthub' ), date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ); ?>
                    </span>
                    <span class="insighthub-badge">
                        <span class="dashicons dashicons-shield-alt" aria-hidden="true"></span>
                        <?php esc_html_e( 'Secure admin view', 'insighthub' ); ?>
                    </span>
                    <span class="insighthub-badge">
                        <?php if ( $sync_running ) : ?>
                            <span class="dashicons dashicons-update spin" aria-hidden="true"></span>
                            <?php esc_html_e( 'Updating integrations…', 'insighthub' ); ?>
                        <?php elseif ( isset( $sync_status['ended_at'] ) ) : ?>
                            <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                            <?php printf( esc_html__( 'Last refresh: %s', 'insighthub' ), esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $sync_status['ended_at'] ) ) ); ?>
                        <?php else : ?>
                            <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                            <?php esc_html_e( 'Integrations idle', 'insighthub' ); ?>
                        <?php endif; ?>
                    </span>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="insighthub-refresh-form">
                        <input type="hidden" name="action" value="insighthub_refresh_integrations" />
                        <?php wp_nonce_field( 'insighthub_refresh_now' ); ?>
                        <button type="submit" class="button button-secondary" <?php disabled( $sync_running ); ?>>
                            <?php esc_html_e( 'Refresh now', 'insighthub' ); ?>
                        </button>
                    </form>
                </div>
            </div>

            <?php $this->render_notices(); ?>

            <div class="insighthub-stats-grid" role="list">
                <div class="insighthub-stat" role="listitem">
                    <div class="insighthub-stat__label">
                        <span><?php esc_html_e( 'Total Posts', 'insighthub' ); ?></span>
                        <span class="dashicons dashicons-media-text" aria-hidden="true"></span>
                    </div>
                    <p class="insighthub-stat__value"><?php echo esc_html( number_format_i18n( $totals['posts'] ) ); ?></p>
                </div>
                <div class="insighthub-stat" role="listitem">
                    <div class="insighthub-stat__label">
                        <span><?php esc_html_e( 'Total Pages', 'insighthub' ); ?></span>
                        <span class="dashicons dashicons-media-default" aria-hidden="true"></span>
                    </div>
                    <p class="insighthub-stat__value"><?php echo esc_html( number_format_i18n( $totals['pages'] ) ); ?></p>
                </div>
                <div class="insighthub-stat" role="listitem">
                    <div class="insighthub-stat__label">
                        <span><?php esc_html_e( 'Total Comments', 'insighthub' ); ?></span>
                        <span class="dashicons dashicons-admin-comments" aria-hidden="true"></span>
                    </div>
                    <p class="insighthub-stat__value"><?php echo esc_html( number_format_i18n( $totals['comments'] ) ); ?></p>
                </div>
                <div class="insighthub-stat" role="listitem">
                    <div class="insighthub-stat__label">
                        <span><?php esc_html_e( 'Total Users', 'insighthub' ); ?></span>
                        <span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
                    </div>
                    <p class="insighthub-stat__value"><?php echo esc_html( number_format_i18n( $totals['users'] ) ); ?></p>
                </div>
                <div class="insighthub-stat" role="listitem">
                    <div class="insighthub-stat__label">
                        <span><?php esc_html_e( 'Categories', 'insighthub' ); ?></span>
                        <span class="dashicons dashicons-category" aria-hidden="true"></span>
                    </div>
                    <p class="insighthub-stat__value"><?php echo esc_html( number_format_i18n( $totals['categories'] ) ); ?></p>
                </div>
                <div class="insighthub-stat" role="listitem">
                    <div class="insighthub-stat__label">
                        <span><?php esc_html_e( 'Tags', 'insighthub' ); ?></span>
                        <span class="dashicons dashicons-tag" aria-hidden="true"></span>
                    </div>
                    <p class="insighthub-stat__value"><?php echo esc_html( number_format_i18n( $totals['tags'] ) ); ?></p>
                </div>
            </div>

            <div class="insighthub-grid-2">
                <section class="insighthub-panel" aria-labelledby="insighthub-recent-activity">
                    <div class="insighthub-panel__header">
                        <h2 id="insighthub-recent-activity"><?php esc_html_e( 'Recent Activity', 'insighthub' ); ?></h2>
                        <span class="insighthub-chip"><?php esc_html_e( 'Rolling 30 days', 'insighthub' ); ?></span>
                    </div>
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
                </section>

                <section class="insighthub-panel" aria-labelledby="insighthub-chart-trends">
                    <div class="insighthub-panel__header">
                        <h2 id="insighthub-chart-trends"><?php esc_html_e( 'Engagement Overview', 'insighthub' ); ?></h2>
                        <span class="insighthub-chip"><?php esc_html_e( 'Charts placeholder', 'insighthub' ); ?></span>
                    </div>
                    <div class="insighthub-chart-placeholder" role="img" aria-label="<?php esc_attr_e( 'Chart placeholder for engagement trends', 'insighthub' ); ?>">
                        <?php esc_html_e( 'Charts will appear here when connected tools share data.', 'insighthub' ); ?>
                    </div>
                </section>
            </div>

            <section class="insighthub-panel" aria-labelledby="insighthub-post-types">
                <div class="insighthub-panel__header">
                    <h2 id="insighthub-post-types"><?php esc_html_e( 'Post Type Totals', 'insighthub' ); ?></h2>
                    <span class="insighthub-chip"><?php esc_html_e( 'Published items', 'insighthub' ); ?></span>
                </div>
                <table class="widefat insighthub-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php esc_html_e( 'Post Type', 'insighthub' ); ?></th>
                            <th scope="col"><?php esc_html_e( 'Published Count', 'insighthub' ); ?></th>
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
            </section>

            <?php if ( ! empty( $woocommerce_totals ) ) : ?>
                <section class="insighthub-panel" aria-labelledby="insighthub-woocommerce">
                    <div class="insighthub-panel__header">
                        <h2 id="insighthub-woocommerce"><?php esc_html_e( 'WooCommerce (last 30 days)', 'insighthub' ); ?></h2>
                        <span class="insighthub-chip"><?php esc_html_e( 'Storefront signals', 'insighthub' ); ?></span>
                    </div>
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
                </section>
            <?php endif; ?>

            <section class="insighthub-panel insighthub-integrations" aria-labelledby="insighthub-integrations-heading">
                <div class="insighthub-panel__header">
                    <h2 id="insighthub-integrations-heading"><?php esc_html_e( 'Marketing Integrations', 'insighthub' ); ?></h2>
                    <span class="insighthub-chip"><?php esc_html_e( 'Connection center', 'insighthub' ); ?></span>
                </div>
                <p class="insighthub-tools__intro"><?php esc_html_e( 'Connect your marketing tools to pull key insights into InsightHub. Focus styles and contrast help keep actions accessible.', 'insighthub' ); ?></p>
                <?php foreach ( $tools as $slug => $tool ) :
                    $connection       = $this->integration_manager->get_connection( $slug );
                    $metadata         = $this->integration_manager->get_connection_metadata( $slug );
                    $validation       = $this->integration_manager->get_validation_state( $slug );
                    $connected        = $this->integration_manager->is_connected( $slug );
                    $needs_attention  = ( 'failed' === $validation['status'] );
                    $summary          = $this->integration_manager->get_cached_summary( $slug );
                    $cached_at        = isset( $summary['cached_at'] ) ? (int) $summary['cached_at'] : null;
                    $data             = isset( $summary['data'] ) ? $summary['data'] : [];
                    ?>
                    <div class="insighthub-tool">
                        <div class="insighthub-tool__row">
                            <div>
                                <p class="insighthub-tool__title"><?php echo esc_html( $tool['label'] ); ?></p>
                                <p class="insighthub-tool__description"><?php echo esc_html( $tool['description'] ); ?></p>
                            </div>
                            <div>
                                <?php if ( $connected ) : ?>
                                    <span class="insighthub-tool__status status-connected"><?php esc_html_e( 'Connected', 'insighthub' ); ?></span>
                                <?php elseif ( $needs_attention ) : ?>
                                    <span class="insighthub-tool__status status-disconnected"><?php esc_html_e( 'Needs attention', 'insighthub' ); ?></span>
                                <?php else : ?>
                                    <span class="insighthub-tool__status status-disconnected"><?php esc_html_e( 'Not connected', 'insighthub' ); ?></span>
                                <?php endif; ?>
                                <?php if ( $needs_attention ) : ?>
                                    <span class="insighthub-badge insighthub-badge--warning"><?php esc_html_e( 'Needs attention', 'insighthub' ); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="insighthub-tool__data">
                                <?php
                                $client_state = $connection ? $this->integration_manager->get_client( $slug ) : null;
                                if ( is_wp_error( $client_state ) ) :
                                    ?>
                                    <div class="notice notice-error inline">
                                        <p><strong><?php esc_html_e( 'Connection issue:', 'insighthub' ); ?></strong> <?php echo esc_html( $client_state->get_error_message() ); ?></p>
                                        <p class="description"><?php esc_html_e( 'Try reconnecting the integration or checking the saved credentials.', 'insighthub' ); ?></p>
                                    </div>
                                <?php endif; ?>

                                <?php if ( $connection && ! empty( $metadata ) ) : ?>
                                    <ul>
                                        <?php foreach ( $metadata as $key => $value ) : ?>
                                            <li><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?>:</strong> <?php echo esc_html( $value ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>

                                <?php if ( ! empty( $data ) ) : ?>
                                    <ul>
                                        <?php foreach ( $data as $key => $value ) : ?>
                                            <li><strong><?php echo esc_html( ucwords( str_replace( '_', ' ', $key ) ) ); ?>:</strong> <?php echo esc_html( is_array( $value ) ? wp_json_encode( $value ) : $value ); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php elseif ( $sync_running && $connected ) : ?>
                                    <span class="description">
                                        <span class="dashicons dashicons-update spin" aria-hidden="true"></span>
                                        <?php esc_html_e( 'Updating… pull in progress.', 'insighthub' ); ?>
                                    </span>
                                <?php elseif ( $needs_attention ) : ?>
                                    <span class="description"><?php esc_html_e( 'Validation failed. Please review credentials and reconnect.', 'insighthub' ); ?></span>
                                <?php elseif ( ! $connected ) : ?>
                                    <span class="description"><?php esc_html_e( 'Connect to start syncing data.', 'insighthub' ); ?></span>
                                <?php else : ?>
                                    <span class="description"><?php esc_html_e( 'No cached data yet. Try running a refresh.', 'insighthub' ); ?></span>
                                <?php endif; ?>

                                <div class="insighthub-validation-meta">
                                    <?php if ( ! empty( $validation['last_success_at'] ) ) : ?>
                                        <p class="description"><?php printf( esc_html__( 'Last successful sync: %s', 'insighthub' ), esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $validation['last_success_at'] ) ) ); ?></p>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $validation['checked_at'] ) ) : ?>
                                        <p class="description"><?php printf( esc_html__( 'Last checked: %s', 'insighthub' ), esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $validation['checked_at'] ) ) ); ?></p>
                                    <?php else : ?>
                                        <p class="description"><?php esc_html_e( 'Validation has not run yet.', 'insighthub' ); ?></p>
                                    <?php endif; ?>
                                    <?php if ( $cached_at ) : ?>
                                        <p class="description"><?php printf( esc_html__( 'Cached at: %s', 'insighthub' ), esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $cached_at ) ) ); ?></p>
                                    <?php endif; ?>
                                    <?php if ( ! empty( $validation['message'] ) ) : ?>
                                        <p class="description"><?php echo esc_html( $validation['message'] ); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="insighthub-actions">
                                <?php if ( $connected ) : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                        <input type="hidden" name="action" value="insighthub_disconnect_tool" />
                                        <input type="hidden" name="tool" value="<?php echo esc_attr( $slug ); ?>" />
                                        <?php wp_nonce_field( 'insighthub_disconnect_' . $slug ); ?>
                                        <button class="button button-secondary" type="submit"><?php esc_html_e( 'Disconnect', 'insighthub' ); ?></button>
                                    </form>
                                <?php else : ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="insighthub-connect-form">
                                        <input type="hidden" name="action" value="insighthub_connect_tool" />
                                        <input type="hidden" name="tool" value="<?php echo esc_attr( $slug ); ?>" />
                                        <?php wp_nonce_field( 'insighthub_connect_' . $slug ); ?>

                                        <?php if ( 'activecampaign' === $slug ) : ?>
                                            <label class="screen-reader-text" for="insighthub-api-url-<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'ActiveCampaign API URL', 'insighthub' ); ?></label>
                                            <input id="insighthub-api-url-<?php echo esc_attr( $slug ); ?>" name="api_url" type="url" class="regular-text" placeholder="https://youraccount.api-us1.com" required />
                                            <label class="screen-reader-text" for="insighthub-api-key-<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'ActiveCampaign API Key', 'insighthub' ); ?></label>
                                            <input id="insighthub-api-key-<?php echo esc_attr( $slug ); ?>" name="api_key" type="password" class="regular-text" placeholder="API key" required />
                                        <?php elseif ( 'clarity' === $slug ) : ?>
                                            <label class="screen-reader-text" for="insighthub-project-id-<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Clarity Project ID', 'insighthub' ); ?></label>
                                            <input id="insighthub-project-id-<?php echo esc_attr( $slug ); ?>" name="project_id" type="text" class="regular-text" placeholder="Project ID" required />
                                            <label class="screen-reader-text" for="insighthub-project-key-<?php echo esc_attr( $slug ); ?>"><?php esc_html_e( 'Clarity Project Key', 'insighthub' ); ?></label>
                                            <input id="insighthub-project-key-<?php echo esc_attr( $slug ); ?>" name="project_key" type="text" class="regular-text" placeholder="Project key" required />
                                        <?php endif; ?>

                                        <button class="button button-primary" type="submit"><?php echo 'google_site_kit' === $slug ? esc_html__( 'Connect with Google', 'insighthub' ) : esc_html__( 'Validate & Connect', 'insighthub' ); ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </section>
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
        $payload = [];

        if ( 'activecampaign' === $tool ) {
            $payload['api_url'] = isset( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ) ) : '';
            $payload['api_key'] = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
        } elseif ( 'clarity' === $tool ) {
            $payload['project_id']  = isset( $_POST['project_id'] ) ? sanitize_text_field( wp_unslash( $_POST['project_id'] ) ) : '';
            $payload['project_key'] = isset( $_POST['project_key'] ) ? sanitize_text_field( wp_unslash( $_POST['project_key'] ) ) : '';
        }

        $result = $this->integration_manager->connect_tool( $tool, $payload );

        if ( is_array( $result ) && isset( $result['redirect'] ) ) {
            wp_safe_redirect( $result['redirect'] );
            exit;
        }

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
     * Handle OAuth callback for Google Site Kit.
     */
    public function handle_oauth_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'insighthub' ) );
        }

        $tool  = isset( $_GET['tool'] ) ? sanitize_key( wp_unslash( $_GET['tool'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        $result = $this->integration_manager->handle_oauth_callback( $tool, $code, $state );
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
            'refresh_queued'   => __( 'Refresh scheduled. Latest summaries will appear shortly.', 'insighthub' ),
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
