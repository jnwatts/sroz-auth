<?php
define("__BASE_DIR__", __DIR__);
require __BASE_DIR__ . '/vendor/autoload.php';

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$auth = new Auth\Auth($config);

if (defined("__AUTH_INCLUDE__")) {
    // We've been included by another script
    return $auth;
}

if (isset($_REQUEST["logout"]))
    $auth->logout();

$user = $auth->current_user();
if (!$user && isset($_REQUEST["user"]) && isset($_REQUEST["pass"]))
    $user = $auth->login($_REQUEST["user"], $_REQUEST["pass"]);

$msg = "";
$redirect = isset($_REQUEST["redirect"]) ? $_REQUEST["redirect"] : "";
$uri = isset($_SERVER["HTTP_X_ORIGINAL_URI"]) ? $_SERVER["HTTP_X_ORIGINAL_URI"] : $redirect;
$check = isset($_REQUEST["check"]);
$valid = $auth->access->validate($user, $uri);

if (!$valid) {
    if ($user) {
        http_response_code(403);
        $msg = "Not authorized";
    } else {
        http_response_code(401);
        $msg = "Authentication required";
    }
}
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
if ($check) exit;
if ($valid && $redirect) {
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
<?if ($user) {?>
TODO:
<ul>
<li>u2f</li>
<li>Remove reliance on session</li>
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
