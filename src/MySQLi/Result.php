<?

namespace Cohort\MySQLi;

class Result {
	private $stmt = false;
	private $record;

	public function __construct($stmt, $meta) {
		$this->stmt = $stmt;
		$this->record = array();
		$bind_args = array();
		while ($field = $meta->fetch_field()) {
			$this->record[$field->name] = null;
			$bind_args[] = &$this->record[$field->name];
		}
		call_user_func_array(array($stmt, 'bind_result'), $bind_args);
	}
	
	public function fetch() {
		if (!$this->stmt) trigger_error("Result::fetch() called, but result has been cleared already", E_USER_ERROR);
		if (!$this->stmt->fetch()) {
			return false;
		}
		
		$row = array();
		foreach ($this->record as $key => $val) {
			$row[$key] = $val;
		}
	
		return $row;
	}
	
	public function insert_id() {
		if (!$this->stmt) trigger_error("Result::insert_id() called, but result has been cleared already", E_USER_ERROR);
		return $this->stmt->insert_id;
	}
	
	public function free() {
		if (!$this->stmt) return false;
		//$this->stmt->free_result();
		$this->stmt->close();
		$this->stmt = false;
		return true;
	}
	
	public function __destruct() {
		$this->free();
	}
}
