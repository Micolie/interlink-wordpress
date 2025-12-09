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
     * Uses AI to find contextual matches including synonyms and related concepts
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
        $target_url = get_permalink($target->ID);
        $target_slug = $target->post_name;
        $target_excerpt = wp_strip_all_tags($target->post_excerpt ?: wp_trim_words($target->post_content, 50, ''));

        // Build context about the target post from URL and content
        $target_context = $this->build_target_context($target_title, $target_slug, $target_excerpt);

        // Ask GPT to find contextually relevant anchor text
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
                        'content' => 'You are an SEO expert finding anchor text for internal links. Your task is to find phrases in the SOURCE CONTENT that would make natural, contextually relevant links to the TARGET POST.

IMPORTANT RULES:
1. The anchor text MUST be an EXACT phrase that exists verbatim in the source content
2. Look for CONTEXTUAL MATCHES - phrases that relate to the target topic through:
   - Synonyms (e.g., "automobile" can link to a post about "cars")
   - Related concepts (e.g., "search rankings" can link to "SEO tips")
   - Broader/narrower terms (e.g., "marketing strategy" can link to "content marketing")
   - Action phrases (e.g., "optimize your website" can link to "website optimization guide")
3. Prefer 2-5 word phrases for natural anchor text
4. The phrase must read naturally as a hyperlink in context

Return ONLY the exact phrases from the source content, one per line, no explanations or formatting.'
                    ),
                    array(
                        'role' => 'user',
                        'content' => "=== SOURCE CONTENT (find anchor phrases here) ===\n" . substr($source_content, 0, 4000) . "\n\n=== TARGET POST CONTEXT ===\nTitle: " . $target_title . "\nURL slug: " . $target_slug . "\nSummary: " . $target_context . "\n\nFind 3-6 phrases from the source content that would make good contextual anchor text to link to this target post. Remember: phrases must EXACTLY exist in the source content above."
                    ),
                ),
                'max_tokens' => 300,
                'temperature' => 0.4,
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

        // Verify each suggestion actually exists in content with word boundary matching
        $verified = array();

        foreach ($suggestions as $suggestion) {
            $suggestion_clean = trim($suggestion, '- "\'*•0123456789.');
            $suggestion_clean = trim($suggestion_clean);

            if (empty($suggestion_clean) || strlen($suggestion_clean) < 3) {
                continue;
            }

            // Use word boundary regex to verify exact phrase exists
            $pattern = '/\b' . preg_quote($suggestion_clean, '/') . '\b/iu';
            if (preg_match($pattern, $source_content)) {
                // Weight by phrase length (longer = better for SEO, but cap it)
                $word_count = str_word_count($suggestion_clean);
                $weight = min($word_count * 15, 75); // Cap at 5 words worth
                $verified[$suggestion_clean] = $weight;
            }
        }

        // Sort by weight (higher = better)
        arsort($verified);

        return $verified;
    }

    /**
     * Build context string from target post information
     */
    private function build_target_context($title, $slug, $excerpt) {
        // Extract meaningful words from slug (replace hyphens with spaces)
        $slug_words = str_replace(array('-', '_'), ' ', $slug);

        // Combine into a context string
        $context_parts = array();

        if (!empty($excerpt)) {
            $context_parts[] = $excerpt;
        }

        // Add slug context if it provides different info than title
        $slug_lower = strtolower($slug_words);
        $title_lower = strtolower($title);
        if (similar_text($slug_lower, $title_lower) / max(strlen($slug_lower), strlen($title_lower)) < 0.7) {
            $context_parts[] = "URL keywords: " . $slug_words;
        }

        return implode('. ', $context_parts);
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
