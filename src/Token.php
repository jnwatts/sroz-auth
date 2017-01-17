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
        setcookie($token["name"], $value, time()+$token["lifetime"], $token["path"], $token["domain"], true, true);
        $_COOKIE[$token["name"]] = $value;
        return $value;
    }

    public function get() {
        $token = $this->config["token"];
        return isset($_COOKIE[$token["name"]]) ? $_COOKIE[$token["name"]] : $this->new();
    }

    public function clear() {
        $token = $this->config["token"];
        unset($_COOKIE[$token["name"]]);
        setcookie($token["name"], "", time()-3600, $token["path"], $token["domain"], true, true);
    }
}
