<?
namespace Auth;

class Auth {
    private $config;
    public $users;
    public $token;
    public $access;
    public $u2f;
    public $session;

    public function __construct(&$config) {
        $this->config =& $config;
        $this->users = new Users($config);
        $this->token = new Token($config);
        $this->access = new Access($config);
        $this->u2f = new U2f($config);
        $this->session = new Session($config);

        $this->session->start($this->token->get());
    }

    public function current_user() {
        if ($this->session->validated())
            return $this->users->get($this->session->username());
        else
            return null;
    }
}

