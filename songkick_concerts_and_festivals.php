<?php

/*
Plugin Name: Songkick Concerts and Festivals (eThan mod)
Plugin URI: http://github.com/saleandro/songkick-wp-plugin
Description: Plugin to show your upcoming concerts based on your Songkick profile. It can display upcoming events for a user or an artist.
For a user, simply put your username in the admin interface. For an artist, you should use the artist's Songkick id, as shown in the url for your artist page.
For example, the url "http://www.songkick.com/artists/123-your-name" has the id "123".
Version: 0.6
Author: Sabrina Leandro (+ eThan)
Author URI: http://github.com/saleandro
License: GPL3

*/

/*
    Copyright 2010 Sabrina Leandro (saleandro@yahoo.com)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if (!class_exists('WP_Http'))
	include_once(ABSPATH . WPINC . '/class-http.php');

define('SONGKICK_OPTIONS', 'songkick-concerts');
define('SONGKICK_TEXT_DOMAIN', 'songkick-concerts-and-festivals');
define('SONGKICK_I18N_ENCODING', 'UTF-8');
define('SONGKICK_CALENDAR_CACHE', 'songkick-calendar-cache');
define('SONGKICK_GIGOGRAPHY_CACHE', 'songkick-gigography-cache');
define('SONGKICK_REFRESH_CALENDAR_CACHE', 60 * 60);
define('SONGKICK_REFRESH_GIGOGRAPHY_CACHE', 60 * 60);

require_once dirname(__FILE__) . '/songkick_user_events.php';
require_once dirname(__FILE__) . '/songkick_artist_events.php';
require_once dirname(__FILE__) . '/songkick_presentable_event.php';
require_once dirname(__FILE__) . '/songkick_settings.php';

/**
 * Global Initialization of the Songkick Plugin
 */
