<?php
include "APR1_MD5.php";
$config = json_decode(file_get_contents(__DIR__ . '/../auth.json'), true);
$debug = false;

set_exception_handler(function ($e) {
	error_log($e);
	die();
});

function debug_log($m) {
	global $debug;
	if ($debug) {
		error_log($m);
	}
}

function auth_required() {
	global $config;
	header('WWW-Authenticate: Basic realm="'.$config["realm"].'"');
	header('HTTP/1.0 401 Unauthorized');
	echo 'Access denied';
	exit;
}

function validate_user() {
	global $config;
	debug_log("Validating user");
	if (!isset($_SERVER['PHP_AUTH_USER'])) {
		debug_log("Missing user");
		return 401;
	}
	if (!isset($config['hashes'][$_SERVER['PHP_AUTH_USER']])) {
		debug_log("User not found");
		return 401;
	}
	if (!APR1_MD5::check($_SERVER['PHP_AUTH_PW'], $config['hashes'][$_SERVER['PHP_AUTH_USER']])) {
		debug_log("Bad password");
		return 401;
	}
	debug_log("Validated user: ".$_SERVER['PHP_AUTH_USER']);
	return 200;
}

function validate_path() {
	global $config;
	$uri = isset($_SERVER["REQUEST_URI"]) ? $_SERVER["REQUEST_URI"] : "";
	debug_log("Validating path: ".$uri);
	foreach ($config["acl"] as $acl) {
		if (strpos($uri, $acl["path"]) === 0) {
			debug_log("Matched ".$acl["path"]);
			if (!isset($acl["valid_users"])) {
				# No list of valid_users means ok for anon
				debug_log("Public access");
				return 200;
			}
			$result = validate_user();
			if ($result == 200 && !in_array($_SERVER['PHP_AUTH_USER'], $acl["valid_users"])) {
				debug_log("User not in valid_users");
				$result = 403;
			}
			return $result;
		}
	}
	# Default to no access
	debug_log("No match");
	return false;
}

if (defined('__AUTH_CHECK__')) {
	return;
}
if (isset($_REQUEST['logout'])) {
	auth_required();
}
$result = validate_user();
if ($result != 200) {
	auth_required();
}
if (isset($_REQUEST['redirect'])) {
	http_response_code(302);
	header("Location: ".$_REQUEST['redirect']);
}
?>

Hello <?=$_SERVER['PHP_AUTH_USER']?>.
