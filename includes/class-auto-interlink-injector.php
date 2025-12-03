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
     * Process a post and add interlinks directly to database
     */
    public function process_post($post_id) {
        // Check if plugin is enabled
        if (!$this->settings->is_enabled()) {
            return false;
        }

        // Get the post
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Check if this post type is enabled
        $enabled_post_types = $this->settings->get('post_types', array('post'));
        if (!in_array($post->post_type, $enabled_post_types)) {
            return false;
        }

        // Check if this post is excluded
        $exclude_posts = $this->settings->get('exclude_posts', array());
        if (in_array($post->ID, $exclude_posts)) {
            return false;
        }

        // Check minimum post length
        $min_length = $this->settings->get('min_post_length', 100);
        $content = $post->post_content;
        $content_length = str_word_count(wp_strip_all_tags($content));
        if ($content_length < $min_length) {
            return false;
        }

        // Check if content already has auto-interlinks (to avoid re-processing)
        if (strpos($content, 'class="auto-interlink"') !== false) {
            return false;
        }

        // Reset counter
        $this->links_added = 0;

        // Get relevant posts
        $max_links = $this->settings->get('max_links_per_post', 5);
        $relevant_posts = $this->analyzer->get_relevant_posts($post->ID, $max_links);

        if (empty($relevant_posts)) {
            return false;
        }

        // Process content and add links
        $modified_content = $this->add_links_to_content($content, $relevant_posts, $post->ID);

        // Only update if content was modified
        if ($modified_content !== $content && $this->links_added > 0) {
            // Unhook to prevent infinite loop
            remove_action('save_post', array($this, 'process_post_on_save'));

            // Update post content directly in database
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $modified_content
            ));

            // Re-hook
            add_action('save_post', array($this, 'process_post_on_save'), 10, 1);

            return $this->links_added;
        }

        return false;
    }

    /**
     * Process post on save
     */
    public function process_post_on_save($post_id) {
        // Check if this is an autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check user permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Process the post
        $this->process_post($post_id);
    }

    /**
     * Add links to content based on relevant posts
     */
    private function add_links_to_content($content, $relevant_posts, $current_post_id) {
        $max_links = $this->settings->get('max_links_per_post', 5);
        $case_sensitive = $this->settings->get('case_sensitive', false);

        // Work directly with the content instead of splitting it
        $modified_content = $content;

        // Try to add links from relevant posts
        foreach ($relevant_posts as $relevant_data) {
            if ($this->links_added >= $max_links) {
                break;
            }

            $target_post = $relevant_data['post'];
            $phrases = array_keys($relevant_data['keywords']);

            // Sort phrases by length (longest first for better matching)
            usort($phrases, function($a, $b) {
                return mb_strlen($b) - mb_strlen($a);
            });

            // Try each phrase for this post
            foreach ($phrases as $phrase) {
                if ($this->links_added >= $max_links) {
                    break 2;
                }

                // Only process phrases with 1-3 words
                $word_count = str_word_count($phrase);
                if ($word_count < 1 || $word_count > 3) {
                    continue;
                }

                // Check if phrase exists in content and isn't already linked
                if ($this->phrase_exists_and_not_linked($modified_content, $phrase, $case_sensitive)) {
                    // Create the link
                    $link = $this->create_link($target_post, $phrase);

                    // Replace first occurrence of phrase with link
                    $modified_content = $this->replace_phrase_with_link(
                        $modified_content,
                        $phrase,
                        $link,
                        $case_sensitive
                    );

                    if ($modified_content !== false) {
                        $this->links_added++;
                        break; // Move to next relevant post
                    }
                }
            }
        }

        return $modified_content;
    }

    /**
     * Check if phrase exists in text and is not already linked
     */
    private function phrase_exists_and_not_linked($text, $phrase, $case_sensitive = false) {
        // Create a temporary version with links removed for searching
        $temp_text = preg_replace('/<a\s+[^>]*>.*?<\/a>/is', '[LINK]', $text);

        // Remove all HTML tags for clean searching
        $search_text = wp_strip_all_tags($temp_text);

        // Check if phrase exists in clean text (not within removed links)
        if ($case_sensitive) {
            $found = strpos($search_text, $phrase) !== false;
        } else {
            $found = stripos($search_text, $phrase) !== false;
        }

        // Make sure it's not within a [LINK] placeholder
        if ($found && strpos($search_text, '[LINK]') !== false) {
            // Double-check the phrase isn't where we removed a link
            $pattern = $case_sensitive
                ? '/\b' . preg_quote($phrase, '/') . '\b/'
                : '/\b' . preg_quote($phrase, '/') . '\b/i';

            // Check in original text structure
            return preg_match($pattern, $search_text) && !preg_match('/\[LINK\]/', $search_text);
        }

        return $found;
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
     * Replace first occurrence of phrase with link
     */
    private function replace_phrase_with_link($content, $phrase, $link, $case_sensitive = false) {
        // Use a more sophisticated approach to avoid replacing text inside HTML tags or existing links

        // First, protect existing links and HTML tags
        $protected_content = $content;
        $placeholders = array();
        $placeholder_index = 0;

        // Protect existing links
        $protected_content = preg_replace_callback(
            '/<a\s+[^>]*>.*?<\/a>/is',
            function($matches) use (&$placeholders, &$placeholder_index) {
                $placeholder = '___PROTECTED_LINK_' . $placeholder_index . '___';
                $placeholders[$placeholder] = $matches[0];
                $placeholder_index++;
                return $placeholder;
            },
            $protected_content
        );

        // Protect HTML tags (but not their content)
        $protected_content = preg_replace_callback(
            '/<[^>]+>/',
            function($matches) use (&$placeholders, &$placeholder_index) {
                $placeholder = '___PROTECTED_TAG_' . $placeholder_index . '___';
                $placeholders[$placeholder] = $matches[0];
                $placeholder_index++;
                return $placeholder;
            },
            $protected_content
        );

        // Now replace the phrase
        $pattern = $case_sensitive
            ? '/\b(' . preg_quote($phrase, '/') . ')\b/u'
            : '/\b(' . preg_quote($phrase, '/') . ')\b/iu';

        // Replace only first occurrence
        $replaced_content = preg_replace($pattern, $link, $protected_content, 1);

        // Restore protected content
        foreach ($placeholders as $placeholder => $original) {
            $replaced_content = str_replace($placeholder, $original, $replaced_content);
        }

        return $replaced_content !== null ? $replaced_content : $content;
    }

    /**
     * Get number of links added
     */
    public function get_links_added_count() {
        return $this->links_added;
    }
}
