<?

namespace Cohort\Templates;

class Config extends \Cohort\Config {
	protected $properties = [
		"template_path" => ".",
		"js_path" => "/js",
		"css_path" => "/css",
		"site_name" => false,
		"header" => false,
		"footer" => false,
	];
}
