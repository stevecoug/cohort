<?

namespace Cohort;

class Config {
	protected $properties = [];
	
	public function __construct($properties = false) {
		if (!is_array($properties)) return;
		foreach ($properties as $key => $val) {
			$this->__set($key, $val);
		}
	}
	
	public function __get($key) {
		if (!isset($this->properties[$key])) return false;
		return $this->properties[$key];
	}
	public function __set($key, $val) {
		if (!isset($this->properties[$key])) {
			throw new \Exception("Invalid property: $key");
		}
		$this->properties[$key] = $val;
	}
}
