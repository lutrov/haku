<?php

/*
Plugin Name: Haku
Description: Improves the default Wordpress search by treating the search string as a phrase, not as individual words. It takes relevancy into account, which means it places more importance on post types that contain the phrase in the title, which contain the phrase multiple times, and the relative position of the phrase. Why this plugin name? Haku means "search" in Finnish.
Author: Ivan Lutrov
Author URI: http://lutrov.com/
Version: 3.0
Notes: This plugin provides an API to customise the default constant values. See the "readme.md" file for more.
*/

defined('ABSPATH') || die('Ahem.');

//
// Define constants used by this plugin.
//
define('HAKU_DATE_FORMAT', get_option('date_format'));
define('HAKU_EXCERPT_WORD_COUNT', 80);
define('HAKU_RESULTS_PAGE_SLUG', 'search');
define('HAKU_SHOW_META', true);
define('HAKU_SHOW_META_AUTHOR', true);
define('HAKU_SHOW_META_DATE', true);
define('HAKU_SHOW_SEARCH_FORM_ON_RESULTS_PAGE', true);

//
// Don't touch these unless you want the sky to fall.
//
define('HAKU_BASE_PLUGIN_PATH', dirname(__FILE__));

//
// Get the entered search query.
//
function haku_search_query() {
	return isset($_POST['q']) ? trim(preg_replace('#[\s]+#', ' ', str_replace('"', null, $_POST['q']))) : null;
}

//
// Search form with custom parameters.
//
function haku_search_form($caption = 'Search') {
	static $form = null;
	if (strlen($form) == 0) {
		$slug = apply_filters('haku_results_page_slug_filter', HAKU_RESULTS_PAGE_SLUG);
		if ($page = get_page_by_path($slug)) {
			$action = get_permalink($page->ID);
			if (substr(trim($action, '/'), 0 - strlen($slug)) == $slug) {
				$form = sprintf('<form method="post" action="%s" class="search-form haku-search-form" role="search"><input type="search" name="q" placeholder="%s" value="%s"><input type="submit" value="%s"></form>', $action, esc_attr__('Haku for&hellip;'), haku_search_query(), esc_attr__($caption));
			}
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
		$in = haku_post_types();
		$query = "SELECT $wpdb->posts.ID, $wpdb->posts.post_name, $wpdb->posts.post_title, $wpdb->posts.post_date, $wpdb->posts.post_author, $wpdb->posts.post_content, IF(LOCATE('$q', $wpdb->posts.post_title), 1, 0) AS IN_TITLE, IF(LOCATE('$q', $wpdb->posts.post_content), 1, 0) AS IN_CONTENT, LOCATE('$q', $wpdb->posts.post_title) AS TITLE_POS, LOCATE('$q', $wpdb->posts.post_content) AS CONTENT_POS, (LENGTH($wpdb->posts.post_title) - LENGTH(REPLACE(UPPER($wpdb->posts.post_title), '$q', ''))) / LENGTH('$q') AS TITLE_CNT, (LENGTH($wpdb->posts.post_content) - LENGTH(REPLACE(UPPER($wpdb->posts.post_content), '$q', ''))) / LENGTH('%q') AS CONTENT_CNT FROM $wpdb->posts WHERE 1=1 AND ((($wpdb->posts.post_title LIKE '%$q%') OR ($wpdb->posts.post_content LIKE '%$q%'))) AND $wpdb->posts.post_type IN ($in) AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_author = 1 AND $wpdb->posts.post_status = 'private') ORDER BY IN_TITLE DESC, TITLE_CNT DESC, TITLE_POS ASC, IN_CONTENT DESC, CONTENT_CNT DESC, CONTENT_POS ASC, $wpdb->posts.post_date DESC";
die($query);
		$posts = $wpdb->get_results($query, OBJECT);
		if (($c = count($posts)) > 0) {
			if ($c > 1) {
				$result = sprintf('%s<p class="intro-text">Your search for "%s" produced %s results, sorted by relevance.</p>', $result, $q, $c);
			} else {
				$result = sprintf('%s<p class="intro-text">Your search for "%s" produced 1 result.</p>', $result, $q);
			}
			foreach ($posts as $post) {
				$content = $post->post_content;
				if (function_exists('markdown')) {
					$content = markdown($content);
				}
				$content = wp_trim_words(strip_shortcodes($content), apply_filters('haku_excerpt_word_count_filter', HAKU_EXCERPT_WORD_COUNT));
				switch (true) {
					case ($x = strrpos($content, '.')):
					case ($x = strrpos($content, ':')):
					case ($x = strrpos($content, '?')):
					case ($x = strrpos($content, '!')):
						$content = substr($content, 0, $x + 1);
						break;
				}
				$url = get_permalink($post->ID);
				$result = sprintf('%s<h2 class="entry-title"><a href="%s" rel="bookmark" >%s</a></h2>', $result, $url, $post->post_title);
				if (apply_filters('haku_show_meta_filter', HAKU_SHOW_META)) {
					$result = sprintf('%s<p class="entry-meta">', $result);
					if (apply_filters('haku_show_meta_date_filter', HAKU_SHOW_META_DATE)) {
						$result = sprintf('%s<span class="entry-time">%s</span>', $result, date(HAKU_DATE_FORMAT, strtotime($post->post_date)));
					}
					if (apply_filters('haku_show_meta_author_filter', HAKU_SHOW_META_AUTHOR)) {
						$result = sprintf('%s by <span class="entry-author-name">%s %s</span>', $result, get_the_author_meta('first_name', $post->post_author), get_the_author_meta('last_name', $post->post_author));
					}
					$result = sprintf('%s</p>', $result);
				}
				$result = sprintf('%s<p class="entry-excerpt">%s <a href="%s">%s</a></p>', $result, $content, $url, __('More'));
			}
		} else {
			$result = sprintf('%s<p>Your search for "%s" produced no results.</p>', $result, $q);
		}
	}
	if (apply_filters('haku_show_search_form_on_results_page_filter', HAKU_SHOW_SEARCH_FORM_ON_RESULTS_PAGE)) {
		$result = sprintf('%s%s', $result, haku_search_form('Search Again'));
	}
	return $result;
}

//
// Get post types to include.
//
function haku_post_types() {
	$types = apply_filters('haku_post_types_filter', array('page', 'post'));
	return sprintf("'%s'", implode("', '", $types));
}

//
// Add shortcode to use in results page.
//
if (strlen(get_option('permalink_structure')) > 0) {
	add_shortcode('haku', 'haku_search_results');
}

//
// Remove search page title.
//
add_action('template_redirect', 'haku_remove_post_title');
function haku_remove_post_title() {
	$slug = apply_filters('haku_results_page_slug_filter', HAKU_RESULTS_PAGE_SLUG);
	if (is_page($slug)) {
		if (function_exists('genesis')) {
			remove_action('genesis_entry_header', 'genesis_do_post_title');
		} else {
			add_filter('the_title', '__return_false');
		}
	}
}

//
// Setup body class.
//
add_filter('body_class', 'haku_body_class');
function haku_body_class($classes) {
	global $post;
	$slug = apply_filters('haku_results_page_slug_filter', HAKU_RESULTS_PAGE_SLUG);
	if ($post->post_name == $slug) {
		array_push($classes, 'haku-search-results');
	}
	return $classes;
}

//
// Hook into the WP search.
//
add_action('plugins_loaded', 'haku_add_filter');
function haku_add_filter() {
	add_filter('get_search_form', 'haku_search_form');
}

?>
