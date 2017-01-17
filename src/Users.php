<?
namespace Auth;
use WhiteHat101\Crypt\APR1_MD5;

class Users {
    private $config;

    public function __construct(&$config) {
        $this->config =& $config;
    }

    public function get($username) {
        foreach ($this->config['users'] as $user) {
            if ($user["name"] == $username) {
                return new User($user);
                break;
            }
        }
        return null;
    }

    public function login($username, $password) {
        return APR1_MD5::check($password, $this->config['hashes'][$username]);
    }
}

