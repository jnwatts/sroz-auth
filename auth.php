<?php
require __DIR__ . '/vendor/autoload.php';
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

session_start();

$logout = isset($_REQUEST["logout"]);
if ($logout) {
    setcookie("token", "", 0, $config["path"], $config["site"], true, true);
    $_SESSION["token"] = "";
    $_SESSION["user"] = "";
}

$msg = "";
$valid_auth = false;
$user = isset($_REQUEST["user"]) ? $_REQUEST["user"] : "";
$pass = isset($_REQUEST["pass"]) ? $_REQUEST["pass"] : "";
$token = isset($_COOKIE["token"]) ? $_COOKIE["token"] : "";
$redirect = isset($_REQUEST["redirect"]) ? $_REQUEST["redirect"] : "";
$uri = isset($_SERVER["HTTP_X_ORIGINAL_URI"]) ? $_SERVER["HTTP_X_ORIGINAL_URI"] : "";
$check = isset($_REQUEST["check"]);

function authenticate(&$name, $pass, $token, $config, &$msg) {
    $valid = false;
    $valid_user = false;

    if (!empty($token)) {
        if (isset($_SESSION["token"]) && $_SESSION["token"] == $token) {
            // Valid token? TODO: Check age in DB
            $name = $_SESSION["user"];
            $valid = true;
        }
    } else {
        if (!$valid && $name) {
            // Validate user + pass
            foreach ($config['users'] as $user) {
                if ($user["name"] == $name) {
                    $valid_user = WhiteHat101\Crypt\APR1_MD5::check($pass, $user["pass"]);
                    break;
                }
            }
            if ($valid_user) {
                $_SESSION["token"] = uniqid();
                $_SESSION["user"] = $name;
                setcookie("token", $_SESSION["token"], time()+3600, $config["path"], $config["site"], true, true);
                $valid = true;
            } else {
                $msg = "Invalid username or password";
            }
        }
    }

    return $valid;
}

function validate($user, $uri, $config, &$msg) {
    function startsWith($haystack, $needle)
    {
        $length = strlen($needle);
        return (substr($haystack, 0, $length) === $needle);
    }
    foreach ($config['acls'] as $a) {
        if (startsWith($uri, $a["path"])) {
            if (isset($a["valid_users"])) {
                if (!in_array($user, $a["valid_users"])) {
                    $msg = "Not authorized";
                    return false;
                }
            }
        }
    }
    return true;
}

$valid_auth = authenticate($user, $pass, $token, $config, $msg);
$valid_path = validate($user, $uri, $config, $msg);

if (!$valid_path) {
    if ($valid_user) {
        http_response_code(403);
    } else {
        http_response_code(401);
    }
}
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
/*
if ($check) {
    $fh = fopen("debug.txt", "w");
    if ($fh) {
        fwrite($fh, print_r($_SERVER, true));
        fwrite($fh, "SESSION: ".print_r($_SESSION, true)."\n");
        fwrite($fh, "user: ".print_r($user, true)."\n");
        fwrite($fh, "Response code: ".http_response_code()."\n");
        fwrite($fh, "Reason: ".$msg."\n");
        fclose($fh);
    }
}
*/

if ($check) exit;
if ($valid_auth && $valid_path && $redirect) {
    http_response_code(302);
    header("Location: https://".$_SERVER['HTTP_HOST'].$redirect);
    exit;
}
?>
<pre>
</pre>
<?if ($msg) {?>
<h1>Error <?=http_response_code()?></h1>
<span class="error"><?=$msg?></span>
<?}?>
<?if ($valid_auth) {?>
TODO:
<ul>
<li>u2f</li>
<li>Extract logic to support class: Used by auth.php to PERFORM auth, used by others to safely get authed user or fail. (Like used to use PHP_AUTH_USER)</li>
</ul>
<div>
<form method="post" action="<?=$_SERVER["PHP_SELF"]?>">
<input name="logout" value="Logout" type="submit">
</form>
</div>
<?} else {?>
<form method="post" action="<?=$_SERVER["PHP_SELF"]?>">
<input name="redirect" type="hidden" value="<?=$redirect?>">
<input name="user" type="text" value="<?=$user?>"><br>
<input name="pass" type="password"><br>
<input type="submit">
</form>
<?}?>
