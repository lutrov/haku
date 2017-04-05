# Haku

Haku improves the default Wordpress search by treating the search string as a phrase, not as individual words. It takes relevancy into account, which means it places more importance on post types that contain the phrase in the title, which contain the phrase multiple times, and the relative position of the phrase. Why this plugin name? Haku means "search" in Finnish.

## Professional Support

If you need professional plugin support from me, the plugin author, contact me via my website at http://lutrov.com

## Copyright and License

This project is licensed under the [GNU GPL](http://www.gnu.org/licenses/old-licenses/gpl-2.0.html), version 2 or later.

## Documentation

__First of all, crete a search results page titled "Search" and put `[haku]` in the content area.__ Haku needs this shortcode to work.

Haku will only work if your theme uses the standard Wordpress `get_search_form` hook. If your theme has a hardcoded search form, you will need to modify it so it uses the `get_search_form` hook instead.

This plugin provides an API to customise the default constant values. See this example:


	// ---- Change the Haku plugin form placeholder text.
	add_filter('haku_form_placeholder_text_filter', 'custom_haku_form_placeholder_text_filter');
	function custom_haku_form_placeholder_text_filter($value) {
		return 'Search this site&hellip;';
	}

	// ---- Change the Haku plugin SERP date format.
	add_filter('haku_serp_date_format_filter', 'custom_haku_serp_date_format_filter');
	function custom_haku_serp_date_format_filter($value) {
		return 'Y-m-d';
	}

	// ---- Change the Haku plugin SERP excerpt word count.
	add_filter('haku_serp_excerpt_word_count_filter', 'custom_haku_serp_excerpt_word_count_filter');
	function custom_haku_serp_excerpt_word_count_filter($value) {
		return 40;
	}

	// ---- Change the Haku plugin SERP slug.
	add_filter('haku_serp_slug_filter', 'custom_haku_serp_slug_filter');
	function custom_haku_serp_slug_filter($value) {
		return 'search-results';
	}

	// ---- Change the Haku plugin SERP show meta value to false.
	add_filter('haku_serp_show_meta_filter', '__return_false');

	// ---- Change the Haku plugin SERP show meta author value to false.
	add_filter('haku_serp_show_meta_author_filter', '__return_false');

	// ---- Change the Haku plugin SERP show meta date value to false.
	add_filter('haku_serp_show_meta_date_filter', '__return_false');

	// ---- Change the Haku plugin show form on SERP value to false.
	add_filter('haku_serp_show_form', 'custom_haku_serp_show_form');
	function custom_haku_serp_show_form_filter($value) {
		return false;
	}

	// ---- Change the Haku plugin custom post types to include in the search.
	add_filter('haku_post_types_filter', 'custom_haku_post_types_filter');
	function custom_haku_post_types_filter($types) {
		foreach (array('movie', 'book', 'product') as $type) {
			array_push($types, $type);
		}
		return $types;
	}

Or if you're using a custom site plugin (you should be), do it via the `plugins_loaded` hook instead:

	// ---- Change the Haku plugin constant values.
	add_action('plugins_loaded', 'custom_haku_filters');
	function custom_haku_filters() {
		// Change the Haku plugin form placeholder text.
		add_filter('haku_form_placeholder_text_filter', 'custom_haku_form_placeholder_text_filter');
		function custom_haku_form_placeholder_text_filter($value) {
			return 'Search this site&hellip;';
		}
		// Change the Haku plugin SERP date format.
		add_filter('haku_serp_date_format_filter', 'custom_haku_serp_date_format_filter');
		function custom_haku_serp_date_format_filter($value) {
			return 'Y-m-d';
		}
		// Change the Haku plugin SERP excerpt word count.
		add_filter('haku_serp_excerpt_word_count_filter', 'custom_haku_serp_excerpt_word_count_filter');
		function custom_haku_serp_excerpt_word_count_filter($value) {
			return 40;
		}
		// Change the Haku plugin SERP slug.
		add_filter('haku_serp_slug_filter', 'custom_haku_serp_slug_filter');
		function custom_haku_serp_slug_filter($value) {
			return 'search-results';
		}
		// Change the Haku plugin SERP show meta value to false.
		add_filter('haku_serp_show_meta_filter', '__return_false');
		// Change the Haku plugin SERP show meta author value to false.
		add_filter('haku_serp_show_meta_author_filter', '__return_false');
		// Change the Haku plugin SERP show meta date value to false.
		add_filter('haku_serp_show_meta_date_filter', '__return_false');
		// Change the Haku plugin show form on SERP value to false.
		add_filter('haku_serp_show_form', '__return_false');
		// Change the Haku plugin custom post types to include in the search.
		add_filter('haku_post_types_filter', 'custom_haku_post_types_filter');
		function custom_haku_post_types_filter($types) {
			foreach (array('movie', 'book', 'product') as $type) {
				array_push($types, $type);
			}
			return $types;
		}
	}

Note, this second approach will _not_ work from your theme's `functions.php` file.

Style the search results page & form using the following CSS declarations in your site theme:

	.haku-form {}
	.haku-form input[type=search] {}
	.haku-form input[type=submit] {}

	.haku-serp {}
	.haku-serp .entry-meta .message {}
	.haku-serp .entry-meta .message-no-results {}
	.haku-serp .entry-title {}
	.haku-serp .entry-permalink {}
	.haku-serp .entry-meta {}
	.haku-serp .entry-meta .entry-time {}
	.haku-serp .entry-meta .entry-author {}
	.haku-serp .entry-meta .entry-excerpt {}