function songkick_plugin_init() {
	// Load Plugin Text Domain for i18n
	load_plugin_textdomain(SONGKICK_TEXT_DOMAIN, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}

add_action('init', 'songkick_plugin_init');

function songkick_option($key) {
	$options = get_option(SONGKICK_OPTIONS);
	return $options[$key];
}

function powered_by_songkick($logo) {
	$text = __('Concerts by Songkick', SONGKICK_TEXT_DOMAIN);
	$html  = "<a class='powered-by' href='http://www.songkick.com/'>";
	$html .= "<img src='".WP_PLUGIN_URL."/".str_replace(basename( __FILE__),"",plugin_basename(__FILE__)).$logo."'";
	$html .= " title='".htmlentities($text, ENT_QUOTES, SONGKICK_I18N_ENCODING)."' alt='".htmlentities($text, ENT_QUOTES, SONGKICK_I18N_ENCODING)."' /></a>";
	return $html;
}

function songkick_title() {
	$default = __('Concerts', SONGKICK_TEXT_DOMAIN);
	$title   = (songkick_option('title')) ? songkick_option('title') : htmlentities($title, ENT_QUOTES, SONGKICK_I18N_ENCODING);
	return $title;
}

function songkick_events_factory($options) {
	if ($options['username']) {
		$songkick_id      = $options['username'];
		$songkick_id_type = 'user';
	} else {
		$songkick_id      = $options['songkick_id'];
		$songkick_id_type = $options['songkick_id_type'];
	}
	$apikey = $options['apikey'];
	$attendance = $options['attendance'];

	if ($songkick_id_type == 'user')
		$sk = new SongkickUserEvents($apikey, $songkick_id, $attendance);
	else
		$sk = new SongkickArtistEvents($apikey, $songkick_id);
	return $sk;
}

function songkick_display_events($events, $profile_url, $date_color, $logo) {
	$profile_title = __('See all concerts', SONGKICK_TEXT_DOMAIN);
	
	if (empty($events)) {
		echo '<p>', htmlentities(__('No events...'), ENT_QUOTES, SONGKICK_I18N_ENCODING), '</p>';
	} else {
		echo "<ul class=\"songkick-events\">";
		foreach($events as $event) {
			$presentable_event = new SongkickPresentableEvent($event);
			echo '<li>'.$presentable_event->to_html($date_color).'</li>';
		}
		echo "</ul>";
	}
	echo '<p class="profile-title"><a href="'.$profile_url.'">';
	echo htmlentities($profile_title, ENT_QUOTES, SONGKICK_I18N_ENCODING)."</a></p>";
	echo powered_by_songkick($logo);
}

function songkick_upcoming_concerts_and_festivals_shortcode_handler() {
	wp_enqueue_style('songkick_concerts', '/wp-content/plugins/songkick-concerts-and-festivals-ethanmod/songkick_concerts.css') ;
	$options          = get_option(SONGKICK_OPTIONS);
	$date_color       = $options['shortcode_date_color'];
	$number_of_events = $options['shortcode_number_of_future_events'];
	$logo             = $options['shortcode_logo'];
	$sk = songkick_events_factory($options);
	$page_to_get = 1; // get the first page
	try {
		$events = $sk->get_upcoming_events($number_of_events,$page_to_get,'ascending');
	} catch (Exception $e) {
		echo "Ooops!  The server had some trouble finding the events list.  Please refresh this page and with a bit of luck it may work.";
		$msg = 'The Following error occured on '.get_bloginfo('url').' while trying to fetch upcoming songkick events: '. $e->getMessage();
		error_log($msg, 0);   //Send message to system log.
		error_log($msg, 1, "ethanzanemiller@gmail.com");  //Send message to eThan
		die();
	}
	echo '<div class="songkick-events">';
	songkick_display_events($events, $sk->profile_url(), $date_color, $logo);
	echo '</div>';
}

function songkick_past_concerts_and_festivals_shortcode_handler() {
	wp_enqueue_style('songkick_concerts', '/wp-content/plugins/songkick-concerts-and-festivals-ethanmod/songkick_concerts.css');
	$options		= get_option(SONGKICK_OPTIONS);
	$date_color		= $options['shortcode_date_color'];
	$number_of_events	= $options['shortcode_number_of_past_events'];
	$logo			= $options['shortcode_logo'];
	$sk = songkick_events_factory($options);
	try {
		$page_to_get = $sk->get_number_of_pages('past_events',$number_of_events);  // get last page #
	} catch (Exception $e) {
		echo "Ooops!  The server had some trouble finding the events list.  Please refresh this page and with a bit of luck it may work.";
		$msg = 'The following error occured on '.get_bloginfo('url').' while trying to fetch number of pages of songkick events: '. $e->getMessage();
		error_log($msg,0);  // Send error message to systme log.
		error_log($msg, 1, "ethanzanemiller@gmail.com");  // Send error message to eThan.
		die();
	}
	try {
		$events = $sk->get_past_events($number_of_events,$page_to_get,'descending');
	} catch (Exception $e) {
		echo "Ooops!  The server had some trouble finding the events list.  Please refresh this page and with a bit of luck it may work.";
		$msg = 'The Following error occured on '.get_bloginfo('url').' while trying to fetch past songkick events: '. $e->getMessage();
		error_log($msg, 0);   //Send message to system log.
		error_log($msg, 1, "ethanzanemiller@gmail.com");  //Send message to eThan
		die();
	}
	echo '<div class="songkick-events">';
	songkick_display_events($events, $sk->profile_url(), $date_color, $logo);
	echo '</div>';
}

/**
 * Global Initialization of the Songkick Sidebar Widget
 */
function songkick_widget_init() {
	if (!function_exists('register_sidebar_widget'))
		return;

	wp_enqueue_style('songkick_concerts', '/wp-content/plugins/songkick-concerts-and-festivals-ethanmod/songkick_concerts.css') ;
	
	function songkick_widget($args) {
		extract($args);

		$options       = get_option(SONGKICK_OPTIONS);
		$hide_if_empty = $options['hide_if_empty'];
		$date_color    = $options['date_color'];
		$number_of_events = $options['number_of_events'];
		$logo             = $options['logo'];

		$sk = songkick_events_factory($options);
		$events = $sk->get_upcoming_events($number_of_events);

		if ($hide_if_empty && empty($events)) return;

		echo $before_widget;
		echo '<div class="songkick-events">';
 		echo $before_title . songkick_title() . $after_title;
		songkick_display_events($events, $sk->profile_url(), $date_color, $logo);
		echo '</div>';
		echo $after_widget;
	}

	register_sidebar_widget(array('Songkick Concerts and Festivals', 'widgets'), 'songkick_widget');
	register_widget_control(array('Songkick Concerts and Festivals', 'widgets'), 'songkick_widget_settings');
}

add_action('admin_menu', 'songkick_admin_menu');
function songkick_admin_menu() {
	add_options_page('Songkick Concerts and Festivals', 'Songkick', 'administrator', 'songkick-concerts-and-festivals', 'songkick_admin_settings');
}

add_shortcode("songkick_upcoming_concerts_and_festivals", "songkick_upcoming_concerts_and_festivals_shortcode_handler");
add_shortcode("songkick_past_concerts_and_festivals", "songkick_past_concerts_and_festivals_shortcode_handler");
add_action('widgets_init', 'songkick_widget_init');

?>
