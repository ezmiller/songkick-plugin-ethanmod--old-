<?php

class SongkickEvents {
	public $apikey;
	public $upcoming_events = array();

	function SongkickEvents($apikey) {
		$this->apikey = $apikey;
		$this->apiurl = 'http://api.songkick.com/api/3.0';
	}

	function get_upcoming_events($per_page=10, $page=1, $sorder='ascending') {
		$cached_results = $this->get_cached_calendar_results($this->calendar_url($per_page, $page));
		if ($this->calendar_cache_expired($cached_results)) {
			try {
				$json_results = $this->get_uncached_upcoming_events($per_page);
			} catch (Exception $e) { throw $e; }
			$cached_results = array( 'events' => $json_results->resultsPage->results->event,
						 'totalEntries' => $json_results->resultsPage->totalEntries,
						 'perPage' => $json_results->resultsPage->perPage,
						 'numberOfPages' => ceil( ($json_results->resultsPage->totalEntries / $json_results->resultsPage->perPage) ),
						 'cachedPage' => $page,
						 'timestamp' => time() );
			$this->set_cached_calendar_results($this->calendar_url($per_page, $page), $cached_results);
			$events = $json_results->resultsPage->results->event;
		} else {
			$events = $cached_results['events'];
		}
		if ( $sorder == 'descending' ) {
			usort($events, array($this,'compare_event_dates'));
		}
		return $events;
	}

	function get_past_events($per_page=10, $page=1, $sorder='ascending') {
		$cached_results = $this->get_cached_gigography_results($this->gigography_url($per_page, $page));
		if ($this->gigography_cache_expired($cached_results)) {
			try {
				$json_results = $this->get_uncached_gigography_events($per_page, $page);
			} catch (Exception $e) { throw $e; }
			$cached_results = array( 'events' => $json_results->resultsPage->results->event,
						 'totalEntries' => $json_results->resultsPage->totalEntries,
						 'perPage' => $json_results->resultsPage->perPage,
						 'numberOfPages' => ceil( ($json_results->resultsPage->totalEntries / $json_results->resultsPage->perPage) ),
						 'cachedPage' => $page,
						 'timestamp' => time() ); 
			$this->set_cached_gigography_results($this->gigography_url($per_page, $page), $cached_results);
			$events = $json_results->resultsPage->results->event;
		} else { 
			$events = $cached_results['events'];
		}
		if ( $sorder == 'descending' ) {
			usort($events, array($this,'compare_event_dates'));
		}
		return $events;
	}

	function get_number_of_pages($call_type='past_events', $per_page, $page=1) {
		if ( $call_type == 'past_events' ) {
			try {
				$json_results = $this->get_uncached_gigography_events($per_page, $page);
			} catch (Exception $e) { throw $e; }
			return ceil( ($json_results->resultsPage->totalEntries / $json_results->resultsPage->perPage) );
		}
		elseif ( $call_type == 'upcoming_events' ) {
			try {
			 	$json_results = $this->get_uncached_gigography_events($per_page, $page);
			} catch (Exception $e) { throw $e; }
			return ceil( ($json_results->resultsPage->totalEntries / $json_results->resultsPage->perPage) );
		}
		else { return 0; }
	}

	protected function get_cached_gigography_results($key) {
		$all_cache = get_option(SONGKICK_GIGOGRAPHY_CACHE);
		return $all_cache[$key];
	}	
	
	protected function get_cached_calendar_results($key) {
		$all_cache = get_option(SONGKICK_CALENDAR_CACHE);
		return $all_cache[$key];
	}

	protected function get_uncached_upcoming_events($per_page) {
		try {
			$response = $this->fetch_upcoming_events($this->calendar_url($per_page));
		} catch (Exception $e) {
			throw $e;
		}
		return $this->events_from_json($response);
	}

	protected function get_uncached_gigography_events($per_page, $page=1) {
		try {
			$response = $this->fetch_past_events($this->gigography_url($per_page, $page));
		} catch (Exception $e) {
			throw $e;
		}
		return $this->events_from_json($response);
	}

	protected function set_cached_calendar_results($key, $value) {
		$all_cache = get_option(SONGKICK_CALENDAR_CACHE);
		if (!$all_cache) {
			$all_cache = array();
		}
		$all_cache[$key] = $value;
		update_option(SONGKICK_CALENDAR_CACHE, $all_cache);
	}
	
	protected function set_cached_gigography_results($key, $value) {
		$all_cache = get_option(SONGKICK_GIGOGRAPHY_CACHE);
		if (!$all_cache) {
			$all_cache = array();
		}
		$all_cache[$key] = $value;
		update_option(SONGKICK_GIGOGRAPHY_CACHE, $all_cache);
	}
	protected function calendar_cache_expired($cached_results) {
		if (!$cached_results || $cached_results == null) return true;
		return (bool) ((time() - $cached_results['timestamp'] ) > SONGKICK_REFRESH_CALENDAR_CACHE);
	}

	protected function gigography_cache_expired($cached_results) {
		if (!$cached_results || $cached_results == null) return true;
		return (bool) ((time() - $cached_results['timestamp'] ) > SONGKICK_REFRESH_GIGOGRAPHY_CACHE);
	}

	protected function fetch_upcoming_events($url) {
		$http     = new WP_Http;
		$response =  $http->request($url);
		if (is_wp_error($response)) {
			$msg = "The WP_Http object threw a WP_Error object while fetching upcoming events from songkick.";
			$msg .= "Wp_Error message: ".$response->get_error_message().".";
			throw new Exception($msg);
	        }
		elseif ($response['response']['code'] != 200) {
			throw new Exception('The WP_Http object returned error code '.$response['response']['code'].' while fetching upcoming events from songkick.');
		}
		return $response['body'];
	}

	protected function fetch_past_events($url) {
		$http 		= new WP_Http;
		$response	= $http->request($url);
		if (is_wp_error($response)) {
			$msg = "The WP_Http object threw a WP_Error object while fetching upcoming events from songkick.";
			$msg .= "Wp_Error message: ".$response->get_error_message().".";
			throw new Exception($msg);	  
		}
		elseif ($response['response']['code'] != 200) {
			throw new Exception('The WP_Http object returned error code '.$response['response']['code'].' while fetching upcoming events from songkick.');
		}
		return $response['body'];
	}

	protected function events_from_json($json) {
		$json_docs = json_decode($json);
		if ($json_docs->totalEntries === 0) {
			return array();
		} else {
			return $json_docs;
		}
	}

	function compare_event_dates($a, $b) {
		$date1 = $a->start->date;
		$date2 = $b->start->date;
		if ( $date1 == $date2 ) { return 0; }
		return ( $date1 > $date2 ) ? -1 : 1;
	}
}

?>
