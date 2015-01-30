<?

namespace Cohort\MySQLi;

class Connection {
	protected $config;
	
	protected $link = false;
	
	public $Errno	  = 0;
	public $Error	  = '';
	
	public function __construct(Config $config) {
		$this->config = $config;
		
		$this->connect();
	}
	
	public function check_connection() {
		if (!@$this->link->ping()) {
			$this->link->close();
			$this->connect();
		}
	}
	
	private function connect() {
		$this->link = @mysqli_connect($this->config->host, $this->config->username, $this->config->password, $this->config->schema);
		if (!$this->link) $this->halt("Could not connect to database");
		$this->link->set_charset('utf8mb4');
	}
	
	public function query($sql) {
		$args = array_slice(func_get_args(), 1);
		if (count($args) > 0 && is_array($args[0])) $args = $args[0];
		$types = "";
		$bind_params = array();
		$blobs = array();
		foreach ($args as $i => &$arg) {
			if (is_int($arg)) {
				$types .= "i";
			} elseif (is_bool($arg)) {
				$types .= "s";
			} elseif (is_float($arg)) {
				$types .= "d";
			} elseif (strlen($arg) > 65535) {
				$types .= "b";
				$blobs[$i] = $arg;
				$arg = NULL;
			} else {
				$types .= "s";
			}
			$bind_params[] = &$arg;
		}
		
		if (count($bind_params) > 0) {
			// put param types as first argument
			array_unshift($bind_params, $types);
		}
		
		$querystarttime = microtime(true);
		
		$stmt = $this->link->stmt_init();
		
		if (!@$stmt->prepare($sql)) {
			@$stmt->close();
			$this->check_connection();
			$stmt = $this->link->stmt_init();
			if (!$stmt->prepare($sql)) {
				trigger_error("MySQLi ERROR #$stmt->errno: $stmt->erro -- Could not prepare statement: $sql");
			}
		}
		
		if ($stmt->param_count) {
			call_user_func_array(array($stmt, "bind_param"), $bind_params);
			
			if (count($blobs) > 0) {
				foreach ($blobs as $arg_num => $blob) {
					$blob_length = strlen($blob);
					for ($byte = 0; $byte < $blob_length; $byte += 8192) {
						$stmt->send_long_data($arg_num, substr($blob, $byte, 8192));
					}
				}
			}
		}
		
		$result = $stmt->execute();
		
		if (!$result) {
			$this->halt("query(): Invalid SQL: ".$sql);
			return false;
		}
		
		if ($meta = $stmt->result_metadata()) {
			$stmt->store_result();
			$num_rows = $stmt->num_rows();
			if ($num_rows == 0) {
				$stmt->free_result();
				$stmt->close();
				return new NullResult();
			} else {
				$result = new Result($stmt, $meta);
			}
		} else if ($stmt->insert_id) {
			$result = $stmt->insert_id;
			$stmt->close();
		} else {
			$result = $stmt->affected_rows;
			if ($result < 0) $result = false;
			$stmt->close();
		}
		
		if(isset($GLOBALS["PAGE_QUERIES"])) {
			$GLOBALS["PAGE_QUERIES"][] = array(
				'host' => $this->config->host,
				'db' => $this->config->schema,
				'time' => round((microtime(true)-$querystarttime),4) . ' sec. ',
				'query' => $sql,
			);
		}
		
		return $result;
	}
	
	public function squery($sql) {
		$args = array_slice(func_get_args(), 1);
		if (count($args) > 0 && is_array($args[0])) $args = $args[0];
		return $this->squery_arr($sql, $args);
	}
	
	protected function squery_arr($sql, $args) {
		$result = $this->query($sql, $args);
		if (!$result) return false;
		$row = $result->fetch();
		return $row;
	}
	
	public function fquery($sql) {
		$args = array_slice(func_get_args(), 1);
		if (count($args) > 0 && is_array($args[0])) $args = $args[0];
		$row = $this->squery_arr($sql, $args);
		if ($row === false) return false;
		$row = array_values($row);
		return $row[0];
	}
	
	public function aquery($sql) {
		$args = array_slice(func_get_args(), 1);
		if (count($args) > 0 && is_array($args[0])) $args = $args[0];
		$result = $this->query($sql, $args);
		if (!$result) return false;
		
		$rows = array();
		while ($row = $result->fetch()) $rows[] = $row;
		return $rows;
	}
	
	public function afquery($sql) {
		$args = array_slice(func_get_args(), 1);
		if (count($args) > 0 && is_array($args[0])) $args = $args[0];
		$result = $this->query($sql, $args);
		if (!$result) return false;
		
		$values = array();
		while ($row = $result->fetch()) $values[] = array_values($row)[0];
		return $values;
	}
	
	protected function halt($msg, $use_msg = false) {
		if ($use_msg) {
			trigger_error($msg, E_USER_ERROR);
		} else {
			trigger_error("MySQL Database Error: " . $_SERVER["SCRIPT_FILENAME"] . "::".$this->link->errno.":".$this->link->error, E_USER_ERROR);
		}
		exit();
	}
}
