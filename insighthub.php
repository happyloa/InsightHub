<?php
/**
 * Plugin Name: InsightHub – Site Analytics Dashboard
 * Plugin URI: https://example.com/
 * Description: A lightweight analytics dashboard that surfaces key WordPress site stats in the admin area and via a shortcode.
 * Version: 1.0.0
 * Author: InsightHub
 * Author URI: https://example.com/
 * License: GPL-2.0-or-later
 * Text Domain: insighthub
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-insighthub-plugin.php';

/**
 * Initialize the InsightHub plugin.
 */
function insighthub_init_plugin() {
    InsightHub\Plugin::get_instance();
}

add_action( 'plugins_loaded', 'insighthub_init_plugin' );
