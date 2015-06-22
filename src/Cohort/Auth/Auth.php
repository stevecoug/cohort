<?

namespace Cohort\Auth;

class Auth {
	protected $userid;
	protected $username;
	protected $info = [];
	protected $perms = [];
	protected $messages = [];
	protected $is_valid = false;
	protected $expires = 0;
	protected $hash = false;
	protected $config;
	protected $db = false;
	
	protected $UPDATE_FIELDS = [ "password" ];
	
	//****************** METHODS THAT CAN BE OVERRIDDEN *********************//
	protected function get_userid($username) {
		$userid = $this->db->fquery("SELECT userid FROM users WHERE username=?", $username);
		return $userid;
	}
	
	protected function get_auth_info($username = false) {
		if ($username) {
			$row = $this->db->squery("SELECT userid, password FROM users WHERE username=?", $username);
		} else {
			$row = $this->db->squery("SELECT userid, password FROM users WHERE userid=?", $this->userid);
		}
		if (!$row) return false;
		return $row;
	}
	
	public function initialize($userid) {
		$this->reset();
		
		$row = $this->db->squery("SELECT * FROM users WHERE userid=?", $userid);
		if (!$row) return false;
		
		$this->userid = intval($row['userid']);
		$this->username = $row['username'];
		$this->info = $row;
		
		$this->perms = [ "user" => true ];
		
		$this->is_valid = true;
		
		$this->reset_expires();
		return true;
	}
	
	public function exists($username) {
		$userid = $this->get_userid($username);
		if (empty($userid)) return false;
		return true;
	}
	
	public function create($info) {
		// This function assumes that sanity checking has already occurred
		$userid = $this->db->query("INSERT INTO users (username, password, created_at, updated_at) VALUES (?, ?, NOW(), NOW())", $info['username'], password_hash($info['password'], PASSWORD_DEFAULT));
		
		return $userid;
	}
	
	public function update($info, $username = false) {
		if ($username === false) {
			$userid = $this->userid;
		} else {
			$userid = $this->get_userid($username);
		}
		
		$fields = [];
		$values = [];
		
		foreach ($this->UPDATE_FIELDS as $field) {
			if (isset($info[$field])) {
				$fields[] = "$field=?";
				if ($field === "password") {
					if (!is_string($info['password'])) return false;
					$values[] = password_hash($info['password'], PASSWORD_DEFAULT);
				} else {
					$values[] = $info[$field];
				}
			}
		}
		
		if (count($fields) == 0) { return false; }
		$values[] = $userid;
		
		$success = $this->db->query("UPDATE users SET ".implode(", ", $fields).", updated_at=NOW() WHERE userid=?", $values);
		if ($success) {
			foreach ($this->UPDATE_FIELDS as $field) {
				if (isset($info[$field]) && $field !== "password") {
					$this->info[$field] = $info[$field];
				}
			}
		}
		return $success;
	}
	
	public function verify_password_strength($password, $password_verify) {
		if ($password !== $password_verify) throw new \Exception("Passwords don't match");
		if (strlen($password) < 8) throw new \Exception("Password must be at least 8 characters long");
		if (!preg_match('/[a-z]/i', $password)) throw new \Exception("Password must contain at least one letter");
		if (!preg_match('/[0-9]/', $password)) throw new \Exception("Password must contain at least one number");
		
		return true;
	}
	
	//****************** METHODS THAT SHOULD NOT BE OVERRIDDEN *********************//
	public function __construct(Config $config, \Cohort\MySQLi\Connection $db) {
		$this->config = $config;
		$this->db = $db;
		$this->reset();
	}
	public function __get($key) {
		if ($key === "hash") return $this->hash;
		if (isset($this->info[$key])) return $this->info[$key];
		return false;
	}
	
	public function setDatabase(\Cohort\MySQLi\Connection $db) {
		$this->db = $db;
	}
	public function setConfig(Config $config) {
		$this->config = $config;
	}
	
	public function good() {
		if ($this->is_valid && !$this->expired()) return true;
		
		if (isset($_COOKIE['autologin'])) {
			if ($this->authenticate_cookie($_COOKIE['autologin'])) return true;
			setcookie("autologin", "", $_SERVER['REQUEST_TIME'] - 3600, "/");
		}
		
		if ($this->is_valid) {
			$this->logout();
			$this->message("Your session has been logged out due to inactivity");
		}
		
		return false;
	}
	
	public function has_perms() {
		if ($this->perms['admin']) return true;
		
		$arr = func_get_args();
		if (is_array($arr[0])) $arr = $arr[0];
		foreach ($arr as $perm) {
			if ($this->perms[$perm]) return true;
		}
		return false;
	}
	
