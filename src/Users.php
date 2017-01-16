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

    public function validate($user, $pass) {
        if ($user) {
            return APR1_MD5::check($pass, $user->hash);
        }
        return false;
    }
}

