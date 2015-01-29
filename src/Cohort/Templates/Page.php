<?

namespace Cohort\Templates;

class Page {
	private $js_files = [];
	private $css_files = [];
	private $title = "";
	private $header = "";
	private $footer = "";
	private $env = [];
	private $globals = [];
	private $config = false;
	
	public function __construct(Config $config, $env = []) {
		$this->env = $env;
		$this->config = $config;
	}
	
	public function __set($key, $val) {
		$this->env[$key] = $val;
	}
	public function __get($key) {
		if (!isset($this->env[$key])) return false;
		return $this->env[$key];
	}
	
	public function set_global($key) {
		$this->globals[] = $key;
	}
	
	private function find_template($template_file) {
		if ($template_file[0] === '/') {
			if (!file_exists($template_file)) return false;
			return $template_file;
		}
		
		if (file_exists($this->config->template_path."/$template_file")) {
			return $this->config->template_path."/$template_file";
		}
		
		return false;
	}
	
	public function display($template_file, $template_env = []) {
		$full_path = $this->find_template($template_file);
		if (!$full_path) {
			throw new \Exception("Can't find template $template_file");
		}
		
		PageHelperFunction($this->globals, $full_path);
	}
	
	public function nodisplay($template_file, $template_env = []) {
		ob_start();
		$this->display($template_file, $template_env);
		return ob_get_clean();
	}
	
	public function js($file) {
		if (isset($this->js_files[$file])) return;
		
		if (substr($file, 0, 4) !== "http" && substr($file, 0, 1) !== "/") {
			$real_file = $_SERVER['DOCUMENT_ROOT'] . $this->config->js_path . "/$file.js";
			$ts = @filemtime($real_file);
			$file_path = sprintf($this->config->js_path . "/%s.js?%d", $file, $ts);
		} else {
			$file_path = $file;
		}
		
		$this->js_files[$file] = $file_path;
	}
	
	public function css($file) {
		if (isset($this->css_files[$file])) return;
		
		if (substr($file, 0, 4) !== "http" && substr($file, 0, 1) !== "/") {
			$real_file = $_SERVER['DOCUMENT_ROOT'] . $this->config->css_path . "/$file.css";
			$ts = @filemtime($real_file);
			$file_path = sprintf($this->config->css_path . "/%s.css?%d", $file, $ts);
		} else {
			$file_path = $file;
		}
		
		$this->css_files[$file] = $file_path;
	}
	
	public function page($title, $align = false) {
		header("Content-Type: text/html; charset=UTF-8");
		
		if ($_GET['bare']) return;
		
		$this->env['title'] = $title;
		if ($this->config->site_name) $title = "$title - " . $this->config->site_name;
		$this->title = $title;
		
		if ($this->config->header) $this->header = $this->nodisplay($this->config->header);
		if ($this->config->footer) $this->footer = $this->nodisplay($this->config->footer);
		
		ob_start([$this, "display_page_callback"]);
	}
	
	public function display_page_callback($html) {
		$final_html = "<!DOCTYPE html>\n<html lang='en'>\n";
		
		$final_html .= "<head>\n";
		$final_html .= sprintf("\t<title>%s</title>\n", htmlentities($this->title));
		$final_html .= "\t<meta name='viewport' content='width=device-width, initial-scale=1.0' />\n";
		if (!empty($this->env['meta_description'])) {
			$final_html .= sprintf("\t<meta name='description' content='%s' />\n", htmlentities($this->env['meta_description']));
		}
		if (!empty($this->env['meta_keywords'])) {
			$final_html .= sprintf("\t<meta name='keywords' content='%s' />\n", htmlentities($this->env['meta_keywords']));
		}
		$final_html .= "\t<meta name='ROBOTS' content='ALL' />\n";
		foreach (array_unique($this->js_files) as $js) {
			$final_html .= "\t<script src='$js'></script>\n";
		}
		foreach (array_unique($this->css_files) as $css) {
			$final_html .= "\t<link href='$css' rel='styleSheet' type='text/css' />\n";
		}
		$final_html .= "</head>\n";
		
		$final_html .= "<body>\n";
		$final_html .= "<div id='page'>\n";
		$final_html .= $this->header;
		$final_html .= $html;
		$final_html .= $this->footer;
		$final_html .= "</div>\n";
		$final_html .= "</body>\n";
		
		$final_html .= "</html>\n";
		
		return $final_html;
	}
}

// This function needs to be outside of the class in order to protect $this
function PageHelperFunction($globals, $full_path) {
	foreach ($globals as $var) {
		global $$var;
	}
	unset($var);
	
	include $full_path;
}


?>
