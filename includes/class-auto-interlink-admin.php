<?php
/**
 * Admin interface class - Optimized for performance
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Interlink_Admin {

    private $settings;

    public function __construct($settings) {
        $this->settings = $settings;
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_admin_menu() {
        add_options_page(
            __('Auto Interlink Settings', 'auto-interlink'),
            __('Auto Interlink', 'auto-interlink'),
            'manage_options',
            'auto-interlink-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('auto_interlink_settings_group', 'auto_interlink_settings', array($this, 'sanitize_settings'));
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        $sanitized['enabled'] = !empty($input['enabled']);
        $sanitized['max_links_per_post'] = absint($input['max_links_per_post'] ?? 5);
        $sanitized['min_keyword_length'] = absint($input['min_keyword_length'] ?? 3);
        $sanitized['max_keyword_length'] = absint($input['max_keyword_length'] ?? 100);
        $sanitized['min_post_length'] = absint($input['min_post_length'] ?? 100);
        $sanitized['link_to_newer_posts'] = !empty($input['link_to_newer_posts']);
        $sanitized['link_to_older_posts'] = !empty($input['link_to_older_posts']);
        $sanitized['case_sensitive'] = !empty($input['case_sensitive']);
        $sanitized['same_category_boost'] = !empty($input['same_category_boost']);
        $sanitized['same_tag_boost'] = !empty($input['same_tag_boost']);
        $sanitized['post_types'] = isset($input['post_types']) ? array_map('sanitize_text_field', (array)$input['post_types']) : array('post');
        $sanitized['exclude_posts'] = array();
        if (!empty($input['exclude_posts'])) {
            foreach (explode(',', $input['exclude_posts']) as $id) {
                if ($id = absint(trim($id))) $sanitized['exclude_posts'][] = $id;
            }
        }
        $sanitized['enable_ai'] = !empty($input['enable_ai']);
        $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key'] ?? '');
        return $sanitized;
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) return;

        // Handle actions
        if (isset($_POST['clear_cache']) && check_admin_referer('auto_interlink_clear_cache')) {
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_auto_interlink_%'");
            echo '<div class="notice notice-success"><p>Cache cleared!</p></div>';
        }

        if (isset($_POST['process_selected_posts']) && check_admin_referer('auto_interlink_process_selected')) {
            $this->process_selected_posts();
        }

        $settings = $this->settings->get_all();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('auto_interlink_settings_group'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="enabled">Enable Auto Interlinking</label></th>
                        <td><input type="checkbox" name="auto_interlink_settings[enabled]" id="enabled" value="1" <?php checked($settings['enabled']); ?>></td>
                    </tr>
                    <tr>
                        <th><label for="max_links_per_post">Max Links Per Post</label></th>
                        <td><input type="number" name="auto_interlink_settings[max_links_per_post]" value="<?php echo esc_attr($settings['max_links_per_post']); ?>" min="1" max="20" class="small-text"></td>
                    </tr>
                    <tr>
                        <th><label for="min_post_length">Min Post Length (words)</label></th>
                        <td><input type="number" name="auto_interlink_settings[min_post_length]" value="<?php echo esc_attr($settings['min_post_length']); ?>" min="0" class="small-text"></td>
                    </tr>
                    <tr>
                        <th>Post Types</th>
                        <td>
                            <?php foreach (get_post_types(array('public' => true), 'objects') as $pt) :
                                if ($pt->name === 'attachment') continue;
                            ?>
                            <label style="display:block;"><input type="checkbox" name="auto_interlink_settings[post_types][]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $settings['post_types'])); ?>> <?php echo esc_html($pt->labels->name); ?></label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Relevance Boosting</th>
                        <td>
                            <label style="display:block;"><input type="checkbox" name="auto_interlink_settings[same_category_boost]" value="1" <?php checked($settings['same_category_boost']); ?>> Same category boost</label>
                            <label style="display:block;"><input type="checkbox" name="auto_interlink_settings[same_tag_boost]" value="1" <?php checked($settings['same_tag_boost']); ?>> Same tag boost</label>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="exclude_posts">Exclude Post IDs</label></th>
                        <td><input type="text" name="auto_interlink_settings[exclude_posts]" value="<?php echo esc_attr(implode(', ', $settings['exclude_posts'])); ?>" class="regular-text" placeholder="1, 5, 23"></td>
                    </tr>
                </table>

                <h2 style="margin-top:30px;">AI-Powered Linking (Optional)</h2>
                <table class="form-table">
                    <tr>
                        <th><label for="enable_ai">Enable AI</label></th>
                        <td>
                            <input type="checkbox" name="auto_interlink_settings[enable_ai]" id="enable_ai" value="1" <?php checked($settings['enable_ai'] ?? false); ?>>
                            <span>Use OpenAI for semantic matching</span>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="openai_api_key">OpenAI API Key</label></th>
                        <td>
                            <input type="password" name="auto_interlink_settings[openai_api_key]" value="<?php echo esc_attr($settings['openai_api_key'] ?? ''); ?>" class="regular-text">
                            <?php if (!empty($settings['openai_api_key'])) : ?><span style="color:green;"> ✓ Set</span><?php endif; ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Save Settings'); ?>
            </form>

            <hr>
            <h2>Cache Management</h2>
            <form method="post">
                <?php wp_nonce_field('auto_interlink_clear_cache'); ?>
                <input type="hidden" name="clear_cache" value="1">
                <?php submit_button('Clear Cache', 'secondary', 'submit', false); ?>
            </form>

            <hr>
            <h2>Process Posts Without Links</h2>
            <?php $posts = $this->get_posts_without_links(50); ?>
            <?php if (!empty($posts)) : ?>
            <form method="post">
                <?php wp_nonce_field('auto_interlink_process_selected'); ?>
                <table class="wp-list-table widefat fixed striped" style="max-width:600px;">
                    <thead><tr><th style="width:30px;"><input type="checkbox" id="select-all"></th><th>ID</th><th>Title</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($posts as $p) : ?>
                        <tr>
                            <td><input type="checkbox" name="selected_posts[]" value="<?php echo $p->ID; ?>"></td>
                            <td><?php echo $p->ID; ?></td>
                            <td><a href="<?php echo get_edit_post_link($p->ID); ?>" target="_blank"><?php echo esc_html($p->post_title); ?></a></td>
                            <td><?php echo get_the_date('Y-m-d', $p); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p><strong>Showing up to 50 posts.</strong></p>
                <?php submit_button('Process Selected', 'primary', 'submit', false); ?>
            </form>
            <script>document.getElementById('select-all').onchange=function(){document.querySelectorAll('input[name="selected_posts[]"]').forEach(c=>c.checked=this.checked)};</script>
            <?php else : ?>
            <p><em>All posts already have internal links.</em></p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_posts_without_links($limit = 50) {
        global $wpdb;
        $post_types = $this->settings->get('post_types', array('post'));
        $types_sql = "'" . implode("','", array_map('esc_sql', $post_types)) . "'";

        // Direct SQL for speed - only get posts without 'auto-interlink' class
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, post_title, post_date FROM {$wpdb->posts}
             WHERE post_type IN ({$types_sql})
             AND post_status = 'publish'
             AND post_content NOT LIKE %s
             ORDER BY post_date DESC
             LIMIT %d",
            '%class="auto-interlink"%',
            $limit
        ));

        return $results;
    }

    private function process_selected_posts() {
        if (empty($_POST['selected_posts'])) {
            echo '<div class="notice notice-warning"><p>No posts selected.</p></div>';
            return;
        }

        $ids = array_map('absint', $_POST['selected_posts']);
        $processed = 0;
        $links = 0;
        $errors = array();

        $analyzer = new Auto_Interlink_Analyzer($this->settings);
        $injector = new Auto_Interlink_Injector($this->settings, $analyzer);

        foreach ($ids as $id) {
            $post = get_post($id);
            $post_title = $post ? $post->post_title : "Post #$id";

            $result = $injector->process_post($id);
            if ($result !== false) {
                $processed++;
                $links += $result;
            } else {
                $error = $injector->get_last_error();
                $errors[] = '<strong>' . esc_html($post_title) . '</strong>: ' . esc_html($error);
            }
        }

        if ($processed > 0) {
            echo '<div class="notice notice-success"><p>✓ Added ' . $links . ' links to ' . $processed . ' post(s).</p></div>';
        }

        if (!empty($errors)) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Could not add links to ' . count($errors) . ' post(s):</strong></p>';
            echo '<ul style="margin-left:20px;list-style:disc;">';
            foreach ($errors as $error) {
                echo '<li>' . $error . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        if ($processed === 0 && empty($errors)) {
            echo '<div class="notice notice-info"><p>No changes were made.</p></div>';
        }
    }
}
