<?php
/**
 * Main plugin bootstrap class.
 *
 * @package InsightHub
 */

namespace InsightHub;

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'class-insighthub-stats-service.php';
require_once plugin_dir_path( __FILE__ ) . 'class-insighthub-integration-manager.php';
require_once plugin_dir_path( __FILE__ ) . 'class-insighthub-admin-page.php';
require_once plugin_dir_path( __FILE__ ) . 'class-insighthub-shortcodes.php';

/**
 * Class Plugin
 *
 * Initializes plugin components and hooks.
 */
class Plugin {
    /**
     * Plugin instance.
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Stats service instance.
     *
     * @var Stats_Service
     */
    private $stats_service;

    /**
     * Integration manager instance.
     *
     * @var Integration_Manager
     */
    private $integration_manager;

    /**
     * Admin page handler.
     *
     * @var Admin_Page
     */
    private $admin_page;

    /**
     * Shortcodes handler.
     *
     * @var Shortcodes
     */
    private $shortcodes;

    /**
     * Main plugin file path.
     *
     * @var string
     */
    private $plugin_file;

    /**
     * Get the singleton instance.
     *
     * @return Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Plugin constructor.
     */
    private function __construct() {
        $this->plugin_file          = dirname( __DIR__ ) . '/insighthub.php';
        $this->stats_service        = new Stats_Service();
        $this->integration_manager  = new Integration_Manager();
        $this->admin_page           = new Admin_Page( $this->stats_service, $this->integration_manager );
        $this->shortcodes           = new Shortcodes( $this->stats_service );

        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        $this->integration_manager->register_hooks();
    }

    /**
     * Enqueue admin assets for the InsightHub dashboard page.
     *
     * @param string $hook Page hook suffix.
     */
    public function enqueue_admin_assets( $hook ) {
        if ( $this->admin_page && $this->admin_page->is_plugin_page( $hook ) ) {
            wp_enqueue_style(
                'insighthub-admin',
                plugin_dir_url( $this->plugin_file ) . 'assets/css/admin.css',
                [],
                '1.0.0'
            );
        }
    }
}
