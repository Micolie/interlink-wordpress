<?php
/**
 * Settings management class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Interlink_Settings {

    private $option_name = 'auto_interlink_settings';
    private $settings = array();

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_settings();
    }

    /**
     * Load settings from database
     */
    private function load_settings() {
        $this->settings = get_option($this->option_name, array());

        // Merge with defaults
        $defaults = $this->get_defaults();
        $this->settings = wp_parse_args($this->settings, $defaults);
    }

    /**
     * Get default settings
     */
    private function get_defaults() {
        return array(
            'enabled' => true,
            'max_links_per_post' => 5,
            'min_keyword_length' => 3,
            'max_keyword_length' => 50,
            'post_types' => array('post'),
            'link_to_newer_posts' => true,
            'link_to_older_posts' => true,
            'case_sensitive' => false,
            'exclude_posts' => array(),
            'min_post_length' => 100,
            'same_category_boost' => true,
            'same_tag_boost' => true,
        );
    }

    /**
     * Get a setting value
     */
    public function get($key, $default = null) {
        if (isset($this->settings[$key])) {
            return $this->settings[$key];
        }
        return $default;
    }

    /**
     * Get all settings
     */
    public function get_all() {
        return $this->settings;
    }

    /**
     * Update a setting
     */
    public function set($key, $value) {
        $this->settings[$key] = $value;
    }

    /**
     * Save settings to database
     */
    public function save() {
        return update_option($this->option_name, $this->settings);
    }

    /**
     * Reset settings to defaults
     */
    public function reset() {
        $this->settings = $this->get_defaults();
        return $this->save();
    }

    /**
     * Check if plugin is enabled
     */
    public function is_enabled() {
        return (bool) $this->get('enabled', true);
    }
}
