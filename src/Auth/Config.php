<?

namespace Cohort\Auth;

class Config extends \Cohort\Config {
	protected $properties = [
		// Make sure you overwrite the cookie nonce, as this one is in a public repository
		"cookie_nonce" => "MDo19W39789mE3c76a6aU4vqR5di446h3M28fGtoOi7706c0qLhQ3NlNTevhILX1",
		// Default login expiration is 1 hour
		"login_expire" => 3600,
		// Default "remember me" cookie expiration is 14 days
		"cookie_expire" => 1209600,
		"login_url" => "login.php?url=%s",
	];
}
