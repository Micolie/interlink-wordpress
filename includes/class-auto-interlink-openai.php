<?php
/**
 * OpenAI integration for semantic post matching
 */

if (!defined('ABSPATH')) {
    exit;
}

class Auto_Interlink_OpenAI {

    private $api_key;
    private $model = 'text-embedding-3-small';
    private $meta_key = '_auto_interlink_embedding';

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    /**
     * Check if API key is valid
     */
    public function is_configured() {
        return !empty($this->api_key);
    }

    /**
     * Get embedding for text
     */
    public function get_embedding($text) {
        if (!$this->is_configured()) {
            return false;
        }

        // Truncate text to avoid token limits (roughly 8000 tokens max)
        $text = substr($text, 0, 30000);

        $response = wp_remote_post('https://api.openai.com/v1/embeddings', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => $this->model,
                'input' => $text,
            )),
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['data'][0]['embedding'])) {
            return $body['data'][0]['embedding'];
        }

        return false;
    }

    /**
     * Get or generate embedding for a post
     */
    public function get_post_embedding($post_id, $force_refresh = false) {
        // Check for cached embedding
        if (!$force_refresh) {
            $cached = get_post_meta($post_id, $this->meta_key, true);
            if (!empty($cached)) {
                return $cached;
            }
        }

        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        // Create text for embedding (title + content)
        $text = $post->post_title . "\n\n" . wp_strip_all_tags($post->post_content);

        $embedding = $this->get_embedding($text);

        if ($embedding) {
            // Cache the embedding
            update_post_meta($post_id, $this->meta_key, $embedding);
        }

        return $embedding;
    }

    /**
     * Calculate cosine similarity between two vectors
     */
    public function cosine_similarity($vec1, $vec2) {
        if (count($vec1) !== count($vec2)) {
            return 0;
        }

        $dot_product = 0;
        $mag1 = 0;
        $mag2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dot_product += $vec1[$i] * $vec2[$i];
            $mag1 += $vec1[$i] * $vec1[$i];
            $mag2 += $vec2[$i] * $vec2[$i];
        }

        $mag1 = sqrt($mag1);
        $mag2 = sqrt($mag2);

        if ($mag1 == 0 || $mag2 == 0) {
            return 0;
        }

        return $dot_product / ($mag1 * $mag2);
    }

    /**
     * Find similar posts using embeddings
     */
    public function find_similar_posts($post_id, $candidate_posts, $limit = 5) {
        $source_embedding = $this->get_post_embedding($post_id);

        if (!$source_embedding) {
            return array();
        }

        $similarities = array();

        foreach ($candidate_posts as $candidate) {
            if ($candidate->ID === $post_id) {
                continue;
            }

            $candidate_embedding = $this->get_post_embedding($candidate->ID);

            if (!$candidate_embedding) {
                continue;
            }

            $similarity = $this->cosine_similarity($source_embedding, $candidate_embedding);

            $similarities[] = array(
                'post' => $candidate,
                'similarity' => $similarity,
            );
        }

        // Sort by similarity (highest first)
        usort($similarities, function($a, $b) {
            return $b['similarity'] <=> $a['similarity'];
        });

        return array_slice($similarities, 0, $limit);
    }

    /**
     * Get suggested anchor text for linking between two posts
     */
    public function get_anchor_suggestions($source_post_id, $target_post_id) {
        if (!$this->is_configured()) {
            return array();
        }

        $source = get_post($source_post_id);
        $target = get_post($target_post_id);

        if (!$source || !$target) {
            return array();
        }

        $source_content = wp_strip_all_tags($source->post_content);
        $target_title = $target->post_title;

        // Ask GPT to find the best anchor text
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'timeout' => 30,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array(
                'model' => 'gpt-4o-mini',
                'messages' => array(
                    array(
                        'role' => 'system',
                        'content' => 'You find anchor text phrases for internal linking. Given source content and a target post title, find 2-5 word phrases from the source content that would make good anchor text to link to the target post. Return only the phrases, one per line, no explanations. Only return phrases that EXACTLY exist in the source content.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => "Source content:\n" . substr($source_content, 0, 3000) . "\n\nTarget post title: " . $target_title . "\n\nFind 3-5 anchor text phrases from the source that could link to this target:"
                    ),
                ),
                'max_tokens' => 200,
                'temperature' => 0.3,
            )),
        ));

        if (is_wp_error($response)) {
            return array();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!isset($body['choices'][0]['message']['content'])) {
            return array();
        }

        $suggestions = array_filter(
            array_map('trim', explode("\n", $body['choices'][0]['message']['content']))
        );

        // Verify each suggestion actually exists in content
        $verified = array();
        $content_lower = strtolower($source_content);

        foreach ($suggestions as $suggestion) {
            $suggestion_clean = trim($suggestion, '- "\'');
            if (stripos($content_lower, strtolower($suggestion_clean)) !== false) {
                $verified[$suggestion_clean] = strlen($suggestion_clean);
            }
        }

        return $verified;
    }

    /**
     * Clear embedding cache for a post
     */
    public function clear_post_embedding($post_id) {
        delete_post_meta($post_id, $this->meta_key);
    }

    /**
     * Clear all embedding caches
     */
    public function clear_all_embeddings() {
        global $wpdb;
        $wpdb->delete($wpdb->postmeta, array('meta_key' => $this->meta_key));
    }
}
