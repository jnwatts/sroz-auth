<?
namespace Auth;

class Session {
    private $config;
    private $data;
    private $token;

    public function __construct(&$config) {
        $this->config =& $config;

        $this->clear();
    }

    public function start($token) {
        if (session_status() == PHP_SESSION_NONE)
            session_start();

        $this->token = $token;

        if (isset($_SESSION[$token]))
            @$this->data = json_decode($_SESSION[$token]);

        if ($this->data === null) {
            $this->data = json_decode('{
                "username": null,
                "validated_password": false,
                "validated_u2f": false
            }');
        }
    }

    public function clear() {
        if ($this->token)
            unset($_SESSION[$this->token]);
        $this->data = null;
        $this->token = null;
    }

    public function save() {
        $_SESSION[$this->token] = json_encode($this->data);
    }

    public function username($val = null) {
        if ($val !== null) {
            $this->data->username = $val;
            $this->save();
        } else {
            return $this->data->username;
        }
    }

    public function validated_password($val = null) {
        if ($val !== null) {
            $this->data->validated_password = $val;
            $this->save();
        } else {
            return $this->data->validated_password;
        }
    }

    public function validated_u2f($val = null) {
        if ($val !== null) {
            $this->data->validated_u2f = $val;
            $this->save();
        } else {
            return $this->data->validated_u2f;
        }
    }

    public function validated() {
        return $this->validated_password() && $this->validated_u2f();
    }

    public function dump() {
        return $this->data;
    }
}
