# Auto Interlink - WordPress Plugin

**Automatically create natural interlinks between relevant WordPress posts using SEO-friendly anchor text.**

This plugin analyzes your WordPress posts, identifies the most relevant connections, and automatically creates hyperlinks using 1-7 word phrases. It prioritizes posts in the same category for better SEO relevance.

## Features

- 🎯 **Smart Relevance Detection**: Analyzes post content, categories, and tags to find the most relevant connections
- 🔗 **Natural Anchor Text**: Uses 1-7 word phrases from target post titles for optimal SEO
- 🏷️ **Category Priority**: Same-category posts are heavily prioritized for better relevance
- 💾 **Direct Database Modification**: Links are permanently added to your post content for optimal SEO
- 🔄 **Automatic Processing**: Links are added automatically when you save or update posts
- 📦 **Bulk Processing**: Process all existing posts at once with one click
- 📋 **Selective Processing**: View posts without links and choose which ones to process
- 🎛️ **Fully Customizable**: Control link density, post types, exclusions, and more
- 🚀 **Performance Optimized**: Built-in caching system to ensure fast page loads
- 🎨 **Easy Configuration**: Simple admin interface with all settings in one place

## How It Works

1. **Finds Target Posts**: Looks for other posts in the same category (highest priority) or with matching tags
2. **Extracts Anchor Text**: Takes 1-7 word phrases from the target post's title
3. **Matches in Content**: Searches for these phrases in your current post's content
4. **Creates Links**: When a match is found, it creates a link using the phrase as anchor text

## Installation

### From GitHub

1. Download the plugin files or clone this repository
2. Upload the `auto-interlink` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Configure settings under Settings → Auto Interlink

### Manual Installation

```bash
cd /path/to/wordpress/wp-content/plugins/
git clone https://github.com/Micolie/interlink-wordpress.git auto-interlink
```

Then activate the plugin in WordPress admin.

## Configuration

Navigate to **Settings → Auto Interlink** in your WordPress admin to configure:

### Basic Settings

- **Enable Auto Interlinking**: Toggle the plugin on/off
- **Maximum Links Per Post**: Control how many automatic links to add (default: 5)
- **Minimum Phrase Length**: Minimum characters for anchor text (default: 3)
- **Maximum Phrase Length**: Maximum characters for anchor text (default: 100)
- **Maximum Phrase Words**: Maximum words for anchor text (default: 7)
- **Minimum Post Length**: Only add links to posts with this many words (default: 100)

### Post Types

Select which post types should have automatic interlinking:
- Posts
- Pages
- Custom Post Types

### Linking Options

- **Link to newer posts**: Allow linking to posts published after the current post
- **Link to older posts**: Allow linking to posts published before the current post
- **Case sensitive matching**: Enable case-sensitive keyword matching

### Relevance Boosting

- **Same category boost**: Prioritize linking to posts in the same category
- **Same tag boost**: Prioritize linking to posts with similar tags

### Exclusions

Enter post IDs (comma-separated) to exclude specific posts from the interlinking system.

### Bulk Processing

Process all existing posts at once to add interlinks. This feature allows you to:
- Add interlinks to all your existing posts with one click
- Re-process posts after changing settings
- Useful for initial setup or after importing content

**Important**: This directly modifies your post content in the database. Always backup your database before bulk processing.

### Selective Processing

The "Posts Without Internal Links" section displays all posts that don't have auto-interlinks yet:
- View a table of all posts without internal links
- See post ID, title, type, date, and word count
- Select specific posts using checkboxes
- Process only the posts you choose to avoid database overload

This is useful when you want more control over which posts receive automatic links.

**Note**: The plugin uses 1-7 word phrases from target post titles as anchor text for natural interlinking.

## Usage Examples

### Example 1: Blog with Related Articles

If you have a post titled "WordPress SEO Best Practices" and your content mentions "seo best practices", the plugin will:

1. Find posts in the same category first (priority linking)
2. Look for the target post's title words in your content (e.g., "seo", "best practices", "wordpress seo")
3. Create a link using the matched phrase as anchor text

### Example 2: Documentation Site

For a documentation site with interconnected topics:

- Posts about "Getting Started" will link to "Installation" and "Configuration"
- Posts in the same category get priority for interlinking
- Technical terms are automatically hyperlinked to their definition posts

## Performance

The plugin is optimized for performance:

- **Caching**: Relevance calculations are cached for 1 hour
- **Processing on Save**: Links are only generated when you save/update a post
- **Efficient Queries**: Optimized database queries to minimize overhead
- **Cache Management**: Clear cache from settings page or when posts are updated
- **No Runtime Overhead**: Since links are permanently added to content, there's no processing overhead when displaying posts

