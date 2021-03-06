<?php

/*
Plugin Name: Haku
Plugin URI: https://github.com/lutrov/haku
Description: Improves the default Wordpress search by treating the search string as a phrase, not as individual words. It takes relevancy into account, which means it places more importance on post types that contain the phrase in the title, which contain the phrase multiple times, and the relative position of the phrase. Why this plugin name? Haku means "search" in Finnish.
Version: 4.4
Author: Ivan Lutrov
Author URI: http://lutrov.com/
Copyright: 2016, Ivan Lutrov

This program is free software; you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation; either version 2 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program; if not, write to the Free Software Foundation, Inc., 51 Franklin
Street, Fifth Floor, Boston, MA 02110-1301, USA. Also add information on how to
contact you by electronic and paper mail.
*/

defined('ABSPATH') || die('Ahem.');

//
// Define constants used by this plugin.
//
define('HAKU_SERP_EXCERPT_WORD_COUNT', 40);
define('HAKU_SERP_SHOW_AUTHOR', false);
define('HAKU_SERP_SHOW_DATE', false);
define('HAKU_SERP_CACHE_LIFETIME', 1);
define('HAKU_SERP_DATE_FORMAT', get_option('date_format'));
define('HAKU_SERP_SHOW_THUMBNAIL', true);
define('HAKU_SERP_SLUG', 'search');
define('HAKU_SERP_RESULTS_LIMIT', 100);

//
// Don't touch these unless you want the sky to fall.
//
define('HAKU_PLUGIN_PATH', dirname(__FILE__));

//
// Get the entered search query.
//
function haku_search_query() {
	$result = null;
	if (isset($_POST['q'])) {
		$result = trim(preg_replace('#[\s]+#', ' ', str_replace('"', null, wp_unslash($_POST['q']))));

	}
	return $result;
}

