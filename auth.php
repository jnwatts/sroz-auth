<?php
include __DIR__ ."/APR1_MD5.php";
$AUTH_JSON=__DIR__ . '/../auth.json';
$config = json_decode(file_get_contents($AUTH_JSON), true);
define('AUTH_DB', $config["db"]);
define('AUTH_SECRET', $config["secret"]);
define('AUTH_COOKIE', hash("sha256", AUTH_SECRET."session"));
$debug = false;

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
	$db->exec('CREATE TABLE user_ips (user TEXT NOT NULL, ip TEXT NOT NULL, expires TEXT NOT NULL, PRIMARY KEY (user, ip))');
}

class SessionContext
{
	function __construct($db) {
		$this->db = $db;
		$this->reset();
		$this->clear_expired();
	}

	function reset() {
		$this->session = NULL;
		$this->user = NULL;
		$this->expires = NULL;
		$this->ip = $_SERVER["REMOTE_ADDR"];
	}

	function login($user) {
		$this->session = hash("sha256", AUTH_SECRET.random_bytes(32));
		$this->user = $user;
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
		$stmt = $this->db->prepare('INSERT OR REPLACE INTO user_ips (user,ip,expires) VALUES (:user,:ip,:expires)');
		$stmt->bindValue(':user', $this->user, SQLITE3_TEXT);
		$stmt->bindValue(':ip', $this->ip, SQLITE3_TEXT);
		$stmt->bindValue(':expires', $this->expires->format('c'), SQLITE3_TEXT);
		$stmt->execute();
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
		extend_cookie($this->session);
	}

	function delete() {
		if (!$this->is_expired()) {
			$stmt = $this->db->prepare('DELETE FROM session_context WHERE session = :session');
			$stmt->bindValue(':session', $this->session, SQLITE3_TEXT);
			$stmt->execute();
			$this->reset();
		}
		$this->clear_expired();
	}

	function clear_expired() {
		$this->db->exec("DELETE from session_context WHERE unixepoch(expires) < unixepoch('now')");
		$this->db->exec("DELETE from user_ips WHERE unixepoch(expires) < unixepoch('now') OR NOT user in (SELECT user FROM session_context)");
	}

	function user_ips() {
		$stmt = $this->db->prepare("SELECT ip FROM user_ips");
		$stmt->bindValue(":user", $this->user, SQLITE3_TEXT);
		$user_ips = [];
		$result = $stmt->execute();
		while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
			array_push($user_ips, $row["ip"]);
		};
		return $user_ips;
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

	return 200;
}

function match_network($ip, $network) {
	$ip = inet_pton($ip);

	if (strpos($network, '/')) {
		list($network, $mask_len) = explode('/', $network);
		$n = inet_pton($network);
	} else {
		$n = inet_pton($network);
		$mask_len = 8*strlen($n);
	}

	$n_len = strlen($n);
	$ip_len = strlen($ip);
	if ($n_len != $ip_len) {
		# IPv4 vs IPv6 mismatch
		return false;
	}

	$mask = '';
	for ($i = 0; $i < $n_len; $i++) {
		$mask_byte_m = min($mask_len, 8);
		$mask_byte = bindec(str_repeat('1', $mask_byte_m) . str_repeat('0', 8 - $mask_byte_m));
		$mask .= pack('C', $mask_byte);
		$mask_len -= $mask_byte_m;
	}

	if (($ip & $mask) == $n) {
		return true;
	}

	return false;
}

function validate_ip($ctx) {
	global $config;
	$ip = filter_var($ctx->ip, FILTER_VALIDATE_IP, 0);
	if ($ip === false) {
		return false;
	}
	foreach ($config["allowed_networks"] as $n) {
		if (match_network($ip, $n)) {
			debug_log("Matched network ".$n);
			return true;
		}
	}
	$user_ips = $ctx->user_ips();
	foreach ($user_ips as $n) {
		if (match_network($ip, $n)) {
			debug_log("Matched user IP ".$n);
			return true;
		}
	}
	debug_log("Denied IP ".$ip);
	return false;
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
	debug_log("Validating uri: ".$uri);
	foreach ($config["acl"] as $acl) {
		if (strpos($uri, $acl["path"]) === 0) {
			debug_log("Matched path ".$acl["path"]);
			if (isset($acl["valid_users"])) {
				if ($ctx->is_valid()) {
					if (in_array($ctx->user, $acl["valid_users"])) {
						debug_log("User is valid");
						return 200;
					}
					return 403;
				} else {
					debug_log("User not valid");
					return 401;
				}
			}
			if (isset($acl["valid_network"]) && $acl["valid_network"]) {
				if (validate_ip($ctx) == true) {
					return 200;
				}
			}
			if (isset($acl["public"]) && $acl["public"] === true) {
				debug_log("Public access");
				return 200;
			}
			return 401;
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
	$ctx->extend();
	$ctx->store();

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
<head>
<link href="/theme.css" rel="stylesheet" type="text/css">
<link href="/bootstrap_3.3.7.min.css" rel="stylesheet" type="text/css">
<link href="/index.css" rel="stylesheet" type="text/css">
</head>
<body>
Hello <?=$ctx->user?>.
</body>