## Customization

### Filters

Developers can customize behavior using WordPress filters:

```php
// Modify extracted keywords
add_filter('auto_interlink_keywords', function($keywords, $post_id) {
    // Your custom logic
    return $keywords;
}, 10, 2);

// Adjust relevance score
add_filter('auto_interlink_relevance_score', function($score, $source_post, $target_post) {
    // Your custom logic
    return $score;
}, 10, 3);
```

### CSS Classes

Links added by the plugin have the class `auto-interlink`, allowing you to style them:

```css
.auto-interlink {
    color: #0073aa;
    text-decoration: underline;
}
```

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher

## FAQ

### Does this modify my post content in the database?

Yes. Starting from version 1.1.0, the plugin permanently adds links to your post content in the database. This provides better SEO value and eliminates runtime overhead. Always backup your database before using the plugin.

### Can I exclude specific posts?

Yes, use the "Exclude Posts" setting to enter comma-separated post IDs that should be excluded.

### How often is the cache updated?

The cache expires after 1 hour. It's also automatically cleared when you update or delete posts. You can manually clear it from the settings page.

### Will this slow down my site?

No. Links are permanently added to your content when you save posts, so there's zero runtime overhead when displaying posts. The plugin only processes content when you save or update a post.

### Can I control which post types are interlinked?

Yes, you can select specific post types in the settings (posts, pages, custom post types).

## Troubleshooting

### Links aren't appearing

1. Check that the plugin is enabled in Settings → Auto Interlink
2. Verify your post meets the minimum word count (default: 100 words)
3. Ensure the post type is enabled in settings
4. Clear the cache from the settings page
5. Use the "Posts Without Internal Links" section to select and process specific posts
6. Ensure your content contains words from other posts' titles (the anchor text source)
7. Posts in the same category are prioritized - check category assignments

### Too many/few links

Adjust the "Maximum Links Per Post" setting to control link density.

### Links to irrelevant posts

- Enable "Same category boost" to prioritize related content
- Adjust minimum/maximum keyword length
- Use the exclude posts feature for specific posts

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.

## Support

For issues, questions, or contributions:
- GitHub Issues: https://github.com/Micolie/interlink-wordpress/issues
- Repository: https://github.com/Micolie/interlink-wordpress

## Changelog

### Version 1.5.0 (Latest)
- **IMPROVED**: Minimum 2-word phrases for better SEO value (no more single-word anchors)
- **IMPROVED**: Anchor text now ranges from 2-7 words for richer context to search crawlers

### Version 1.4.0
- **REWRITE**: Completely rewritten algorithm for reliable linking
- **NEW**: Simple word matching - finds individual words from target titles in your content
- **NEW**: Preserves original text case when creating links
- **IMPROVED**: Minimum word length of 4 characters to avoid common words
- **IMPROVED**: Extended stop words list to filter out generic terms
- **FIX**: Links now actually get added to posts

### Version 1.3.0
- **NEW**: Anchor text now uses 1-7 word phrases (configurable) instead of 1-3
- **NEW**: Simplified matching algorithm - uses target post titles as anchor text source
- **IMPROVED**: Same-category posts now heavily prioritized (100 points vs 50 for tags)
- **IMPROVED**: Reduced minimum keyword length from 10 to 3 characters
- **FIX**: Algorithm now properly finds linkable phrases in content

### Version 1.2.0
- **NEW**: Posts Without Internal Links section - view and select specific posts to process
- **NEW**: Selective processing - choose which posts to add links to, avoiding database overload
- **FIX**: Fixed critical bug where posts with existing links couldn't receive new auto-interlinks
- **IMPROVED**: Simplified and more reliable phrase detection algorithm

### Version 1.1.0
- **BREAKING CHANGE**: Switched from filter-based to direct database modification for better SEO
- **NEW**: Smart anchor text using 1-3 word phrases for natural interlinking
- **NEW**: Bulk processing feature to process all existing posts at once
- **FIX**: Resolved text truncation bug that was causing content loss
- **IMPROVED**: Better phrase extraction algorithm with weighted scoring (longer phrases prioritized)
- **IMPROVED**: Updated default phrase length settings (3-100 characters)
- **IMPROVED**: More robust content modification that preserves all HTML and formatting
- **IMPROVED**: Single words, two-word, and three-word phrases all supported

### Version 1.0.0
- Initial release
- Smart relevance detection
- Automatic link insertion
- Comprehensive admin settings
- Performance optimization with caching
- Support for multiple post types
- Category and tag boosting
