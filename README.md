# Auto Interlink - WordPress Plugin

**Automatically create natural interlinks between relevant WordPress posts using SEO-friendly longtail anchor text.**

This plugin analyzes your WordPress posts, identifies the most relevant connections, and automatically creates hyperlinks using 3-5 word longtail phrases. It saves tons of hours by automating the entire internal linking process, improving SEO and user navigation.

## Features

- 🎯 **Smart Relevance Detection**: Analyzes post content, categories, and tags to find the most relevant connections
- 🔗 **Longtail Anchor Text**: Uses 3-5 word phrases for better SEO value (no single-word links)
- 💾 **Direct Database Modification**: Links are permanently added to your post content for optimal SEO
- 🔄 **Automatic Processing**: Links are added automatically when you save or update posts
- 📦 **Bulk Processing**: Process all existing posts at once with one click
- 🎛️ **Fully Customizable**: Control link density, post types, exclusions, and more
- 🚀 **Performance Optimized**: Built-in caching system to ensure fast page loads
- 🎨 **Easy Configuration**: Simple admin interface with all settings in one place

## How It Works

1. **Analyzes Content**: The plugin extracts relevant 3-5 word longtail phrases from your posts
2. **Finds Connections**: Identifies which posts are most relevant based on phrase overlap, categories, and tags
3. **Adds Links Automatically**: When you save a post, it permanently inserts links to related posts using natural longtail anchor text
4. **Direct Modification**: Links are permanently added to your post content in the database for better SEO

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
- **Minimum Phrase Length**: Minimum characters for longtail phrases (default: 10)
- **Maximum Phrase Length**: Maximum characters for longtail phrases (default: 100)
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

## Usage Examples

### Example 1: Blog with Related Articles

If you have a blog post about "WordPress SEO Best Practices" and another about "Improving Website Performance", the plugin will:

1. Extract longtail phrases like "wordpress seo best", "website performance optimization", "improve page speed"
2. Calculate relevance based on phrase overlap and taxonomy
3. Automatically add links using natural 3-5 word anchors like "wordpress seo best practices" or "website performance optimization"

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
4. Save or update the post to trigger link processing
5. Use the "Process All Posts" button to process existing posts
6. Check that your content has suitable 3-5 word phrases for linking

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

### Version 1.1.0 (Latest)
- **BREAKING CHANGE**: Switched from filter-based to direct database modification for better SEO
- **NEW**: Longtail anchor text (3-5 word phrases only) for improved SEO value
- **NEW**: Bulk processing feature to process all existing posts at once
- **FIX**: Resolved text truncation bug that was causing content loss
- **IMPROVED**: Better phrase extraction algorithm for more natural anchor text
- **IMPROVED**: Updated default phrase length settings (10-100 characters)
- **IMPROVED**: More robust content modification that preserves all HTML and formatting

### Version 1.0.0
- Initial release
- Smart relevance detection
- Automatic link insertion
- Comprehensive admin settings
- Performance optimization with caching
- Support for multiple post types
- Category and tag boosting
