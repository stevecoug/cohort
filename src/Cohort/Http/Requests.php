<?

namespace Cohort\Http;

class Requests {
	private $requests = [];
	
	public function __construct() {}
	
	public static function SingleRequest($url, $data = false, $type = "GET", $extra_options = false) {
		$response = false;
		if ($type === "GET" && $data !== false) {
			$query = http_build_query($data);
			if (strpos($url, "?")) {
				$url = "$url&$query";
			} else {
				$url = "$url?$query";
			}
			$data = false;
		}
		
		$options = [
			'url' => $url,
			"post_data" => $data,
			"callback" => function(Response $result) use (&$response) {
				$response = $result;
			},
		];
		if (is_array($extra_options)) {
			foreach ($extra_options as $key => $val) {
				$options[$key] = $val;
			}
		}
		
		$http = new Requests();
		$http->addRequest($options, false);
		$http->execute();
		
		return $response;
	}
	public static function SingleGet($url, $extra_options = false) {
		return static::SingleRequest($url, false, "GET", $extra_options);
	}
	public static function SinglePost($url, $data, $extra_options = false) {
		return static::SingleRequest($url, $data, "POST", $extra_options);
	}
	
	public function numRequests() {
		return count($this->requests);
	}
	
	public function addRequest($arr, $errors_ok = true) {
		$num = $this->numRequests();
		$req = [
			"url" => $arr['url'],
			"result" => false,
			"errors_ok" => $errors_ok,
		];
		
		foreach ([ "callback", "callback_info", "post_data", "cookies", "user_agent", "headers", "referer", "errors_ok", "timeout" ] as $key) {
			$req[$key] = (isset($arr[$key]) ? $arr[$key] : false);
		}
		
		$this->requests[$num] = $req;
		return $num;
	}
	
	public function execute() {
		$mh = curl_multi_init();
		$ch = [];
		
		$base_curl_opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_MAXREDIRS => 2,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 3,
			CURLOPT_TIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SAFE_UPLOAD => true,
			CURLOPT_HEADER => true,
		];
		
		// Create requests for each URL, including all additional information
		foreach ($this->requests as $num => $req) {
			$ch[$num] = curl_init($req['url']);
			
			$curl_opts = $base_curl_opts;
			if (!empty($req['post_data'])) {
				$curl_opts[CURLOPT_POST] = true;
				$curl_opts[CURLOPT_POSTFIELDS] = $req['post_data'];
			}
			if (!empty($req['cookies'])) {
				if (is_array($req['cookies'])) {
					$tmp = [];
					foreach ($req['cookies'] as $k => $v) {
						$tmp[] = sprintf("%s=%s", $k, $v);
					}
					$req['cookies'] = implode("; ", $tmp);
				}
				$curl_opts[CURLOPT_COOKIE] = $req['cookies'];
			}
			if (!empty($req['user_agent'])) $curl_opts[CURLOPT_USERAGENT] = $req['user_agent'];
			if (!empty($req['headers'])) $curl_opts[CURLOPT_HTTPHEADER] = $req['headers'];
			if (!empty($req['referer'])) $curl_opts[CURLOPT_REFERER] = $req['referer'];
			if (!empty($req['timeout'])) {
				$curl_opts[CURLOPT_CONNECTTIMEOUT] = intval($req['timeout']);
				$curl_opts[CURLOPT_TIMEOUT] = intval($req['timeout']);
			}
			
			if (substr($req['url'], 0, 6) === "https:") {
				$curl_opts[CURLOPT_SSLVERSION] = 6; //CURL_SSLVERSION_TLSv1_2
				//$curl_opts[CURLOPT_SSL_CIPHER_LIST] = "TLSv1";
			}
			
			curl_setopt_array($ch[$num], $curl_opts);
			
			curl_multi_add_handle($mh, $ch[$num]);
		}
		
		// Start performing the request
		do {
				$exec_return_value = @curl_multi_exec($mh, $running_handles);
		} while ($exec_return_value == CURLM_CALL_MULTI_PERFORM);
		
		// Loop and continue processing the request
		while ($running_handles && $exec_return_value == CURLM_OK) {
			// Wait forever for network
			$num_ready = @curl_multi_select($mh);
			if ($num_ready == -1) {
				usleep(100);
			}
			
			// Pull in any new data, or at least handle timeouts
			do {
				$exec_return_value = @curl_multi_exec($mh, $running_handles);
			} while ($exec_return_value == CURLM_CALL_MULTI_PERFORM);
			
			if ($num_ready != -1) {
				if ($exec_return_value !== CURLM_OK) {
					switch ($exec_return_value) {
						case CURLM_BAD_HANDLE: $error = "BAD HANDLE"; break;
						case CURLM_BAD_EASY_HANDLE: $error = "BAD EASY HANDLE"; break;
						case CURLM_OUT_OF_MEMORY: $error = "OUT OF MEMORY"; break;
						case CURLM_INTERNAL_ERROR: $error = "INTERNAL ERROR"; break;
						default: $error = $exec_return_value; break;
					}
					throw new \Exception("cURL multi-exec error: $error");
				}
			}
		}
		
		// Check for any errors
		if ($exec_return_value != CURLM_OK) {
			throw new \Exception("Curl multi read error $exec_return_value", E_USER_WARNING);
		}

		// Extract the content
		foreach($this->requests as $num => $req) {
			// Check for errors
			$curl_error = curl_error($ch[$num]);
			if($curl_error == "") {
				$this->requests[$num]['result'] = curl_multi_getcontent($ch[$num]);
				$this->requests[$num]['error'] = false;
			} else {
				$this->requests[$num]['result'] = "";
				$this->requests[$num]['error'] = $curl_error;
				if (!$this->requests[$num]['errors_ok']) {
					throw new \Exception("Curl error on handle $num: $curl_error");
				}
			}
			$this->requests[$num]['info'] = curl_getinfo($ch[$num]);
			
			// Remove and close the handle
			curl_multi_remove_handle($mh, $ch[$num]);
			curl_close($ch[$num]);
		}
		
		// Clean up the curl_multi handle
		curl_multi_close($mh);
		
		// Make the callbacks
		foreach($this->requests as $num => $req) {
			$callback = $req['callback'];
			if ($callback) {
				$resp = new Response($req['url'], $req['result'], $req['callback_info'], $req['info'], $req['error']);
				$callback($resp);
			}
		}
		
		$this->requests = [];
	}
}

