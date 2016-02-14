<?php

namespace Cohort\RSS;


class Rss20 extends Parse {
	public function __construct($xml, $debug = false) {
		parent::__construct($debug);
		$this->xml = $xml;
		$this->title = $this->xml->channel->title;
		$this->num_items = count($this->xml->channel->item);
	}
	
	protected function get_item($num) {
		$item = $this->xml->channel->item[$num];
		
		$categories = [];
		if (count($item->category) > 0) {
			foreach ($item->category as $cat) {
				$categories[] = (string) $cat;
			}
		}
		
		$author = "";
		$dc = $item->children('http://purl.org/dc/elements/1.1/');
		if ($dc->creator) $author = (string) $dc->creator;
		
		$img_type = false;
		$img_url = false;
		if ($item->enclosure) {
			foreach ($item->enclosure->attributes() as $k => $v) {
				switch ($k) {
					case "url": $img_url = (string)$v; break;
					case "type": $img_type = (string)$v; break;
				}
			}
			if (!in_array($img_type, [ "image/jpeg", "image/png", "image/gif" ])) {
				$img_url = false;
			}
		}
		
		return [
			"title" => (string) $item->title,
			"ts" => strtotime($item->pubDate),
			"url" => $this->normalize_link((string) $item->link),
			"summary" => (string) $item->description,
			"author" => $author,
			"categories" => $categories,
			"image" => $img_url,
		];
	}
}

