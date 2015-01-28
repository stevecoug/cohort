<?

namespace Cohort;

class Config {
	protected $properties = [];
	
	public function __construct($properties) {
		foreach ($properties as $key => $val) {
			$this->properties[$key] = $val;
		}
	}
	
	public function __get($key) {
		if (!isset($this->properties[$key])) return false;
		return $this->properties[$key];
	}
	public function __set($key, $val) {
		$this->properties[$key] = $val;
	}
}
