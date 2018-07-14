<?php


namespace Cohort\RSS;

abstract class Parse implements \Iterator {
	protected $debug = false;
	protected $xml = false;
	protected $url = false;
	protected $title = false;
	protected $num_items = 0;
	private $curr = 0;
	
	public function __construct($debug) {
		$this->debug = $debug;
	}
	
	public function rewind() { $this->curr = 0; }
	public function current() { return $this->get_item($this->curr); }
	public function key() { return $this->curr; }
	public function next() { $this->curr++; }
	public function valid() { return ($this->curr >= 0 && $this->curr < $this->num_items); }
	
	public function get_next_item() {
		$item = false;
		while ($this->curr < $this->num_items) {
			$item = $this->get_item($this->curr);
			$this->curr++;
			if ($item !== false) return $item;
		}
		return false;
	}
	
	public function set_url($url) {
		$this->url = $url;
	}
	
	static public function get_parser($data, $url = false, $debug = false) {
		libxml_use_internal_errors(true);
		$data = trim($data);
		$xml = @simplexml_load_string($data, null, LIBXML_DTDLOAD);
		if (!$xml) {
			if ($debug) {
				echo "******** RSS PARSING ERROR: XML could not be loaded ********\n";
				foreach (libxml_get_errors() as $error) {
					printf("Error %d on line %d, column %d, level %d\n  %s", $error->code, $error->line, $error->column, $error->level, $error->message);
				}
			}
			throw new \Exception("RSS parsing error: XML could not be loaded");
			return false;
		}
		
		if ($xml->getName() == "feed") {
			$rss = new Atom($xml, $debug);
			$rss->set_url($url);
			return $rss;
		}
		if ($xml->getName() == "rss") {
			if ($xml['version'] == "2.0") {
				$rss = new Rss20($xml, $debug);
				$rss->set_url($url);
				return $rss;
			}
		}
		if ($debug) printf("******** RSS PARSING ERROR: %s/%s ********\n", $xml->getName(), $xml['version']);
		throw new \Exception("RSS parsing error: ".$xml->getName()."/".$xml['version']);
	}
	
	protected function normalize_link($link) {
		static $domain = false;
		static $path = false;
		
		$link = trim($link);
		
		if (substr($link, 0, 4) === "http") return $link;
		
		if (!$domain) {
			if (!preg_match('#^(https?://[a-z0-9_.-]+)(/.*)?(/[^/]*)$#', $this->url, $regs)) {
				if ($this->debug) echo "Invalid RSS feed URL - can't parse for normalizing\n$this->url\n";
				exit(1);
			}
			$domain = $regs[1];
			$path = $regs[2] . "/";
		}
		
		if ($link[0] === "/") {
			$link = "$domain$link";
		} else {
			$link = "$domain$path$link";
		}
		return $link;
	}
}


