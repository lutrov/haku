<?php

/*
Plugin Name: Haku
Description: Improves the default Wordpress search by treating the search string as a phrase, not as individual words. It takes relevancy into account, which means it places more importance on post types that contain the phrase in the title, which contain the phrase multiple times, and the relative position of the phrase. Why this plugin name? Haku means "search" in Finnish.
Plugin URI: https://github.com/lutrov/haku
Author: Ivan Lutrov
Author URI: http://lutrov.com/
Version: 3.3
Notes: This plugin provides an API to customise the default constant values. See the "readme.md" file for more.
*/

defined('ABSPATH') || die('Ahem.');

//
// Define constants used by this plugin.
//
define('HAKU_SERP_EXCERPT_WORD_COUNT', 40);
define('HAKU_SERP_SHOW_AUTHOR', true);
define('HAKU_SERP_SHOW_DATE', true);
define('HAKU_SERP_CACHE_LIFETIME', 60);
define('HAKU_SERP_DATE_FORMAT', get_option('date_format'));
define('HAKU_SERP_SHOW_THUMBNAIL', true);
define('HAKU_SERP_SLUG', 'search');
define('HAKU_SERP_RESULTS_LIMIT', 100);

//
// Don't touch these unless you want the sky to fall.
//
define('HAKU_BASE_PLUGIN_PATH', dirname(__FILE__));

//
// Get the entered search query.
//
function haku_search_query() {
	$result = null;
	if (isset($_POST['q'])) {
		$result = trim(preg_replace('#[\s]+#', ' ', str_replace('"', null, $_POST['q'])));
	}
	return $result;
}

//
// Customise search form to match the rest of our logic.
//
function haku_search_form($form) {
	$slug = apply_filters('haku_serp_slug_filter', HAKU_SERP_SLUG);
	if ($page = get_page_by_path($slug)) {
		$action = get_permalink($page->ID);
		if (substr(trim($action, '/'), 0 - strlen($slug)) == $slug) {
			$form = str_replace(
				array(
					sprintf('method="get"'),
					sprintf('class="search-form"'),
					sprintf('class="searchform"'),
					sprintf('class="searchform search-form"'),
					sprintf('class="et_pb_searchform"'),
					sprintf('class="et-search-form"'),
					sprintf('action="%s"', home_url('/')),
					sprintf('value=""'),
					sprintf('name="s"')
				),
				array(
					sprintf('method="post"'),
					sprintf('class="search-form haku-form"'),
					sprintf('class="searchform haku-form"'),
					sprintf('class="search-form searchform haku-form"'),
					sprintf('class="et_pb_searchform haku-form"'),
					sprintf('class="et-search-form haku-form"'),
					sprintf('action="%s"', $action),
					sprintf('value="%s"', haku_search_query()),
					sprintf('name="q"')
				),
				$form
			);
		}
	}
	return $form;
}