	public function reset() {
		$this->userid = null;
		$this->username = null;
		$this->info = [];
		$this->perms = [];
		$this->is_valid = false;
		$this->expires = 0;
		$this->hash = md5(microtime() . mt_rand() . getmypid() . mt_rand());
	}
	
	public function authenticate($username, $password) {
		if (!$password) return false;
		
		$info = $this->get_auth_info($username);
		if (!$info) return false;
		if (!password_verify($password, $info['password'])) return false;
			
		return $this->initialize($info['userid']);
	}
	
	public function update_password($old_password, $new_password) {
		$info = $this->get_auth_info();
		if (!$info) throw new \Exception("Could not retrieve current password");
		if (!password_verify($old_password, $info['password'])) throw new \Exception("Old password is incorrect");
		
		if (!$this->db->query("UPDATE users SET password=? WHERE userid=?", password_hash($new_password, PASSWORD_DEFAULT), $this->userid)) {
			throw new \Exception("Failed updating password");
		}
		return true;
	}
	
	public function reset_expires() {
		$this->expires = $_SERVER['REQUEST_TIME'] + $this->config->login_expire;
	}
	
	public function expired() {
		if ($this->expires === 0) return false;
		return ($this->expires < $_SERVER['REQUEST_TIME']);
	}
	
	public function message($msg, $type = "warning") {
		$this->messages[] = [ $type, $msg ];
	}
	
	public function get_messages() {
		$ret = $this->messages;
		$this->messages = [];
		return $ret;
	}
	
	public function has_messages() {
		return count($this->messages);
	}
	
	public function logout() {
		$this->reset();
		foreach ($_SESSION as $key => $val) unset($_SESSION[$key]);
		$_SESSION['AUTH'] =& $GLOBALS['AUTH'];
	}
	
	public function require_perms() {
		$req = func_get_args();
		if (count($req) == 0) $req[] = "user";
		
		$redir_url = $this->login_url($_SERVER['REQUEST_URI']);
		
		if (!$this->good()) {
			$this->message("You need to log in before viewing this page.");
			header("Location: $redir_url");
			exit();
		}
		
		if (!$this->has_perms($req)) {
			$this->logout();
			$this->message("This account has insufficient permissions to view this page.");
			header("Location: $redir_url");
			exit();
		}
		
		return true;
	}
	
	public function get_reset_key($username) {
		$userid = $this->get_userid($username);
		
		if (!$userid) return false;
		
		$reset_key = $this->db->fquery("SELECT reset_key FROM user_reset where userid=?", $userid);
		if ($reset_key == false) {
			$reset_key = md5($username.uniqid("fuz").microtime().mt_rand().getmypid().mt_rand());
			$this->db->query("INSERT INTO user_reset (userid, reset_key, created_at, updated_at) VALUES (?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE reset_key=VALUES(reset_key), updated_at=NOW()", $userid, $reset_key);
		}
		
		return $reset_key;
	}
	
	public function check_reset_key($username, $reset_key) {
		$actual_key = $this->get_reset_key($username);
		return ($reset_key === $actual_key);
	}
	
	public function delete_reset_key($username) {
		$userid = $this->get_userid($username);
		if (!$userid) return false;
		return $this->db->query("DELETE FROM user_reset WHERE userid=?", $userid);
	}
	
	public function login_url($redir = "/") {
		return sprintf($this->config->login_url, urlencode($redir));
	}
	
	public function authenticate_cookie($cookie) {
		$good = $this->good = false;
		
		list($username, $ts, $cookie_hash) = explode("|", $cookie, 3);
		if ($ts + $this->config->cookie_expire < $_SERVER['REQUEST_TIME']) return false;
		if ($ts > $_SERVER['REQUEST_TIME']) return false;
		
		$valid_cookie = $this->get_auth_cookie($username, $ts);
		if ($valid_cookie === false) return false;
		if ($valid_cookie !== $cookie) return false;
		
		$userid = $this->get_userid($username);
		return $this->initialize($userid);
	}
	
	public function get_auth_cookie($username = false, $ts = false) {
		if ($ts === false) {
			$username = $this->username;
			$ts = $_SERVER['REQUEST_TIME'];
		} else {
			$ts = intval($ts);
		}
		
		$info = $this->get_auth_info($username);
		if (!$info) return false;
		$userid = $info['userid'];
		$pw_hash = $info['password'];
		
		$cookie_hash = hash_hmac("sha256", "$username|$ts", "$userid|$ts|$pw_hash|".$this->config->cookie_nonce);
		
		$cookie = "$username|$ts|$cookie_hash";
		
		return $cookie;
	}
}



?>
