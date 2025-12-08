<?php
/**
 * Admin interface class
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Interlink_Admin {

    private $settings;

    /**
     * Constructor
     */
    public function __construct($settings) {
        $this->settings = $settings;
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Auto Interlink Settings', 'auto-interlink'),
            __('Auto Interlink', 'auto-interlink'),
            'manage_options',
            'auto-interlink-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'auto_interlink_settings_group',
            'auto_interlink_settings',
            array($this, 'sanitize_settings')
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['enabled'] = isset($input['enabled']) ? (bool) $input['enabled'] : false;
        $sanitized['max_links_per_post'] = absint($input['max_links_per_post'] ?? 5);
        $sanitized['min_keyword_length'] = absint($input['min_keyword_length'] ?? 3);
        $sanitized['max_keyword_length'] = absint($input['max_keyword_length'] ?? 50);
        $sanitized['min_post_length'] = absint($input['min_post_length'] ?? 100);
        $sanitized['link_to_newer_posts'] = isset($input['link_to_newer_posts']) ? (bool) $input['link_to_newer_posts'] : true;
        $sanitized['link_to_older_posts'] = isset($input['link_to_older_posts']) ? (bool) $input['link_to_older_posts'] : true;
        $sanitized['case_sensitive'] = isset($input['case_sensitive']) ? (bool) $input['case_sensitive'] : false;
        $sanitized['same_category_boost'] = isset($input['same_category_boost']) ? (bool) $input['same_category_boost'] : true;
        $sanitized['same_tag_boost'] = isset($input['same_tag_boost']) ? (bool) $input['same_tag_boost'] : true;

        // Post types
        $sanitized['post_types'] = array();
        if (isset($input['post_types']) && is_array($input['post_types'])) {
            foreach ($input['post_types'] as $post_type) {
                $sanitized['post_types'][] = sanitize_text_field($post_type);
            }
        }

        // Excluded posts
        $sanitized['exclude_posts'] = array();
        if (isset($input['exclude_posts']) && !empty($input['exclude_posts'])) {
            $exclude_ids = explode(',', $input['exclude_posts']);
            foreach ($exclude_ids as $id) {
                $id = absint(trim($id));
                if ($id > 0) {
                    $sanitized['exclude_posts'][] = $id;
                }
            }
        }

        // AI Settings
        $sanitized['enable_ai'] = isset($input['enable_ai']) ? (bool) $input['enable_ai'] : false;
        $sanitized['openai_api_key'] = isset($input['openai_api_key']) ? sanitize_text_field($input['openai_api_key']) : '';

        return $sanitized;
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ('settings_page_auto-interlink-settings' !== $hook) {
            return;
        }

        wp_enqueue_style('auto-interlink-admin', AUTO_INTERLINK_PLUGIN_URL . 'assets/admin.css', array(), AUTO_INTERLINK_VERSION);
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle cache clearing
        if (isset($_POST['clear_cache']) && check_admin_referer('auto_interlink_clear_cache')) {
            $analyzer = new Auto_Interlink_Analyzer($this->settings);
            $analyzer->clear_all_cache();
            echo '<div class="notice notice-success"><p>' . __('Cache cleared successfully!', 'auto-interlink') . '</p></div>';
        }

        // Handle bulk processing
        if (isset($_POST['process_all_posts']) && check_admin_referer('auto_interlink_process_all')) {
            $this->process_all_posts();
        }

        // Handle processing selected posts
        if (isset($_POST['process_selected_posts']) && check_admin_referer('auto_interlink_process_selected')) {
            $this->process_selected_posts();
        }

        $settings = $this->settings->get_all();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
                <?php
                settings_fields('auto_interlink_settings_group');
                ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enabled"><?php _e('Enable Auto Interlinking', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_interlink_settings[enabled]" id="enabled" value="1" <?php checked($settings['enabled'], true); ?>>
                                    <?php _e('Automatically add interlinks to posts', 'auto-interlink'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="max_links_per_post"><?php _e('Maximum Links Per Post', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="auto_interlink_settings[max_links_per_post]" id="max_links_per_post" value="<?php echo esc_attr($settings['max_links_per_post']); ?>" min="1" max="20" class="small-text">
                                <p class="description"><?php _e('Maximum number of automatic links to add to each post.', 'auto-interlink'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="min_keyword_length"><?php _e('Minimum Keyword Length', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="auto_interlink_settings[min_keyword_length]" id="min_keyword_length" value="<?php echo esc_attr($settings['min_keyword_length']); ?>" min="2" max="20" class="small-text">
                                <p class="description"><?php _e('Minimum character length for keywords to be considered.', 'auto-interlink'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="max_keyword_length"><?php _e('Maximum Keyword Length', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="auto_interlink_settings[max_keyword_length]" id="max_keyword_length" value="<?php echo esc_attr($settings['max_keyword_length']); ?>" min="10" max="200" class="small-text">
                                <p class="description"><?php _e('Maximum character length for keywords (prevents matching entire sentences).', 'auto-interlink'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="min_post_length"><?php _e('Minimum Post Length', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="auto_interlink_settings[min_post_length]" id="min_post_length" value="<?php echo esc_attr($settings['min_post_length']); ?>" min="0" step="50" class="small-text">
                                <p class="description"><?php _e('Minimum word count for posts to receive automatic links.', 'auto-interlink'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label><?php _e('Post Types', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <?php
                                $post_types = get_post_types(array('public' => true), 'objects');
                                foreach ($post_types as $post_type) {
                                    if ($post_type->name === 'attachment') {
                                        continue;
                                    }
                                    $checked = in_array($post_type->name, $settings['post_types']) ? 'checked' : '';
                                    ?>
                                    <label style="display: block; margin-bottom: 5px;">
                                        <input type="checkbox" name="auto_interlink_settings[post_types][]" value="<?php echo esc_attr($post_type->name); ?>" <?php echo $checked; ?>>
                                        <?php echo esc_html($post_type->labels->name); ?>
                                    </label>
                                    <?php
                                }
                                ?>
                                <p class="description"><?php _e('Select which post types should have automatic interlinking.', 'auto-interlink'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label><?php _e('Linking Options', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="auto_interlink_settings[link_to_newer_posts]" value="1" <?php checked($settings['link_to_newer_posts'], true); ?>>
                                    <?php _e('Link to newer posts', 'auto-interlink'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="auto_interlink_settings[link_to_older_posts]" value="1" <?php checked($settings['link_to_older_posts'], true); ?>>
                                    <?php _e('Link to older posts', 'auto-interlink'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="auto_interlink_settings[case_sensitive]" value="1" <?php checked($settings['case_sensitive'], true); ?>>
                                    <?php _e('Case sensitive keyword matching', 'auto-interlink'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label><?php _e('Relevance Boosting', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="auto_interlink_settings[same_category_boost]" value="1" <?php checked($settings['same_category_boost'], true); ?>>
                                    <?php _e('Boost relevance for posts in same category', 'auto-interlink'); ?>
                                </label>
                                <label style="display: block; margin-bottom: 5px;">
                                    <input type="checkbox" name="auto_interlink_settings[same_tag_boost]" value="1" <?php checked($settings['same_tag_boost'], true); ?>>
                                    <?php _e('Boost relevance for posts with same tags', 'auto-interlink'); ?>
                                </label>
                                <p class="description"><?php _e('These options help prioritize linking to posts in related categories or with similar tags.', 'auto-interlink'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="exclude_posts"><?php _e('Exclude Posts', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="auto_interlink_settings[exclude_posts]" id="exclude_posts" value="<?php echo esc_attr(implode(', ', $settings['exclude_posts'])); ?>" class="regular-text">
                                <p class="description"><?php _e('Enter post IDs to exclude from interlinking (comma-separated, e.g., 1, 5, 23).', 'auto-interlink'); ?></p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <h2 style="margin-top: 30px;"><?php _e('AI-Powered Linking (Optional)', 'auto-interlink'); ?></h2>
                <p><?php _e('Enable AI to find semantically related posts even when they don\'t share exact keywords. This uses OpenAI to understand the meaning and context of your content.', 'auto-interlink'); ?></p>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row">
                                <label for="enable_ai"><?php _e('Enable AI Matching', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="auto_interlink_settings[enable_ai]" id="enable_ai" value="1" <?php checked($settings['enable_ai'] ?? false, true); ?>>
                                    <?php _e('Use AI for smart internal linking', 'auto-interlink'); ?>
                                </label>
                                <p class="description"><?php _e('When enabled, the plugin uses OpenAI to find related posts based on semantic similarity, not just keyword matching.', 'auto-interlink'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="openai_api_key"><?php _e('OpenAI API Key', 'auto-interlink'); ?></label>
                            </th>
                            <td>
                                <input type="password" name="auto_interlink_settings[openai_api_key]" id="openai_api_key" value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" class="regular-text">
                                <p class="description">
                                    <?php _e('Enter your OpenAI API key. Get one from', 'auto-interlink'); ?>
                                    <a href="https://platform.openai.com/api-keys" target="_blank">platform.openai.com/api-keys</a>
                                </p>
                                <?php if (!empty($settings['openai_api_key'])) : ?>
                                <p style="color: green;">✓ <?php _e('API key is set', 'auto-interlink'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px; margin: 20px 0;">
                    <strong><?php _e('How AI Matching Works:', 'auto-interlink'); ?></strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li><?php _e('Understands that "car" and "automobile" are related', 'auto-interlink'); ?></li>
                        <li><?php _e('Finds topically related posts even without exact word matches', 'auto-interlink'); ?></li>
                        <li><?php _e('Suggests better anchor text based on context', 'auto-interlink'); ?></li>
                        <li><?php _e('Cost: ~$0.0001 per post (very affordable)', 'auto-interlink'); ?></li>
                    </ul>
                </div>

                <?php submit_button(__('Save Settings', 'auto-interlink')); ?>
            </form>

            <hr>

            <h2><?php _e('Cache Management', 'auto-interlink'); ?></h2>
            <p><?php _e('The plugin caches relevance data to improve performance. Clear the cache if you notice outdated links or after making major changes to your posts.', 'auto-interlink'); ?></p>

            <form method="post">
                <?php wp_nonce_field('auto_interlink_clear_cache'); ?>
                <input type="hidden" name="clear_cache" value="1">
                <?php submit_button(__('Clear Cache', 'auto-interlink'), 'secondary', 'submit', false); ?>
            </form>

            <hr>

            <h2><?php _e('Bulk Processing', 'auto-interlink'); ?></h2>
            <p><?php _e('Process all existing posts to add interlinks. This will scan all published posts and add relevant interlinks using 1-3 word phrases.', 'auto-interlink'); ?></p>
            <p><strong><?php _e('Warning:', 'auto-interlink'); ?></strong> <?php _e('This will directly modify your post content in the database. Make sure to backup your database before running this operation.', 'auto-interlink'); ?></p>

            <form method="post">
                <?php wp_nonce_field('auto_interlink_process_all'); ?>
                <input type="hidden" name="process_all_posts" value="1">
                <?php submit_button(__('Process All Posts', 'auto-interlink'), 'primary', 'submit', false); ?>
            </form>

            <hr>

            <h2><?php _e('Posts Without Internal Links', 'auto-interlink'); ?></h2>
            <p><?php _e('Select specific posts to process. Only posts without existing auto-interlinks are shown.', 'auto-interlink'); ?></p>

            <?php
            $posts_without_links = $this->get_posts_without_links();
            if (!empty($posts_without_links)) :
            ?>
            <form method="post">
                <?php wp_nonce_field('auto_interlink_process_selected'); ?>
                <table class="wp-list-table widefat fixed striped" style="max-width: 800px;">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="select-all-posts"></th>
                            <th style="width: 60px;"><?php _e('ID', 'auto-interlink'); ?></th>
                            <th><?php _e('Title', 'auto-interlink'); ?></th>
                            <th style="width: 100px;"><?php _e('Type', 'auto-interlink'); ?></th>
                            <th style="width: 120px;"><?php _e('Date', 'auto-interlink'); ?></th>
                            <th style="width: 80px;"><?php _e('Words', 'auto-interlink'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts_without_links as $post) :
                            $word_count = str_word_count(wp_strip_all_tags($post->post_content));
                            $post_type_obj = get_post_type_object($post->post_type);
                        ?>
                        <tr>
                            <td><input type="checkbox" name="selected_posts[]" value="<?php echo esc_attr($post->ID); ?>"></td>
                            <td><?php echo esc_html($post->ID); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" target="_blank">
                                    <?php echo esc_html($post->post_title); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($post_type_obj ? $post_type_obj->labels->singular_name : $post->post_type); ?></td>
                            <td><?php echo esc_html(get_the_date('Y-m-d', $post)); ?></td>
                            <td><?php echo esc_html($word_count); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin-top: 10px;">
                    <strong><?php printf(__('Total: %d posts without internal links', 'auto-interlink'), count($posts_without_links)); ?></strong>
                </p>
                <input type="hidden" name="process_selected_posts" value="1">
                <?php submit_button(__('Process Selected Posts', 'auto-interlink'), 'primary', 'submit', false); ?>
            </form>
            <script>
            document.getElementById('select-all-posts').addEventListener('change', function() {
                var checkboxes = document.querySelectorAll('input[name="selected_posts[]"]');
                for (var i = 0; i < checkboxes.length; i++) {
                    checkboxes[i].checked = this.checked;
                }
            });
            </script>
            <?php else : ?>
            <p><em><?php _e('All posts already have internal links or no posts match your settings.', 'auto-interlink'); ?></em></p>
            <?php endif; ?>

            <hr>

            <h2><?php _e('How It Works', 'auto-interlink'); ?></h2>
            <ol>
                <li><?php _e('The plugin analyzes your posts to extract relevant 1-3 word phrases.', 'auto-interlink'); ?></li>
                <li><?php _e('It identifies which posts are most relevant to each other based on phrase overlap, categories, and tags.', 'auto-interlink'); ?></li>
                <li><?php _e('When you save a post, it automatically inserts links to related posts using natural anchor text.', 'auto-interlink'); ?></li>
                <li><?php _e('Links are permanently added to your post content in the database for better SEO.', 'auto-interlink'); ?></li>
            </ol>

            <p><strong><?php _e('Need help?', 'auto-interlink'); ?></strong> <?php _e('Visit the', 'auto-interlink'); ?> <a href="https://github.com/Micolie/interlink-wordpress" target="_blank"><?php _e('plugin repository', 'auto-interlink'); ?></a> <?php _e('for documentation and support.', 'auto-interlink'); ?></p>
        </div>
        <?php
    }

    /**
     * Process all posts for interlinking
     */
    private function process_all_posts() {
        set_time_limit(0); // Prevent timeout

        $post_types = $this->settings->get('post_types', array('post'));
        $exclude_posts = $this->settings->get('exclude_posts', array());

        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__not_in' => $exclude_posts,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $query = new WP_Query($args);
        $total_posts = $query->post_count;
        $processed = 0;
        $links_added = 0;

        $analyzer = new Auto_Interlink_Analyzer($this->settings);
        $injector = new Auto_Interlink_Injector($this->settings, $analyzer);

        foreach ($query->posts as $post) {
            $result = $injector->process_post($post->ID);
            if ($result !== false) {
                $processed++;
                $links_added += $result;
            }
        }

        echo '<div class="notice notice-success"><p>';
        printf(
            __('Bulk processing complete! Processed %d out of %d posts and added %d interlinks.', 'auto-interlink'),
            $processed,
            $total_posts,
            $links_added
        );
        echo '</p></div>';
    }

    /**
     * Get all posts without internal auto-interlinks
     */
    private function get_posts_without_links() {
        $post_types = $this->settings->get('post_types', array('post'));
        $exclude_posts = $this->settings->get('exclude_posts', array());

        $args = array(
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'post__not_in' => $exclude_posts,
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $query = new WP_Query($args);
        $posts_without_links = array();

        foreach ($query->posts as $post) {
            // Check if post doesn't have auto-interlinks
            if (strpos($post->post_content, 'class="auto-interlink"') === false) {
                $posts_without_links[] = $post;
            }
        }

        return $posts_without_links;
    }

    /**
     * Process selected posts for interlinking
     */
    private function process_selected_posts() {
        if (!isset($_POST['selected_posts']) || empty($_POST['selected_posts'])) {
            echo '<div class="notice notice-warning"><p>' . __('No posts selected.', 'auto-interlink') . '</p></div>';
            return;
        }

        set_time_limit(0); // Prevent timeout

        $selected_ids = array_map('absint', $_POST['selected_posts']);
        $processed = 0;
        $links_added = 0;

        $analyzer = new Auto_Interlink_Analyzer($this->settings);
        $injector = new Auto_Interlink_Injector($this->settings, $analyzer);

        foreach ($selected_ids as $post_id) {
            $result = $injector->process_post($post_id);
            if ($result !== false) {
                $processed++;
                $links_added += $result;
            }
        }

        echo '<div class="notice notice-success"><p>';
        printf(
            __('Selected posts processed! Added %d interlinks to %d out of %d selected posts.', 'auto-interlink'),
            $links_added,
            $processed,
            count($selected_ids)
        );
        echo '</p></div>';
    }
}
