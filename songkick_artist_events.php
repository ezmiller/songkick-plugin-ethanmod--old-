<?php
require_once dirname(__FILE__) . '/songkick_events.php';

class SongkickArtistEvents extends SongkickEvents {
	public $id;
	public $apikey;

	function SongkickArtistEvents($apikey, $id) {
		$this->SongkickEvents($apikey);
		$this->id = $id;
	}

	function profile_url() {
		return "http://www.songkick.com/artists/$this->id";
	}

	protected function calendar_url($per_page, $page=1){
		return "$this->apiurl/artists/$this->id/calendar.json?apikey=$this->apikey&per_page=$per_page&page=$page";
	}

	protected function gigography_url($per_page, $page=1){
		return "$this->apiurl/artists/$this->id/gigography.json?apikey=$this->apikey&per_page=$per_page&page=$page";
	}
}
?>
