<?
namespace Auth;

class Token {
    private $config;

    public function __construct(&$config) {
        $this->config =& $config;
    }

    public function new() {
        $token = $this->config["token"];
        $value = uniqid();
        $_SESSION["token"] = $value;
        setcookie($token["name"], $value, time()+$token["lifetime"], $token["path"], $token["domain"], true, true);
        return $value;
    }

    public function get() {
        $token = $this->config["token"];
        $value = isset($_COOKIE[$token["name"]]) ? $_COOKIE[$token["name"]] : null;
        $session_value = isset($_SESSION["token"]) ? $_SESSION["token"] : null;
        return ($value == $session_value) ? $value : null;
    }

    public function clear() {
        $token = $this->config["token"];
        unset($_SESSION["token"]);
        unset($_COOKIE[$token["name"]]);
        setcookie($token["name"], "", time()-3600, $token["path"], $token["domain"], true, true);
    }
}