//
// Search results and optional form.
//
function haku_search_results() {
	global $wpdb, $wp_query;
	$result = null;
	$q = strtoupper(haku_search_query());
	if (strlen($q) > 0) {
		if (empty($wp_query)) {
			$wp_query = new wp_query();
		} else {
			$wpdb->flush();
		}
		$limit = (int) apply_filters('haku_serp_results_limit_filter', HAKU_SERP_RESULTS_LIMIT);
		$in = haku_post_types();
		$query = "SELECT $wpdb->posts.ID, $wpdb->posts.post_name, $wpdb->posts.post_title, $wpdb->posts.post_date, $wpdb->posts.post_author, $wpdb->posts.post_content, IF(LOCATE('$q', $wpdb->posts.post_title), 1, 0) AS IN_TITLE, IF(LOCATE('$q', $wpdb->posts.post_content), 1, 0) AS IN_CONTENT, LOCATE('$q', $wpdb->posts.post_title) AS TITLE_POS, LOCATE('$q', $wpdb->posts.post_content) AS CONTENT_POS, (LENGTH($wpdb->posts.post_title) - LENGTH(REPLACE(UPPER($wpdb->posts.post_title), '$q', ''))) / LENGTH('$q') AS TITLE_CNT, (LENGTH($wpdb->posts.post_content) - LENGTH(REPLACE(UPPER($wpdb->posts.post_content), '$q', ''))) / LENGTH('%q') AS CONTENT_CNT FROM $wpdb->posts WHERE 1=1 AND ((($wpdb->posts.post_title LIKE '%$q%') OR ($wpdb->posts.post_content LIKE '%$q%'))) AND $wpdb->posts.post_type IN ($in) AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_author = 1 AND $wpdb->posts.post_status = 'private') ORDER BY IN_TITLE DESC, TITLE_CNT DESC, TITLE_POS ASC, IN_CONTENT DESC, CONTENT_CNT DESC, CONTENT_POS ASC, $wpdb->posts.post_date DESC LIMIT $limit";
		$posts = get_transient(sprintf('haku_%s', hash('md5', $query)));
		if ($posts == false) {
			$posts = $wpdb->get_results($query, OBJECT);
			if (count($posts) > 0) {
				set_transient(sprintf('haku_%s', hash('md5', $query)), $posts, apply_filters('haku_serp_cache_lifetime_filter', HAKU_SERP_CACHE_LIFETIME));
			}
		}
		if (($c = count($posts)) > 0) {
			switch ($c) {
				case $limit:
					$result = sprintf('%s<p class="message">Your search for "%s" produced the top %s results, sorted by relevance.</p>', $result, $q, $c);
					break;
				case 1:
					$result = sprintf('%s<p class="message">Your search for "%s" produced 1 result.</p>', $result, $q);
					break;
				default:
					$result = sprintf('%s<p class="message">Your search for "%s" produced %s results, sorted by relevance.</p>', $result, $q, $c);
					break;
			}
			foreach ($posts as $post) {
				$permalink = get_permalink($post->ID);
				$result = sprintf('%s<div class="entry"><h2 class="entry-title"><a href="%s" rel="bookmark">%s</a></h2><div class="entry-permalink"><a href="%s">%s</a></div>', $result, $permalink, $post->post_title, $permalink, $permalink);
				$thumbnail = null;
				if (apply_filters('haku_serp_show_thumbnail_filter', HAKU_SERP_SHOW_THUMBNAIL)) {
					$thumbnail = get_the_post_thumbnail($post->ID, 'thumbnail');
				}
				if (strlen($thumbnail) > 0) {
					$result = sprintf('%s<div class="entry-image">%s</div>', $result, $thumbnail);
				}
				$meta = null;
				if (apply_filters('haku_serp_show_date_filter', HAKU_SERP_SHOW_DATE)) {
					$meta = sprintf('%s<span class="entry-date">%s</span>', $meta, date(HAKU_SERP_DATE_FORMAT, strtotime($post->post_date)));
				}
				if (apply_filters('haku_serp_show_author_filter', HAKU_SERP_SHOW_AUTHOR)) {
					$meta = sprintf('%s by <span class="entry-author">%s %s</span>', $meta, get_the_author_meta('first_name', $post->post_author), get_the_author_meta('last_name', $post->post_author));
				}
				if (strlen($meta) > 0) {
					$result = sprintf('%s<div class="entry-meta">%s</div>', $result, $meta);
				}
				$content = $post->post_excerpt;
				if (strlen($content) == 0) {
					$content = $post->post_content;
					if (function_exists('markdown')) {
						$content = markdown($content);
					}
					$content = wp_trim_words(strip_shortcodes($content), apply_filters('haku_serp_excerpt_word_count_filter', HAKU_SERP_EXCERPT_WORD_COUNT));
				}
				switch (true) {
					case ($x = strrpos($content, '.')):
					case ($x = strrpos($content, ':')):
					case ($x = strrpos($content, '?')):
					case ($x = strrpos($content, '!')):
						$content = substr($content, 0, $x + 1);
						break;
				}
				$result = sprintf('%s<div class="entry-excerpt">%s</div></div>', $result, $content);
			}
		} else {
			$result = sprintf('%s<p class="message message-no-results">Your search for "%s" produced no results.</p>', $result, $q);
		}
	}
	return $result;
}

//
// Get post types to include.
//
function haku_post_types() {
	$result = sprintf("'%s'", implode("', '", apply_filters('haku_post_types_filter', array('page', 'post', 'product'))));
	return $result;
}

//
// Setup body class.
//
add_filter('body_class', 'haku_body_class');
function haku_body_class($classes) {
	global $post;
	$slug = apply_filters('haku_serp_slug_filter', HAKU_SERP_SLUG);
	if ($post->post_name == $slug) {
		array_push($classes, 'haku-serp');
	}
	return $classes;
}

//
// Hook into the WP search.
//
add_action('plugins_loaded', 'haku_add_filter');
function haku_add_filter() {
	add_filter('get_search_form', 'haku_search_form', 11);
}

//
// Add shortcode to use in results page.
//
if (strlen(get_option('permalink_structure')) > 0) {
	add_shortcode('haku', 'haku_search_results');
}

?>