//
// Customise search form to match the rest of our logic.
//
function haku_search_form($form) {
	$slug = apply_filters('haku_serp_slug', HAKU_SERP_SLUG);
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
		$limit = (int) apply_filters('haku_serp_results_limit', HAKU_SERP_RESULTS_LIMIT);
		$in = haku_post_types();
		$query = "SELECT $wpdb->posts.ID, $wpdb->posts.post_name, $wpdb->posts.post_title, $wpdb->posts.post_date, $wpdb->posts.post_author, $wpdb->posts.post_excerpt, $wpdb->posts.post_content, $wpdb->posts.post_type, IF(LOCATE('$q', $wpdb->posts.post_title), 1, 0) AS IN_TITLE, IF(LOCATE('$q', $wpdb->posts.post_content), 1, 0) AS IN_CONTENT, LOCATE('$q', $wpdb->posts.post_title) AS TITLE_POS, LOCATE('$q', $wpdb->posts.post_content) AS CONTENT_POS, (LENGTH($wpdb->posts.post_title) - LENGTH(REPLACE(UPPER($wpdb->posts.post_title), '$q', ''))) / LENGTH('$q') AS TITLE_CNT, (LENGTH($wpdb->posts.post_content) - LENGTH(REPLACE(UPPER($wpdb->posts.post_content), '$q', ''))) / LENGTH('%q') AS CONTENT_CNT FROM $wpdb->posts WHERE 1=1 AND ((($wpdb->posts.post_title LIKE '%$q%') OR ($wpdb->posts.post_content LIKE '%$q%'))) AND $wpdb->posts.post_type IN ($in) AND ($wpdb->posts.post_status = 'publish' OR $wpdb->posts.post_author = 1 AND $wpdb->posts.post_status = 'private') ORDER BY IN_TITLE DESC, TITLE_CNT DESC, TITLE_POS ASC, IN_CONTENT DESC, CONTENT_CNT DESC, CONTENT_POS ASC, $wpdb->posts.post_date DESC LIMIT $limit";
		$result = get_transient(sprintf('haku_%s', hash('md5', $query)));
		if ($result == false) {
			$posts = apply_filters('haku_results', $wpdb->get_results($query, OBJECT));
			if (($c = count($posts)) > 0) {
				switch ($c) {
					case $limit:
						$result = sprintf('%s<p class="message">%s</p>', $result, sprintf(__('Your search for "%s" produced the top %s results, sorted by relevance.'), $q, $c));
						break;
					case 1:
						$result = sprintf('%s<p class="message">%s</p>', $result, sprintf(__('Your search for "%s" produced 1 result.'), $q));
						break;
					default:
						$result = sprintf('%s<p class="message">%s</p>', $result, sprintf(__('Your search for "%s" produced %s results, sorted by relevance.'), $q, $c));
						break;
				}
				foreach ($posts as $post) {
					$permalink = get_permalink($post->ID);
					$result = sprintf('%s<div class="item"><h2 class="item-title"><a href="%s" rel="bookmark">%s</a></h2><p class="item-permalink">%s</p>', $result, $permalink, esc_attr($post->post_title), $permalink);
					$meta = null;
					if (apply_filters('haku_serp_show_date', HAKU_SERP_SHOW_DATE)) {
						$meta = sprintf('%s<span class="item-date">%s</span>', $meta, date(HAKU_SERP_DATE_FORMAT, strtotime($post->post_date)));
					}
					if (apply_filters('haku_serp_show_author', HAKU_SERP_SHOW_AUTHOR)) {
						$meta = sprintf('%s by <span class="item-author">%s %s</span>', $meta, get_the_author_meta('first_name', $post->post_author), get_the_author_meta('last_name', $post->post_author));
					}
					if (empty($meta) == false) {
						$result = sprintf('%s<p class="item-meta">%s</p>', $result, $meta);
					}
					$thumbnail = null;
					if (apply_filters('haku_serp_show_thumbnail', HAKU_SERP_SHOW_THUMBNAIL)) {
						$thumbnail = get_the_post_thumbnail($post->ID, 'thumbnail');
					}
					$result = sprintf('%s<div class="item-content with%s-image">', $result, empty($thumbnail) == false ? null : 'out');
					if (empty($thumbnail) == false) {
						$result = sprintf('%s<p class="item-image">%s</p>', $result, $thumbnail);
					}
					$content = $post->post_excerpt;
					if (empty($content) == true) {
						$content = $post->post_content;
					}
					$content = wp_trim_words(do_shortcode($content), apply_filters('haku_serp_excerpt_word_count', HAKU_SERP_EXCERPT_WORD_COUNT));
					switch (true) {
						case ($x = strrpos($content, '.')):
						case ($x = strrpos($content, ':')):
						case ($x = strrpos($content, '?')):
						case ($x = strrpos($content, '!')):
							$content = substr($content, 0, $x + 1);
							break;
					}
					$result = sprintf('%s<p class="item-excerpt">%s</p></div></div>', $result, $content);
					set_transient(sprintf('haku_%s', hash('md5', $query)), $result, apply_filters('haku_serp_cache_lifetime', HAKU_SERP_CACHE_LIFETIME));
				}
			} else {
				$result = sprintf('%s<p class="message message-no-results">%s</p>', $result, sprintf(__('Your search for "%s" produced no results.'), $q));
			}
		}
	}
	return $result;
}

//
// Get post types to include.
//
function haku_post_types() {
	$result = sprintf("'%s'", implode("', '", apply_filters('haku_post_types', array('page', 'post'))));
	return $result;
}

//
// Setup body class.
//
add_filter('body_class', 'haku_body_class_filter');
function haku_body_class_filter($classes) {
	global $post;
	$slug = apply_filters('haku_serp_slug', HAKU_SERP_SLUG);
	if ($post->post_name == $slug) {
		array_push($classes, 'haku-serp');
	}
	return $classes;
}

//
// Hook into the WP search.
//
add_action('plugins_loaded', 'haku_add_filter_action');
function haku_add_filter_action() {
	add_filter('get_search_form', 'haku_search_form', 11);
}

//
// Add shortcode to use in results page.
//
add_action('plugins_loaded', 'haku_add_shortcode_action');
function haku_add_shortcode_action() {
	if (empty(get_option('permalink_structure')) == false) {
		add_shortcode('haku', 'haku_search_results');
	}
}

//
// Custom admin notices.
//
add_action('admin_notices', 'haku_admin_notices_action');
function haku_admin_notices_action() {
	$slug = apply_filters('haku_serp_slug', HAKU_SERP_SLUG);
	$page = get_page_by_path($slug);
	if (empty($page) == false) {
		if (substr_count($page->post_content, '[haku]') == 0) {
			$page = null;
		}
	}
	if (empty($page) == true) {
		echo sprintf('<div class="notice notice-warning is-dismissible"><p>%s</p></div>', sprintf(__('Haku only works with a search results page. Please create a page called "%s" and add %s as the shortcode.'), ucfirst($slug), '<code>[haku]</code>'));
	}
}

?>
