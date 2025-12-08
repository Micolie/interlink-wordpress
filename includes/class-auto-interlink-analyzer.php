<?php
/**
 * Content analyzer class - finds relevant posts for interlinking
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Interlink_Analyzer {

    private $settings;
    private $cache_expiration = 3600; // 1 hour

    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
    }

    /**
     * Get relevant posts for a given post (prioritizes same category)
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

        // Get the current post
        $current_post = get_post($post_id);
        if (!$current_post) {
            return array();
        }

        // Get current post's categories
        $current_categories = wp_get_post_categories($post_id);
        $current_tags = wp_get_post_tags($post_id, array('fields' => 'ids'));

        // Get source content for phrase matching
        $source_content = strtolower(wp_strip_all_tags($current_post->post_content));

        // Get all potential target posts
        $potential_posts = $this->get_potential_target_posts($post_id);

        // Score and rank posts by relevance
        $scored_posts = array();
        foreach ($potential_posts as $target_post) {
            // Get anchor phrases from target post title
            $anchor_phrases = $this->get_anchor_phrases_from_title($target_post->post_title);

            // Find which phrases exist in source content
            $matching_phrases = $this->find_phrases_in_content($anchor_phrases, $source_content);

            if (empty($matching_phrases)) {
                continue; // Skip if no matching phrases found
            }

            // Calculate score (heavily weight same category)
            $score = 0;

            // Same category boost (primary factor)
            if ($this->settings->get('same_category_boost', true)) {
                $target_categories = wp_get_post_categories($target_post->ID);
                $common_cats = array_intersect($current_categories, $target_categories);
                $score += count($common_cats) * 100; // Heavy boost for same category
            }

            // Same tag boost
            if ($this->settings->get('same_tag_boost', true)) {
                $target_tags = wp_get_post_tags($target_post->ID, array('fields' => 'ids'));
                $common_tags = array_intersect($current_tags, $target_tags);
                $score += count($common_tags) * 50;
            }

            // Add base score for having matching phrases
            $score += count($matching_phrases) * 10;

            if ($score > 0) {
                $scored_posts[] = array(
                    'post' => $target_post,
                    'score' => $score,
                    'keywords' => $matching_phrases
                );
            }
        }

        // Sort by score (highest first)
        usort($scored_posts, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        // Cache the results
        set_transient($cache_key, $scored_posts, $this->cache_expiration);

        return array_slice($scored_posts, 0, $limit);
    }

    /**
     * Get anchor phrases from a post title (1-7 word combinations)
     */
    private function get_anchor_phrases_from_title($title) {
        $title_clean = strtolower(wp_strip_all_tags($title));
        $stop_words = $this->get_stop_words();
        $max_phrase_words = $this->settings->get('max_phrase_words', 7);
        $min_length = $this->settings->get('min_keyword_length', 3);
        $max_length = $this->settings->get('max_keyword_length', 100);

        // Extract words from title
        preg_match_all('/\b[\w\-]+\b/u', $title_clean, $matches);
        $all_words = $matches[0];

        // Also keep filtered words (without stop words)
        $filtered_words = array();
        foreach ($all_words as $word) {
            if (!in_array($word, $stop_words) && mb_strlen($word) >= 2) {
                $filtered_words[] = $word;
            }
        }

        $phrases = array();

        // Add the full title first (highest priority)
        if (mb_strlen($title_clean) >= $min_length && mb_strlen($title_clean) <= $max_length) {
            $word_count = count($all_words);
            if ($word_count >= 1 && $word_count <= $max_phrase_words) {
                $phrases[$title_clean] = 100;
            }
        }

        // Generate phrase combinations from filtered words (1 to max_phrase_words)
        for ($phrase_length = min($max_phrase_words, count($filtered_words)); $phrase_length >= 1; $phrase_length--) {
            for ($i = 0; $i <= count($filtered_words) - $phrase_length; $i++) {
                $phrase = implode(' ', array_slice($filtered_words, $i, $phrase_length));
                $phrase_char_length = mb_strlen($phrase);

                if ($phrase_char_length >= $min_length && $phrase_char_length <= $max_length) {
                    if (!isset($phrases[$phrase])) {
                        // Weight by phrase length (longer = better)
                        $phrases[$phrase] = $phrase_length * 10;
                    }
                }
            }
        }

        // Sort by weight (highest first)
        arsort($phrases);

        return $phrases;
    }

    /**
     * Find which phrases exist in the source content
     */
    private function find_phrases_in_content($phrases, $content) {
        $matching = array();

        foreach ($phrases as $phrase => $weight) {
            // Use word boundary matching
            $pattern = '/\b' . preg_quote($phrase, '/') . '\b/iu';
            if (preg_match($pattern, $content)) {
                $matching[$phrase] = $weight;
            }
        }

        return $matching;
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
     * Get common stop words to exclude
     */
    private function get_stop_words() {
        return array(
            'a', 'about', 'above', 'after', 'again', 'against', 'all', 'am', 'an', 'and', 'any', 'are', 'as', 'at',
            'be', 'because', 'been', 'before', 'being', 'below', 'between', 'both', 'but', 'by',
            'can', 'did', 'do', 'does', 'doing', 'down', 'during',
            'each', 'few', 'for', 'from', 'further',
            'had', 'has', 'have', 'having', 'he', 'her', 'here', 'hers', 'herself', 'him', 'himself', 'his', 'how',
            'i', 'if', 'in', 'into', 'is', 'it', 'its', 'itself',
            'just',
            'me', 'might', 'more', 'most', 'must', 'my', 'myself',
            'no', 'nor', 'not', 'now',
            'of', 'off', 'on', 'once', 'only', 'or', 'other', 'our', 'ours', 'ourselves', 'out', 'over', 'own',
            'same', 'she', 'should', 'so', 'some', 'such',
            'than', 'that', 'the', 'their', 'theirs', 'them', 'themselves', 'then', 'there', 'these', 'they', 'this', 'those', 'through', 'to', 'too',
            'under', 'until', 'up',
            'very',
            'was', 'we', 'were', 'what', 'when', 'where', 'which', 'while', 'who', 'whom', 'why', 'will', 'with', 'would',
            'you', 'your', 'yours', 'yourself', 'yourselves'
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
