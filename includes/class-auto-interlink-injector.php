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
    private $last_error = '';
    private $processing_details = array();
    private $debug_log = array();

    public function __construct($settings, $analyzer) {
        $this->settings = $settings;
        $this->analyzer = $analyzer;
    }

    private function log_debug($message) {
        $this->debug_log[] = $message;
    }

    public function get_debug_log() {
        return $this->debug_log;
    }

    /**
     * Process a post and add interlinks directly to database
     */
    public function process_post($post_id) {
        $this->last_error = '';
        $this->processing_details = array();
        $this->debug_log = array();

        $this->log_debug("=== Starting process_post for ID: $post_id ===");

        // Check if enabled
        $is_enabled = $this->settings->is_enabled();
        $this->log_debug("Plugin enabled: " . ($is_enabled ? 'YES' : 'NO'));
        if (!$is_enabled) {
            $this->last_error = 'Plugin is disabled in settings';
            return false;
        }

        $post = get_post($post_id);
        $this->log_debug("Post found: " . ($post ? 'YES - "' . $post->post_title . '"' : 'NO'));
        if (!$post) {
            $this->last_error = 'Post not found (ID: ' . $post_id . ')';
            return false;
        }

        // Check if this post type is enabled
        $enabled_post_types = $this->settings->get('post_types', array('post'));
        $this->log_debug("Post type: " . $post->post_type);
        $this->log_debug("Enabled post types: " . implode(', ', $enabled_post_types));
        if (!in_array($post->post_type, $enabled_post_types)) {
            $this->last_error = 'Post type "' . $post->post_type . '" is not enabled in settings';
            return false;
        }

        // Check if this post is excluded
        $exclude_posts = $this->settings->get('exclude_posts', array());
        $this->log_debug("Excluded post IDs: " . (empty($exclude_posts) ? 'none' : implode(', ', $exclude_posts)));
        if (in_array($post->ID, $exclude_posts)) {
            $this->last_error = 'Post is in the exclusion list';
            return false;
        }

        // Check minimum post length
        $min_length = $this->settings->get('min_post_length', 100);
        $content = $post->post_content;
        $content_length = str_word_count(wp_strip_all_tags($content));
        $this->log_debug("Content length: $content_length words (minimum: $min_length)");
        if ($content_length < $min_length) {
            $this->last_error = 'Post too short (' . $content_length . ' words, minimum: ' . $min_length . ')';
            return false;
        }

        // Check if content already has auto-interlinks (to avoid re-processing)
        $has_links = strpos($content, 'class="auto-interlink"') !== false;
        $this->log_debug("Already has auto-interlinks: " . ($has_links ? 'YES' : 'NO'));
        if ($has_links) {
            $this->last_error = 'Post already has auto-interlinks';
            return false;
        }

        $this->links_added = 0;

        // Get relevant posts
        $max_links = $this->settings->get('max_links_per_post', 5);
        $this->log_debug("Max links per post: $max_links");
        $this->log_debug("Calling analyzer->get_relevant_posts()...");

        $relevant_posts = $this->analyzer->get_relevant_posts($post->ID, $max_links);

        $this->log_debug("Relevant posts found: " . count($relevant_posts));

        if (empty($relevant_posts)) {
            $this->last_error = 'No relevant posts found (no matching keywords from other post titles found in this content)';
            return false;
        }

        // Log details about relevant posts
        foreach ($relevant_posts as $i => $rp) {
            $title = isset($rp['post']) ? $rp['post']->post_title : 'unknown';
            $keywords = isset($rp['keywords']) ? array_keys($rp['keywords']) : array();
            $this->log_debug("  Relevant post #$i: \"$title\" - Keywords: " . implode(', ', $keywords));
        }

        $this->processing_details['relevant_posts_found'] = count($relevant_posts);

        // Process content and add links
        $this->log_debug("Calling add_links_to_content()...");
        $modified_content = $this->add_links_to_content($content, $relevant_posts);

        $this->log_debug("Links added: " . $this->links_added);
        $this->log_debug("Content modified: " . ($modified_content !== $content ? 'YES' : 'NO'));

        // Only update if content was modified
        if ($modified_content !== $content && $this->links_added > 0) {
            $this->log_debug("Updating post in database...");

            // Remove hook with same priority it was registered (priority 20)
            remove_action('save_post', array($this, 'process_post_on_save'), 20);

            $update_result = wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $modified_content
            ));

            $this->log_debug("wp_update_post result: " . ($update_result ? "success (ID: $update_result)" : "FAILED"));

            // Re-add hook with same priority it was registered (priority 20)
            add_action('save_post', array($this, 'process_post_on_save'), 20, 1);

            return $this->links_added;
        }

        // Set error message if relevant posts were found but no links could be added
        if (!empty($relevant_posts) && $this->links_added === 0) {
            $this->last_error = 'Found ' . count($relevant_posts) . ' relevant posts but could not match any keywords in content (keywords may not appear as standalone words)';
        } elseif (empty($this->last_error)) {
            $this->last_error = 'No links were added (content unchanged)';
        }

        $this->log_debug("Final error: " . $this->last_error);

        return false;
    }

    /**
     * Process post on save
     */
    public function process_post_on_save($post_id) {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $this->process_post($post_id);
    }

    /**
     * Add links to content based on relevant posts
     */
    private function add_links_to_content($content, $relevant_posts) {
        $max_links = $this->settings->get('max_links_per_post', 5);
        $modified_content = $content;

        foreach ($relevant_posts as $relevant_data) {
            if ($this->links_added >= $max_links) {
                break;
            }

            $target_post = $relevant_data['post'];
            $keywords = array_keys($relevant_data['keywords']);

            // Sort by length (longest first)
            usort($keywords, function($a, $b) {
                return strlen($b) - strlen($a);
            });

            // Try each keyword
            foreach ($keywords as $keyword) {
                if ($this->links_added >= $max_links) {
                    break 2;
                }

                // Try to add link for this keyword
                $result = $this->add_single_link($modified_content, $keyword, $target_post);

                if ($result !== false) {
                    $modified_content = $result;
                    $this->links_added++;
                    break; // Move to next target post
                }
            }
        }

        return $modified_content;
    }

    /**
     * Add a single link for a keyword
     */
    private function add_single_link($content, $keyword, $target_post) {
        // First, protect existing links
        // IMPORTANT: Use non-word characters in placeholders so \b word boundaries still work
        // Underscores are word characters in regex, so we use # instead
        $placeholders = array();
        $placeholder_index = 0;

        $protected_content = preg_replace_callback(
            '/<a\s[^>]*>.*?<\/a>/is',
            function($matches) use (&$placeholders, &$placeholder_index) {
                $placeholder = '###LINK' . $placeholder_index . '###';
                $placeholders[$placeholder] = $matches[0];
                $placeholder_index++;
                return $placeholder;
            },
            $content
        );

        // Protect all HTML tags
        $protected_content = preg_replace_callback(
            '/<[^>]+>/',
            function($matches) use (&$placeholders, &$placeholder_index) {
                $placeholder = '###TAG' . $placeholder_index . '###';
                $placeholders[$placeholder] = $matches[0];
                $placeholder_index++;
                return $placeholder;
            },
            $protected_content
        );

        // Find the keyword in protected content (case insensitive)
        // This pattern finds the word with its original case
        $pattern = '/\b(' . preg_quote($keyword, '/') . ')\b/iu';

        if (!preg_match($pattern, $protected_content, $matches)) {
            return false;
        }

        // Get the original case version from the content
        $original_word = $matches[1];

        // Create the link with original case
        $url = get_permalink($target_post->ID);
        $link = sprintf(
            '<a href="%s" title="%s" class="auto-interlink">%s</a>',
            esc_url($url),
            esc_attr($target_post->post_title),
            esc_html($original_word)
        );

        // Replace first occurrence only
        $replaced = preg_replace($pattern, $link, $protected_content, 1);

        if ($replaced === null || $replaced === $protected_content) {
            return false;
        }

        // Restore protected content
        foreach ($placeholders as $placeholder => $original) {
            $replaced = str_replace($placeholder, $original, $replaced);
        }

        return $replaced;
    }

    public function get_links_added_count() {
        return $this->links_added;
    }

    public function get_last_error() {
        return $this->last_error;
    }

    public function get_processing_details() {
        return $this->processing_details;
    }
}
