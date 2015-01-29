<?

namespace Cohort\MySQLi;

class NullResult {
	public function __construct() {}
	public function fetch() { return false; }
	public function free() {}
}

