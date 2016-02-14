<?php

namespace Cohort\RSS;


class Atom extends Parse {
	public function __construct($xml, $debug = false) {
		parent::__construct($debug);
		$this->xml = $xml;
		$this->title = $this->xml->title;
		$this->num_items = count($this->xml->entry);
	}
	
	protected function get_item($num) {
		$item = $this->xml->entry[$num];
		$url = false;
		foreach ($item->link as $link) {
			if ($link['rel'] == "alternate") $url = $link['href'];
		}
		
		if (!$url) {
			return false;
		}
		
		$categories = [];
		if (count($item->category) > 0) {
			foreach ($item->category as $cat) {
				$categories[] = (string) $cat;
			}
		}
		
		$author = "";
		$dc = $item->children('http://purl.org/dc/elements/1.1/');
		if ($dc->creator) $author = (string) $dc->creator;
		
		return array(
			"title" => (string) $item->title,
			"ts" => strtotime($item->published),
			"url" => $this->normalize_link((string) $url),
			"summary" => (string) $item->content,
			"author" => $author,
			"categories" => $categories,
		);
	}
}

