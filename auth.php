<?php
define("__BASE_DIR__", __DIR__);
require __BASE_DIR__ . '/vendor/autoload.php';

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$auth = new Auth\Auth($config);
if (defined("__AUTH_INCLUDE__")) {
    // We've been included by another script
    return $auth;
}

$session =& $auth->session;
$users =& $auth->users;
$u2f =& $auth->u2f;
$user = null;
$redirect = isset($_REQUEST["redirect"]) ? $_REQUEST["redirect"] : "";
$msg = "";
$js_env = [
    "auth.api" => $u2f->api()
];
if ($redirect)
    $js_env["auth.redirect"] = $redirect;

if (isset($_REQUEST["logout"])) {
    $session->clear();
    if ($redirect) {
        header("Location: " . $redirect, TRUE, 302);
        exit;
    }
    $session->start();
}

if ($session->username() && $session->ip_address()) {
    if ($session->ip_address() != $_SERVER['REMOTE_ADDR']) {
        $session->clear();
    }
}

if (!$session->username()) {
    if (isset($_REQUEST["username"])) {
        $session->username($_REQUEST["username"]);
        $session->ip_address($_SERVER['REMOTE_ADDR']);
    }
}

if (!$session->validated_password()) {
    if (isset($_REQUEST["password"])) {
        if ($users->login($session->username(), $_REQUEST["password"])) {
            $session->validated_password(true);
        }
    }
}

$user = $users->get($session->username());

if ($user) {
    $js_env["auth.key_handles"] = $u2f->keyHandles($user);
    $u2f_reg_count = $u2f->validRegistrationCount($user);
}

if (isset($_REQUEST["u2f"])) {
    if (!$user || !$session->validated_password()) {
        http_response_code(401);
        $data = ["errorCode" => 401, "errorMsg" => "Authentication required"];
        exit;
    }
    try {
        $action = $_REQUEST["u2f"];
        $data = null;
        if ($action == "authenticate") {
            $data = $u2f->authenticate($user);
        } else if ($action == "authenticate2") {
            $req = isset($_REQUEST['auth']) ? json_decode($_REQUEST['auth']) : null;
            $response = isset($_REQUEST['response']) ? json_decode($_REQUEST['response']) : null;
            $result = $u2f->authenticate($user, [$req], $response);
            if ($result)
                $session->validated_u2f(true);
            $data = $u2f->keyHandles($user);
        } else if ($session->validated_u2f() || $u2f_reg_count == 0) {
            if ($action == "register") {
                $data = $u2f->register($user);
            } else if ($action == "register2") {
                $reg = isset($_REQUEST['reg']) ? json_decode($_REQUEST['reg']) : null;
                $response = isset($_REQUEST['response']) ? json_decode($_REQUEST['response']) : null;
                $u2f->register($user, $reg, $response);
                $data = $u2f->keyHandles($user);
            } else if ($action == "unregister") {
                $key = isset($_REQUEST['key']) ? $_REQUEST['key'] : null;
                if ($key)
                    $u2f->removeRegistration($user, $key);
                $data = $u2f->keyHandles($user);
            }
        }
        if ($data === null) {
            http_response_code(400);
            $data = ["errorCode" => 400, "errorMsg" => "Bad request"];
        }
    } catch (Error $e) {
        http_response_code(500);
        $data = ["errorCode" => $e->getCode(), "errorMsg" => $e->getMessage(), "stackTrace" => $e->getTrace()];
    } catch (Exception $e) {
        http_response_code(400);
        $data = ["errorCode" => $e->getCode(), "errorMsg" => $e->getMessage(), "stackTrace" => $e->getTrace()];
    }
    echo json_encode($data);
    exit;
}

if (!$session->validated_password()) {
    include(__BASE_DIR__ . "/views/header.php");
    include(__BASE_DIR__ . "/views/login.php");
    include(__BASE_DIR__ . "/views/footer.php");
    exit;
} else if ($u2f_reg_count > 0 && !$session->validated_u2f()) {
    include(__BASE_DIR__ . "/views/header.php");
    include(__BASE_DIR__ . "/views/u2f_auth.php");
    include(__BASE_DIR__ . "/views/footer.php");
    exit;
} else {
    $session->validated_u2f(true);
}

if ($redirect) {
    header("Location: " . $redirect, TRUE, 302);
    exit;
}

include(__BASE_DIR__ . "/views/header.php");
include(__BASE_DIR__ . "/views/user_info.php");
include(__BASE_DIR__ . "/views/footer.php");
exit;
