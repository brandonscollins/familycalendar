<?php
/**
 * Plugin Name: Strategicli Family Calendar
 * Plugin URI: <https://strategicli.com/family-calendar>
 * Description: A simple, elegant calendar widget for displaying family events from iCal feeds
 * Version: 1.0.0
 * Author: Strategicli
 * Author URI: <https://strategicli.com>
 * License: GPL v2 or later
 * Text Domain: strategicli-family-calendar
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('SFC_VERSION', '1.0.0');
define('SFC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('SFC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('SFC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once SFC_PLUGIN_DIR . 'includes/class-sfc-admin.php';
require_once SFC_PLUGIN_DIR . 'includes/class-sfc-calendar-display.php';
require_once SFC_PLUGIN_DIR . 'includes/class-sfc-ical-parser.php';

// Main plugin class
class SFC_Calendar {
    private static $instance = null;
    private $admin;
    private $display;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    public function init() {
        // Initialize components
        $this->admin = new SFC_Admin();
        $this->display = new SFC_Calendar_Display();

        // Add hooks
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_scripts'));
        add_shortcode('sfc_calendar', array($this->display, 'render_shortcode'));

        // AJAX handlers
        add_action('wp_ajax_sfc_refresh_calendar', array($this->display, 'ajax_refresh_calendar'));
        add_action('wp_ajax_nopriv_sfc_refresh_calendar', array($this->display, 'ajax_refresh_calendar'));
		add_action('wp_ajax_sfc_navigate_month', array($this->display, 'ajax_navigate_month'));
		add_action('wp_ajax_nopriv_sfc_navigate_month', array($this->display, 'ajax_navigate_month'));
    }

    public function load_textdomain() {
        load_plugin_textdomain(
            'strategicli-family-calendar',
            false,
            dirname(SFC_PLUGIN_BASENAME) . '/languages'
        );
    }

	public function maybe_enqueue_scripts() {
		global $post;
		if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'sfc_calendar')) {
			wp_enqueue_style(
				'sfc-frontend',
				SFC_PLUGIN_URL . 'assets/css/sfc-frontend.css',
				array(),
				SFC_VERSION
			);

			wp_enqueue_script(
				'sfc-calendar',
				SFC_PLUGIN_URL . 'assets/js/sfc-calendar.js',
				array(),
				SFC_VERSION,
				true
			);

			// Make sure to localize the script with the correct variable name
			wp_localize_script('sfc-calendar', 'sfc_ajax', array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('sfc_calendar_nonce')
			));
		}
	}

}

// Initialize the plugin
function sfc_init() {
    SFC_Calendar::get_instance();
}

add_action('plugins_loaded', 'sfc_init');