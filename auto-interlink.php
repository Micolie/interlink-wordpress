<?php
/**
 * Plugin Name: Auto Interlink
 * Plugin URI: https://github.com/Micolie/interlink-wordpress
 * Description: Automatically creates natural interlinks between relevant posts using 3-5 word longtail phrases. Directly modifies post content to save hours of manual linking work.
 * Version: 1.1.0
 * Author: Auto Interlink Team
 * Author URI: https://github.com/Micolie
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: auto-interlink
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('AUTO_INTERLINK_VERSION', '1.1.0');
define('AUTO_INTERLINK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('AUTO_INTERLINK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AUTO_INTERLINK_PLUGIN_FILE', __FILE__);

// Include required files
require_once AUTO_INTERLINK_PLUGIN_DIR . 'includes/class-auto-interlink-settings.php';
require_once AUTO_INTERLINK_PLUGIN_DIR . 'includes/class-auto-interlink-analyzer.php';
require_once AUTO_INTERLINK_PLUGIN_DIR . 'includes/class-auto-interlink-injector.php';
require_once AUTO_INTERLINK_PLUGIN_DIR . 'includes/class-auto-interlink-admin.php';

/**
 * Main plugin class
 */
class Auto_Interlink {

    private static $instance = null;
    private $settings;
    private $analyzer;
    private $injector;
    private $admin;

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize plugin
     */
    private function init() {
        // Initialize settings
        $this->settings = new Auto_Interlink_Settings();

        // Initialize analyzer
        $this->analyzer = new Auto_Interlink_Analyzer($this->settings);

        // Initialize injector
        $this->injector = new Auto_Interlink_Injector($this->settings, $this->analyzer);

        // Initialize admin (only in admin area)
        if (is_admin()) {
            $this->admin = new Auto_Interlink_Admin($this->settings);
        }

        // Register hooks
        $this->register_hooks();
    }

    /**
     * Register WordPress hooks
     */
    private function register_hooks() {
        // Activation/deactivation hooks
        register_activation_hook(AUTO_INTERLINK_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(AUTO_INTERLINK_PLUGIN_FILE, array($this, 'deactivate'));

        // Process posts on save (direct database modification)
        add_action('save_post', array($this->injector, 'process_post_on_save'), 20, 1);

        // Clear cache when posts are updated or deleted
        add_action('save_post', array($this->analyzer, 'clear_cache_for_post'), 10, 1);
        add_action('delete_post', array($this->analyzer, 'clear_all_cache'));
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        $defaults = array(
            'enabled' => true,
            'max_links_per_post' => 5,
            'min_keyword_length' => 10,
            'max_keyword_length' => 100,
            'post_types' => array('post'),
            'link_to_newer_posts' => true,
            'link_to_older_posts' => true,
            'case_sensitive' => false,
            'exclude_posts' => array(),
            'min_post_length' => 100,
            'same_category_boost' => true,
            'same_tag_boost' => true,
        );

        // Only set defaults if options don't exist
        if (!get_option('auto_interlink_settings')) {
            add_option('auto_interlink_settings', $defaults);
        }

        // Clear any existing cache
        delete_transient('auto_interlink_post_keywords');
        delete_transient('auto_interlink_post_relevance');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear cache
        delete_transient('auto_interlink_post_keywords');
        delete_transient('auto_interlink_post_relevance');
    }

    /**
     * Get settings instance
     */
    public function get_settings() {
        return $this->settings;
    }

    /**
     * Get analyzer instance
     */
    public function get_analyzer() {
        return $this->analyzer;
    }

    /**
     * Get injector instance
     */
    public function get_injector() {
        return $this->injector;
    }
}

// Initialize the plugin
function auto_interlink_init() {
    return Auto_Interlink::get_instance();
}

// Start the plugin
add_action('plugins_loaded', 'auto_interlink_init');
