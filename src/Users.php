<?
namespace Auth;
use WhiteHat101\Crypt\APR1_MD5;

class Users {
    private $config;

    public function __construct(&$config) {
        $this->config =& $config;
    }

    public function get($name) {
        foreach ($this->config['users'] as $user) {
            if ($user["name"] == $name) {
                return new User($user);
            }
        }
        return null;
    }

    public function get_by_token($token) {
        if ($token && isset($_SESSION["user"])) {
            return $this->get($_SESSION["user"]);
        }
        return null;
    }

    public function validate($user, $pass, $token) {
        if ($user) {
            $valid = APR1_MD5::check($pass, $user->hash);
            if ($valid)
                $_SESSION["user"] = $user->name;
            return $valid;
        }
        return false;
    }
}

