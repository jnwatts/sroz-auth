<?php
include __DIR__ ."/APR1_MD5.php";
$AUTH_JSON=__DIR__ . '/../auth.json';
$config = json_decode(file_get_contents($AUTH_JSON), true);
define('AUTH_DB', $config["db"]);
define('AUTH_SECRET', $config["secret"]);
define('AUTH_COOKIE', hash("sha256", AUTH_SECRET."session"));
$debug = true;

set_exception_handler(function ($e) {
	error_log($e);
	die();
});

function debug_log($m) {
	global $debug;
	if ($debug) {
		error_log($m."\n");
	}
}

function open_db() {
	debug_log('Open db: '.AUTH_DB);
	if (!file_exists(AUTH_DB)) {
		touch(AUTH_DB);
		$db = new SQLite3(AUTH_DB, SQLITE3_OPEN_READWRITE);
		init_db($db);
	} else {
		$db = new SQLite3(AUTH_DB, SQLITE3_OPEN_READWRITE);
	}
	$db->busyTimeout(5000);
	return $db;
}

function init_db($db) {
	$db->exec('DROP TABLE IF EXISTS session_context');
	$db->exec('CREATE TABLE session_context (session TEXT NOT NULL, user TEXT NOT NULL, expires TEXT, PRIMARY KEY (session))');
}

class SessionContext
{
	function __construct($db) {
		$this->db = $db;
		$this->reset();
	}

	function reset() {
		$this->session = NULL;
		$this->user = NULL;
		$this->expires = NULL;
	}

	function login($user) {
		$this->session = hash("sha256", AUTH_SECRET.random_bytes(32));
		$this->user = $user;
		$this->extend();
	}

	function fetch($session) {
		$stmt = $this->db->prepare('SELECT user,expires FROM session_context WHERE session = :session');
		$stmt->bindValue(':session', $session, SQLITE3_TEXT);
		$result = $stmt->execute()->fetchArray(SQLITE3_ASSOC);
		if (!$result) {
			return false;
		}
		if (count($result) < 1) {
			return false;
		}
		$this->session = $session;
		$this->user = $result["user"];
		$this->expires = new DateTime($result["expires"]);
		return true;
	}

	function store() {
		if ($this->session == NULL) {
			return;
		}
		$stmt = $this->db->prepare('INSERT OR REPLACE INTO session_context (session,user,expires) VALUES (:session,:user,:expires)');
		$stmt->bindValue(':session', $this->session, SQLITE3_TEXT);
		$stmt->bindValue(':user', $this->user, SQLITE3_TEXT);
		$stmt->bindValue(':expires', $this->expires->format('c'), SQLITE3_TEXT);
		$stmt->execute();
		$this->clear_expired();
	}

	function is_expired() {
		return is_null($this->expires)
			|| $this->expires < new DateTime();
	}

	function is_valid() {
		return !is_null($this->user) && !$this->is_expired();
	}

	function extend() {
		$this->expires = (new DateTime())->modify('+1 day');
		$this->store();
	}

	function delete() {
		if (!$this->is_expired()) {
			$stmt = $this->db->prepare('DELETE FROM session_context WHERE session = :session');
			$stmt->bindValue(':session', $this->session, SQLITE3_TEXT);
			$stmt->execute();
			$this->reset();
		}
	}

	function clear_expired() {
		$this->db->exec("DELETE from session_context WHERE unixepoch(expires) < unixepoch('now')");
	}
}

function show_login($auth_failure = false) {
	http_response_code(401);
	require(__DIR__.'/auth_login.php');
	exit();
}

function read_cookie() {
	if (!isset($_COOKIE[AUTH_COOKIE])) {
		debug_log("No cookie");
		return NULL;
	}

	$session = $_COOKIE[AUTH_COOKIE];
	if (!preg_match('/^[A-Fa-f0-9]{64}$/', $session)) {
		debug_log("Garbage cookie");
		return NULL;
	}

	return $session;
}

function extend_cookie($session) {
	global $config;
	setcookie(
		AUTH_COOKIE,
		$session,
		time()+$config['cookie']['expire_seconds'],
		$config['cookie']['path'],
		$config['cookie']['domain'],
		true);
}

function validate_user($ctx = NULL) {
	global $config;
	debug_log("Validating user");

	$session = read_cookie();
	if (is_null($session)) {
		return 401;
	}

	if (!$ctx->fetch($session)) {
		debug_log("Session not found");
		return 401;
	}

	if ($ctx->is_expired()) {
		debug_log("Expired session");
		return 401;
	}

	if (!isset($config['hashes'][$ctx->user])) {
		debug_log("User not found");
		return 401;
	}

	$ctx->extend();
	$ctx->store();
	extend_cookie($ctx->session);

	return 200;
}

function validate_path() {
	global $config;
	$db = open_db();
	$ctx = new SessionContext($db);
	validate_user($ctx);
	if ($ctx->is_valid()) {
		header("X-Authentication-Id: ".$ctx->user, true);
	}

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
			if ($ctx->is_valid()) {
				if (in_array($ctx->user, $acl["valid_users"])) {
					debug_log("User is valid");
					return 200;
				}
				return 403;
			} else {
				return 401;
			}
		}
	}
	# Default to no access
	debug_log("No match");
	return 403;
}

function try_login($ctx, $user, $pass) {
	global $config;

	if (!isset($config['hashes'][$user])) {
		debug_log("User not found: ".$user);
		return 401;
	}

	if (!APR1_MD5::check($pass, $config['hashes'][$user])) {
		debug_log("Bad password");
		return 401;
	}

	$ctx->login($user);
	$ctx->store();
	extend_cookie($ctx->session);

	debug_log("Validated user: ". $user);
	return 200;
}

if (defined('__AUTH_INTERNAL__')) {
	return;
}
$db = open_db();
$ctx = new SessionContext($db);
validate_user($ctx);
if (isset($_REQUEST['logout'])) {
	$ctx->delete();
	show_login();
}
if (!$ctx->is_valid()) {
	if (isset($_REQUEST['user']) && isset($_REQUEST['pass'])) {
		$result = try_login($ctx, $_REQUEST['user'], $_REQUEST['pass']);
		if ($result != 200) {
			show_login('Invalid user or password');
		}
	} else {
		show_login();
	}
}
if (isset($_REQUEST['redirect'])) {
	http_response_code(302);
	header("Location: ".$_REQUEST['redirect']);
}
?>

Hello <?=$ctx->user?>.
