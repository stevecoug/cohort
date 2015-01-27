<?

namespace Cohort\Http;

class Response {
	private $url;
	private $response;
	private $requestInfo;
	private $responseInfo;
	
	private $responseHeaders = false;
	private $responseData = false;
	private $responseHeaderArray = false;
	private $responseCookies = false;
	
	public function __construct($url, $response, $request_info, $response_info) {
		$this->url = $url;
		$this->response = $response;
		$this->requestInfo = $request_info;
		$this->responseInfo = $response_info;
	}
	
	public function getUrl() { return $this->url; }
	public function getResponse() { return $this->response; }
	public function getRequestInfo() { return $this->requestInfo; }
	public function getResponseInfo() { return $this->responseInfo; }
	
	private function parseResponse() {
		list($this->responseHeaders, $this->responseData) = explode("\r\n\r\n", $this->response, 2);
	}
	
	public function getResponseHeaders() {
		if ($this->responseHeaders === false) $this->parseResponse();
		return $this->responseHeaders;
	}
	public function getResponseData() {
		if ($this->responseData === false) $this->parseResponse();
		return $this->responseData;
	}
	
	private function parseResponseHeaders() {
		$headers = $this->getResponseHeaders();
		$headers = str_replace("\r\n", "\n", $headers);
		$headers = str_replace("\r", "\n", $headers);
		
		$this->responseHeaderArray = [];
		foreach (explode("\n", $headers) as $line) {
			if (!strpos($line, ":")) {
				$this->responseHeaderArray['HTTP'] = trim($line);
				continue;
			}
			
			list($key, $val) = explode(":", $line, 2);
			$key = strtolower(trim($key));
			$val = trim($val);
			if (isset($this->responseHeaderArray[$key])) {
				if (!is_array($this->responseHeaderArray[$key])) {
					$tmp = $this->responseHeaderArray[$key];
					$this->responseHeaderArray[$key] = [ $tmp ];
				}
				$this->responseHeaderArray[$key][] = $val;
			} else {
				$this->responseHeaderArray[$key] = $val;
			}
		}
	}
	public function getResponseHeaderArray() {
		if ($this->responseHeaderArray === false) $this->parseResponseHeaders();
		return $this->responseHeaderArray;
	}
	public function getResponseHeader($key) {
		$key = strtolower($key);
		if ($this->responseHeaderArray === false) $this->parseResponseHeaders();
		if (isset($this->responseHeaderArray[$key])) return $this->responseHeaderArray[$key];
		return false;
	}
	
	private function parseResponseCookies() {
		$this->responseCookies = [];
		$cookie_lines = $this->getResponseHeader("Set-Cookie");
		if (!$cookie_lines) return;
		if (!is_array($cookie_lines)) $cookie_lines = [ $cookie_lines ];
		
		foreach ($cookie_lines as $cookie_line) {
			$cookie = [];
			$cookie_attrs = explode(";", $cookie_line);
			
			list($cookie_name, $cookie_value) = explode("=", $cookie_attrs[0], 2);
			$cookie['value'] = urldecode($cookie_value);
			
			for ($i = 1; $i < count($cookie_attrs); $i++) {
				$attr = trim($cookie_attrs[$i]);
				if (strpos($attr, "=")) {
					list($key, $val) = explode("=", $attr, 2);
				} else {
					$key = $attr;
					$val = true;
				}
				$key = strtolower($key);
				
				$cookie[$key] = $val;
			}
			$this->responseCookies[$cookie_name] = $cookie;
		}
	}
	public function getResponseCookies() {
		if ($this->responseCookies === false) $this->parseResponseCookies();
		return $this->responseCookies;
	}
	public function getResponseCookie($key) {
		if ($this->responseCookies === false) $this->parseResponseCookies();
		if (isset($this->responseCookies[$key])) return $this->responseCookies[$key];
		return false;
	}
}
