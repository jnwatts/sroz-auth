<?
namespace Auth;

class Auth {
    private $config;
    public $users;
    public $token;
    public $access;
    public $u2f;

    public function __construct(&$config) {
        $this->config =& $config;
        $this->users = new Users($config);
        $this->token = new Token($config);
        $this->access = new Access($config);
        $this->u2f = new U2f($config);

        if (session_status() == PHP_SESSION_NONE)
            session_start();
    }

    public function users() {
        return $this->users;
    }

    public function current_user() {
        $token = $this->token->get();
        if (!$token)
            return null;

        //TODO: Get user from DB where token
        return $this->users->get($_SESSION["user"]);
    }

    public function login($name, $pass) {
        $user = $this->users->get($name);
        if ($this->users->validate($user, $pass)) {
            $this->token->new();
            $_SESSION["user"] = $user->name;
            return $user;
        } else {
            return null;
        }
    }

    public function logout() {
        $this->token->clear();
        unset($_SESSION["user"]);
    }

}

