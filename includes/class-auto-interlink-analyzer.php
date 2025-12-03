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
     * Get relevant posts for a given post
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

        // Extract keywords from current post
        $keywords = $this->extract_keywords($current_post->post_content, $current_post->post_title);

        // Get all potential target posts
        $potential_posts = $this->get_potential_target_posts($post_id);

        // Score and rank posts by relevance
        $scored_posts = array();
        foreach ($potential_posts as $target_post) {
            $score = $this->calculate_relevance_score($current_post, $target_post, $keywords);
            if ($score > 0) {
                $scored_posts[] = array(
                    'post' => $target_post,
                    'score' => $score,
                    'keywords' => $this->find_matching_keywords($keywords, $target_post)
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
     * Extract keywords from content
     */
    public function extract_keywords($content, $title = '') {
        // Combine title and content
        $text = $title . ' ' . $content;

        // Remove HTML tags
        $text = wp_strip_all_tags($text);

        // Remove shortcodes
        $text = strip_shortcodes($text);

        // Convert to lowercase unless case sensitive
        if (!$this->settings->get('case_sensitive', false)) {
            $text = strtolower($text);
        }

        // Get word boundaries
        $min_length = $this->settings->get('min_keyword_length', 3);
        $max_length = $this->settings->get('max_keyword_length', 50);

        // Extract words
        preg_match_all('/\b[\w\-]+\b/u', $text, $matches);
        $words = $matches[0];

        // Filter by length and remove common stop words
        $stop_words = $this->get_stop_words();
        $keywords = array();

        foreach ($words as $word) {
            $word_length = mb_strlen($word);
            if ($word_length >= $min_length && $word_length <= $max_length && !in_array($word, $stop_words)) {
                if (!isset($keywords[$word])) {
                    $keywords[$word] = 0;
                }
                $keywords[$word]++;
            }
        }

        // Sort by frequency
        arsort($keywords);

        // Also extract 2-3 word phrases from title
        if ($title) {
            $title_clean = wp_strip_all_tags($title);
            if (!$this->settings->get('case_sensitive', false)) {
                $title_clean = strtolower($title_clean);
            }

            // Add title as a high-value keyword
            if (mb_strlen($title_clean) >= $min_length && mb_strlen($title_clean) <= $max_length) {
                $keywords[$title_clean] = (isset($keywords[$title_clean]) ? $keywords[$title_clean] : 0) + 10;
            }

            // Extract 2-word phrases from title
            $title_words = preg_split('/\s+/', $title_clean);
            if (count($title_words) >= 2) {
                for ($i = 0; $i < count($title_words) - 1; $i++) {
                    $phrase = $title_words[$i] . ' ' . $title_words[$i + 1];
                    if (mb_strlen($phrase) >= $min_length && mb_strlen($phrase) <= $max_length) {
                        $keywords[$phrase] = (isset($keywords[$phrase]) ? $keywords[$phrase] : 0) + 5;
                    }
                }
            }
        }

        return $keywords;
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
     * Calculate relevance score between two posts
     */
    private function calculate_relevance_score($source_post, $target_post, $source_keywords) {
        $score = 0;

        // Extract target keywords
        $target_keywords = $this->extract_keywords($target_post->post_content, $target_post->post_title);

        // Calculate keyword overlap
        foreach ($source_keywords as $keyword => $freq) {
            if (isset($target_keywords[$keyword])) {
                $score += min($freq, $target_keywords[$keyword]) * mb_strlen($keyword);
            }
        }

        // Boost score for same category
        if ($this->settings->get('same_category_boost', true)) {
            $source_cats = wp_get_post_categories($source_post->ID);
            $target_cats = wp_get_post_categories($target_post->ID);
            $common_cats = array_intersect($source_cats, $target_cats);
            $score += count($common_cats) * 50;
        }

        // Boost score for same tags
        if ($this->settings->get('same_tag_boost', true)) {
            $source_tags = wp_get_post_tags($source_post->ID, array('fields' => 'ids'));
            $target_tags = wp_get_post_tags($target_post->ID, array('fields' => 'ids'));
            $common_tags = array_intersect($source_tags, $target_tags);
            $score += count($common_tags) * 30;
        }

        return $score;
    }

    /**
     * Find matching keywords between source and target
     */
    private function find_matching_keywords($source_keywords, $target_post) {
        $target_keywords = $this->extract_keywords($target_post->post_content, $target_post->post_title);
        $matching = array();

        foreach ($source_keywords as $keyword => $freq) {
            if (isset($target_keywords[$keyword])) {
                $matching[$keyword] = $freq;
            }
        }

        // Sort by frequency and length (prefer longer, more specific phrases)
        uasort($matching, function($a, $b) use ($source_keywords) {
            $a_key = array_search($a, $source_keywords);
            $b_key = array_search($b, $source_keywords);

            $a_score = $a * mb_strlen($a_key);
            $b_score = $b * mb_strlen($b_key);

            return $b_score - $a_score;
        });

        return $matching;
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
