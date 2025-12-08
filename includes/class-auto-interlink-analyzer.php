<?php
/**
 * Content analyzer class - finds relevant posts for interlinking
 * Simplified algorithm: prioritizes same-category posts and uses simple word matching
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Interlink_Analyzer {

    private $settings;
    private $cache_expiration = 3600; // 1 hour

    public function __construct($settings) {
        $this->settings = $settings;
    }

    /**
     * Get relevant posts for a given post
     * Prioritizes same-category posts and finds linkable words
     */
    public function get_relevant_posts($post_id, $limit = null) {
        if (!$limit) {
            $limit = $this->settings->get('max_links_per_post', 5);
        }

        // Check cache first
        $cache_key = 'auto_interlink_relevant_' . $post_id;
        $cached = get_transient($cache_key);

        if (false !== $cached) {
            return array_slice($cached, 0, $limit);
        }

        $current_post = get_post($post_id);
        if (!$current_post) {
            return array();
        }

        // Get source content (lowercase for matching)
        $source_content = strtolower(wp_strip_all_tags($current_post->post_content));

        // Get current post's categories and tags
        $current_categories = wp_get_post_categories($post_id);
        $current_tags = wp_get_post_tags($post_id, array('fields' => 'ids'));

        // Get all potential target posts
        $potential_posts = $this->get_potential_target_posts($post_id);

        $scored_posts = array();

        foreach ($potential_posts as $target_post) {
            // Find linkable words from target title that exist in source content
            $linkable_words = $this->find_linkable_words($target_post->post_title, $source_content);

            if (empty($linkable_words)) {
                continue;
            }

            // Calculate score - heavily favor same category
            $score = 10; // Base score for having linkable words

            // Same category boost (main priority)
            if ($this->settings->get('same_category_boost', true)) {
                $target_categories = wp_get_post_categories($target_post->ID);
                $common_cats = array_intersect($current_categories, $target_categories);
                $score += count($common_cats) * 100;
            }

            // Same tag boost
            if ($this->settings->get('same_tag_boost', true)) {
                $target_tags = wp_get_post_tags($target_post->ID, array('fields' => 'ids'));
                $common_tags = array_intersect($current_tags, $target_tags);
                $score += count($common_tags) * 50;
            }

            $scored_posts[] = array(
                'post' => $target_post,
                'score' => $score,
                'keywords' => $linkable_words
            );
        }

        // Sort by score (highest first)
        usort($scored_posts, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        // Cache results
        set_transient($cache_key, $scored_posts, $this->cache_expiration);

        return array_slice($scored_posts, 0, $limit);
    }

    /**
     * Find words from title that exist in content
     * Simple and reliable - just looks for individual words
     */
    private function find_linkable_words($title, $content) {
        $title_lower = strtolower(wp_strip_all_tags($title));
        $stop_words = $this->get_stop_words();
        $min_word_length = 4; // Minimum word length to avoid matching common short words

        // Extract words from title
        preg_match_all('/[a-zA-Z]+/u', $title_lower, $matches);
        $words = $matches[0];

        $linkable = array();

        foreach ($words as $word) {
            // Skip stop words and short words
            if (in_array($word, $stop_words) || strlen($word) < $min_word_length) {
                continue;
            }

            // Simple check: does this word exist in content?
            // Use word boundaries to avoid partial matches
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $content)) {
                // Weight longer words higher
                $linkable[$word] = strlen($word) * 10;
            }
        }

        // Sort by weight (longer words first)
        arsort($linkable);

        return $linkable;
    }

    /**
     * Get potential target posts
     */
    private function get_potential_target_posts($current_post_id) {
        $post_types = $this->settings->get('post_types', array('post'));
        $exclude_posts = $this->settings->get('exclude_posts', array());
        $exclude_posts[] = $current_post_id;

        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__not_in' => $exclude_posts,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $query = new WP_Query($args);
        return $query->posts;
    }

    /**
     * Get stop words to exclude
     */
    private function get_stop_words() {
        return array(
            'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'as', 'at',
            'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by',
            'can', 'did', 'do', 'does', 'doing', 'down', 'during',
            'each', 'few', 'for', 'from', 'further',
            'had', 'has', 'have', 'having', 'he', 'her', 'here', 'hers', 'herself', 'him', 'himself', 'his', 'how',
            'i', 'if', 'in', 'into', 'is', 'it', 'its', 'itself',
            'just', 'know',
            'me', 'might', 'more', 'most', 'must', 'my', 'myself',
            'no', 'nor', 'not', 'now',
            'of', 'off', 'on', 'once', 'only', 'or', 'other', 'our', 'ours', 'ourselves', 'out', 'over', 'own',
            'same', 'she', 'should', 'so', 'some', 'such',
            'than', 'that', 'the', 'their', 'theirs', 'them', 'themselves', 'then', 'there', 'these', 'they', 'this', 'those', 'through', 'to', 'too',
            'under', 'until', 'up',
            'very',
            'was', 'we', 'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom', 'why', 'will', 'with', 'would',
            'you', 'your', 'yours', 'yourself', 'yourselves',
            'also', 'back', 'come', 'could', 'even', 'first', 'get', 'give', 'go', 'good', 'great',
            'just', 'like', 'look', 'make', 'many', 'may', 'much', 'need', 'new', 'one', 'only',
            'other', 'over', 'say', 'see', 'take', 'think', 'time', 'two', 'use', 'want', 'way', 'well', 'work', 'year'
        );
    }

    /**
     * Clear cache for a specific post
     */
    public function clear_cache_for_post($post_id) {
        delete_transient('auto_interlink_relevant_' . $post_id);
    }

    /**
     * Clear all cache
     */
    public function clear_all_cache() {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_auto_interlink_%'");
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_auto_interlink_%'");
    }
}
