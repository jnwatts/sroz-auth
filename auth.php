<?php
define("__BASE_DIR__", __DIR__);
require __BASE_DIR__ . '/vendor/autoload.php';

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$auth = new Auth\Auth($config);
$msg = "";

if (defined("__AUTH_INCLUDE__")) {
    // We've been included by another script
    return $auth;
}

if (isset($_REQUEST["logout"]))
    $auth->logout();

$user = $auth->current_user();
if (!$user && isset($_REQUEST["user"]) && isset($_REQUEST["pass"]))
    $user = $auth->login($_REQUEST["user"], $_REQUEST["pass"]);

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

if ($user && isset($_REQUEST["u2f"])) {
    $u2f = $_REQUEST["u2f"];
    try {
        switch ($u2f) {
            case "register":
                $data = $auth->u2f->register();
                echo json_encode($data);
                break;
            case "register2":
                $reg = isset($_REQUEST['reg']) ? json_decode($_REQUEST['reg']) : null;
                $response = isset($_REQUEST['response']) ? json_decode($_REQUEST['response']) : null;
                $auth->u2f->register($reg, $response);
                echo json_encode($auth->u2f->keyHandles());
                break;
            case "unregister":
                $key = isset($_REQUEST['key']) ? $_REQUEST['key'] : null;
                if ($key)
                    $auth->u2f->removeRegistration($key);
                echo json_encode($auth->u2f->keyHandles());
                break;
            case "authenticate":
                $data = $auth->u2f->authenticate();
                echo json_encode($data);
                break;
            case "authenticate2":
                $req = isset($_REQUEST['auth']) ? json_decode($_REQUEST['auth']) : null;
                $response = isset($_REQUEST['response']) ? json_decode($_REQUEST['response']) : null;
                $auth->u2f->authenticate([$req], $response);
                echo json_encode($auth->u2f->keyHandles());
                break;
            default:
                echo "Invalid operation";
                http_response_code(500);
                break;
        }
    } catch (Error $e) {
        echo $e;
        http_response_code(400);
    }
    exit;
}

?>
<html>
<head>
    <script src="https://code.jquery.com/jquery-3.1.1.min.js"
        integrity="sha256-hVVnYaiADRTO2PzUGmuLJr8BLUSjGIZsDYGmIJLv2b8="
        crossorigin="anonymous"></script>
    <script src="/u2f-api.js"></script>
    <script>
        $(function() {
            window.auth = {
                uri: "<?=$_SERVER["PHP_SELF"]?>",
                register: function() {
                    $.ajax({
                        type: "POST",
                        url: auth.uri,
                        data: {u2f: "register"},
                        dataType: "json"
                    }).done(auth.register_callback);
                },
                register_callback: function(data) {
                    console.log("Register request", data);
                    auth.msg("Press U2F button now...");
                    var request = data.reg;
                    var appId = request.appId;
                    var registerRequests = [{version: request.version, challenge: request.challenge}];
                    u2f.register(appId, registerRequests, data.sign, function(data) {
                        console.log("Register callback", data);
                        auth.msg("Registering...");
                        $.ajax({
                            type: "POST",
                            url: auth.uri,
                            data: {u2f: "register2", reg: JSON.stringify(request), response: JSON.stringify(data)},
                            dataType: "json"
                        }).done(auth.register2_callback);
                    });
                },
                register2_callback: function(data) {
                    console.log("Register2 callback", data);
                    auth.msg("");
                    auth.update_keys(data);
                },
                unregister: function(keyHandle) {
                    $.ajax({
                        type: "POST",
                        url: auth.uri,
                        data: {u2f: "unregister", key: keyHandle},
                        dataType: "json"
                    }).done(auth.update_keys);
                },
                update_keys: function(keyHandles) {
                    if (keyHandles)
                        auth.key_handles = keyHandles;
                    var keys = $('#key_handles');
                    keys.html("");
                    auth.key_handles.forEach(function (keyHandle, i) {
                        var k = $("<input type=\"button\">");
                        k.prop("value", "Unregister #" + i);
                        k.on('click', function() {
                            auth.unregister(keyHandle);
                        });
                        keys.append(k);
                    });
                },
                authenticate: function() {
                    $.ajax({
                        type: "POST",
                        url: auth.uri,
                        data: {u2f: "authenticate"},
                        dataType: "json"
                    }).done(auth.authenticate_callback);
                },
                authenticate_callback: function(data) {
                    console.log("Auth request", data);
                    auth.msg("Press U2F button now...");
                    var request = data[0];
                    var appId = request.appId;
                    var challenge = request.challenge;
                    var registeredKeys = [{version: request.version, keyHandle: request.keyHandle}];
                    u2f.sign(appId, challenge, registeredKeys, function(data) {
                        console.log("Auth callback", data);
                        if (data.errorCode) {
                            auth.msg("Failed to sign: " + data.errorCode);
                            return false;
                        }
                        auth.msg("Authenticating...");
                        $.ajax({
                            type: "POST",
                            url: auth.uri,
                            data: {u2f: "authenticate2", auth: JSON.stringify(request), response: JSON.stringify(data)},
                            dataType: "json"
                        }).done(auth.authenticate_callback2);
                    });
                },
                authenticate_callback2: function(data) {
                    console.log("Register2 callback", data);
                    auth.msg("");
                    auth.update_keys(data);
                },
                msg: function(str) {
                    $('#msg').html(str);
                },
                key_handles: <?=json_encode($auth->u2f->keyHandles())?>,
            };

            $('#register').on('click', function() {
                auth.register();
            });

            $('#authenticate').on('click', function() {
                auth.authenticate();
            });

            auth.update_keys(auth.key_handles);
        });
    </script>
</head>
<body>
<?if (http_response_code() != 200) {?>
<h1>Error <?=http_response_code()?></h1>
<?}?>
<div id="msg"><?=$msg?></div>
<?if ($user) {?>
<h2>TODO:</h2>
<ul>
<li>u2f</li>
<li>Remove reliance on session</li>
</ul>
<div>
<div>
    <div id="key_handles">
    </div>
    <input id="register" value="Register U2F" type="button">
    <input id="authenticate" value="authenticate U2F" type="button">
</div>
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
</body>
</html>
