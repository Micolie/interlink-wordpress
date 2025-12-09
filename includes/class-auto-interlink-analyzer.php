<?php
/**
 * Content analyzer class - finds relevant posts for interlinking
 * Supports both keyword matching and AI-powered semantic matching
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Interlink_Analyzer {

    private $settings;
    private $cache_expiration = 3600; // 1 hour
    private $openai = null;

    public function __construct($settings) {
        $this->settings = $settings;

        // Initialize OpenAI if enabled
        if ($this->settings->get('enable_ai', false)) {
            $api_key = $this->settings->get('openai_api_key', '');
            if (!empty($api_key)) {
                $this->openai = new Auto_Interlink_OpenAI($api_key);
            }
        }
    }

    /**
     * Check if AI mode is active
     */
    public function is_ai_enabled() {
        return $this->openai !== null && $this->openai->is_configured();
    }

    /**
     * Get relevant posts for a given post
     * Uses AI if enabled, otherwise falls back to keyword matching
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

        // Get all potential target posts
        $potential_posts = $this->get_potential_target_posts($post_id);

        // Use AI or keyword matching based on settings
        if ($this->is_ai_enabled()) {
            $scored_posts = $this->get_relevant_posts_ai($post_id, $current_post, $potential_posts, $limit);
        } else {
            $scored_posts = $this->get_relevant_posts_keywords($post_id, $current_post, $potential_posts);
        }

        // Cache results
        set_transient($cache_key, $scored_posts, $this->cache_expiration);

        return array_slice($scored_posts, 0, $limit);
    }

    /**
     * Get relevant posts using AI (semantic similarity)
     */
    private function get_relevant_posts_ai($post_id, $current_post, $potential_posts, $limit) {
        $source_content = strtolower(wp_strip_all_tags($current_post->post_content));

        // Use OpenAI to find semantically similar posts
        $similar_posts = $this->openai->find_similar_posts($post_id, $potential_posts, $limit * 2);

        $scored_posts = array();

        foreach ($similar_posts as $similar) {
            $target_post = $similar['post'];
            $similarity = $similar['similarity'];

            // Only consider posts with reasonable similarity (> 0.3)
            if ($similarity < 0.3) {
                continue;
            }

            // Get anchor text suggestions from AI
            $ai_anchors = $this->openai->get_anchor_suggestions($post_id, $target_post->ID);

            // Also try keyword-based anchors as fallback
            $keyword_anchors = $this->find_linkable_words($target_post->post_title, $source_content);

            // Merge anchors (AI suggestions first)
            $all_anchors = array_merge($ai_anchors, $keyword_anchors);

            if (empty($all_anchors)) {
                continue;
            }

            // Score based on similarity (convert 0-1 to higher score)
            $score = intval($similarity * 200);

            // Add category/tag boosts
            $current_categories = wp_get_post_categories($post_id);
            $current_tags = wp_get_post_tags($post_id, array('fields' => 'ids'));

            if ($this->settings->get('same_category_boost', true)) {
                $target_categories = wp_get_post_categories($target_post->ID);
                $common_cats = array_intersect($current_categories, $target_categories);
                $score += count($common_cats) * 50;
            }

            if ($this->settings->get('same_tag_boost', true)) {
                $target_tags = wp_get_post_tags($target_post->ID, array('fields' => 'ids'));
                $common_tags = array_intersect($current_tags, $target_tags);
                $score += count($common_tags) * 25;
            }

            $scored_posts[] = array(
                'post' => $target_post,
                'score' => $score,
                'keywords' => $all_anchors,
                'ai_similarity' => $similarity,
            );
        }

        // Sort by score (highest first)
        usort($scored_posts, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $scored_posts;
    }

    /**
     * Get relevant posts using keyword matching (original algorithm)
     */
    private function get_relevant_posts_keywords($post_id, $current_post, $potential_posts) {
        $source_content = strtolower(wp_strip_all_tags($current_post->post_content));
        $current_categories = wp_get_post_categories($post_id);
        $current_tags = wp_get_post_tags($post_id, array('fields' => 'ids'));

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

        return $scored_posts;
    }

    /**
     * Find phrases and words from title that exist in content
     * Includes both multi-word phrases (preferred for SEO) and single words
     */
    private function find_linkable_words($title, $content) {
        $title_lower = strtolower(wp_strip_all_tags($title));
        $stop_words = $this->get_stop_words();
        $max_phrase_words = $this->settings->get('max_phrase_words', 7);

        // Extract all words from title (including unicode letters and numbers)
        preg_match_all('/[\p{L}\p{N}]+/u', $title_lower, $matches);
        $all_words = $matches[0];

        // Filter out stop words to get meaningful words
        $filtered_words = array();
        foreach ($all_words as $word) {
            if (!in_array($word, $stop_words) && strlen($word) >= 3) {
                $filtered_words[] = $word;
            }
        }

        // Need at least 1 word
        if (count($filtered_words) < 1) {
            return array();
        }

        $linkable = array();

        // Try multi-word phrases (2+ words) - preferred for SEO
        if (count($filtered_words) >= 2) {
            for ($length = min($max_phrase_words, count($filtered_words)); $length >= 2; $length--) {
                for ($i = 0; $i <= count($filtered_words) - $length; $i++) {
                    $phrase = implode(' ', array_slice($filtered_words, $i, $length));

                    // Check if phrase exists in content
                    $pattern = '/\b' . preg_quote($phrase, '/') . '\b/i';
                    if (preg_match($pattern, $content)) {
                        // Weight by number of words (longer = better for SEO)
                        $linkable[$phrase] = $length * 20;
                    }
                }
            }
        }

        // ALWAYS also try single words (not just as fallback) - they're more likely to match
        foreach ($filtered_words as $word) {
            // Skip if this word is already part of a matched phrase
            $already_in_phrase = false;
            foreach (array_keys($linkable) as $phrase) {
                if (strpos($phrase, $word) !== false && strpos($phrase, ' ') !== false) {
                    $already_in_phrase = true;
                    break;
                }
            }

            if (!$already_in_phrase && strlen($word) >= 4) { // Require 4+ chars for single words
                $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
                if (preg_match($pattern, $content)) {
                    $linkable[$word] = 10; // Lower weight for single words
                }
            }
        }

        // Sort by weight (longer phrases first)
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
