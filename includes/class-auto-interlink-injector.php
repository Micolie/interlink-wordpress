<?php
/**
 * Link injector class - automatically inserts links into post content
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Interlink_Injector {

    private $settings;
    private $analyzer;
    private $links_added = 0;

    /**
     * Constructor
     */
    public function __construct($settings, $analyzer) {
        $this->settings = $settings;
        $this->analyzer = $analyzer;
    }

    /**
     * Inject links into post content
     */
    public function inject_links($content) {
        // Check if plugin is enabled
        if (!$this->settings->is_enabled()) {
            return $content;
        }

        // Check if we're in the main query and it's a single post
        if (!is_singular() || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        global $post;

        // Check if this post type is enabled
        $enabled_post_types = $this->settings->get('post_types', array('post'));
        if (!in_array($post->post_type, $enabled_post_types)) {
            return $content;
        }

        // Check if this post is excluded
        $exclude_posts = $this->settings->get('exclude_posts', array());
        if (in_array($post->ID, $exclude_posts)) {
            return $content;
        }

        // Check minimum post length
        $min_length = $this->settings->get('min_post_length', 100);
        $content_length = str_word_count(wp_strip_all_tags($content));
        if ($content_length < $min_length) {
            return $content;
        }

        // Reset counter
        $this->links_added = 0;

        // Get relevant posts
        $max_links = $this->settings->get('max_links_per_post', 5);
        $relevant_posts = $this->analyzer->get_relevant_posts($post->ID, $max_links);

        if (empty($relevant_posts)) {
            return $content;
        }

        // Process content and add links
        $modified_content = $this->add_links_to_content($content, $relevant_posts, $post->ID);

        return $modified_content;
    }

    /**
     * Add links to content based on relevant posts
     */
    private function add_links_to_content($content, $relevant_posts, $current_post_id) {
        $max_links = $this->settings->get('max_links_per_post', 5);
        $case_sensitive = $this->settings->get('case_sensitive', false);

        // Split content into paragraphs to work with
        $paragraphs = $this->split_into_paragraphs($content);
        $modified_paragraphs = array();

        foreach ($paragraphs as $paragraph) {
            // Skip if paragraph is too short or is HTML tag
            if (strlen(wp_strip_all_tags($paragraph)) < 20 || $this->is_html_tag($paragraph)) {
                $modified_paragraphs[] = $paragraph;
                continue;
            }

            $modified_paragraph = $paragraph;

            // Try to add links from relevant posts
            foreach ($relevant_posts as $relevant_data) {
                if ($this->links_added >= $max_links) {
                    break 2; // Exit both loops
                }

                $target_post = $relevant_data['post'];
                $keywords = array_keys($relevant_data['keywords']);

                // Try each keyword for this post
                foreach ($keywords as $keyword) {
                    if ($this->links_added >= $max_links) {
                        break 2;
                    }

                    // Check if keyword exists in paragraph and isn't already linked
                    if ($this->keyword_exists_and_not_linked($modified_paragraph, $keyword, $case_sensitive)) {
                        // Create the link
                        $link = $this->create_link($target_post, $keyword);

                        // Replace first occurrence of keyword with link
                        $modified_paragraph = $this->replace_keyword_with_link(
                            $modified_paragraph,
                            $keyword,
                            $link,
                            $case_sensitive
                        );

                        $this->links_added++;
                        break; // Move to next relevant post
                    }
                }
            }

            $modified_paragraphs[] = $modified_paragraph;
        }

        return implode('', $modified_paragraphs);
    }

    /**
     * Split content into paragraphs while preserving HTML
     */
    private function split_into_paragraphs($content) {
        // Split by paragraph tags and double line breaks
        $paragraphs = preg_split('/(<p[^>]*>.*?<\/p>|<div[^>]*>.*?<\/div>|<h[1-6][^>]*>.*?<\/h[1-6]>)/s', $content, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        return $paragraphs;
    }

    /**
     * Check if string is an HTML tag
     */
    private function is_html_tag($string) {
        return preg_match('/^<[^>]+>$/', trim($string));
    }

    /**
     * Check if keyword exists in text and is not already linked
     */
    private function keyword_exists_and_not_linked($text, $keyword, $case_sensitive = false) {
        // Remove existing links to get clean text
        $clean_text = preg_replace('/<a\s+[^>]*>.*?<\/a>/i', '', $text);

        // Remove HTML tags for searching
        $search_text = wp_strip_all_tags($clean_text);

        if ($case_sensitive) {
            return strpos($search_text, $keyword) !== false;
        } else {
            return stripos($search_text, $keyword) !== false;
        }
    }

    /**
     * Create HTML link
     */
    private function create_link($post, $anchor_text) {
        $url = get_permalink($post->ID);
        $title = esc_attr($post->post_title);
        $anchor = esc_html($anchor_text);

        return sprintf(
            '<a href="%s" title="%s" class="auto-interlink">%s</a>',
            esc_url($url),
            $title,
            $anchor
        );
    }

    /**
     * Replace first occurrence of keyword with link
     */
    private function replace_keyword_with_link($content, $keyword, $link, $case_sensitive = false) {
        // Pattern to match keyword outside of HTML tags and existing links
        // This is a simplified approach - matches keyword not inside tags
        $pattern = $case_sensitive
            ? '/\b(' . preg_quote($keyword, '/') . ')\b(?![^<]*<\/a>)(?![^<]*>)/u'
            : '/\b(' . preg_quote($keyword, '/') . ')\b(?![^<]*<\/a>)(?![^<]*>)/iu';

        // Replace only the first occurrence
        $replaced = preg_replace($pattern, $link, $content, 1);

        return $replaced ? $replaced : $content;
    }

    /**
     * Get number of links added
     */
    public function get_links_added_count() {
        return $this->links_added;
    }
}
