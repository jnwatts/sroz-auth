<?
namespace Auth;

class Auth {
    private $config;
    public $users;
    public $access;
    public $u2f;
    public $session;
    private $schema_version_table;
    private $config_tokens;

    public function __construct(&$config) {
        $this->config =& $config;
        $this->config_tokens = [
            "%%BASE_DIR%%" => __BASE_DIR__
        ];
        $this->config["db"]["url"] = $this->replace_config_tokens($this->config["db"]["url"]);

        $this->db_config = new \Doctrine\DBAL\Configuration();
        $this->db = \Doctrine\DBAL\DriverManager::getConnection($this->config['db'], $this->db_config);
        $this->config["db"]["connection"] = $this->db;

        $this->users = new Users($config);
        $this->access = new Access($config);
        $this->u2f = new U2f($config);
        $this->session = new Session($config);

        if (!defined("__AUTH_SKIP_SESSION__"))
            $this->session->start();
    }

    private function replace_config_tokens($val) {
        foreach ($this->config_tokens as $search => $replace) {
            $val = str_replace($search, $replace, $val);
        }
        return $val;
    }

    public function phinx_config() {
        return [
            "paths" => [
                "migrations" => __BASE_DIR__ . "/db/migrations",
                "seeds" => __BASE_DIR__ . "/db/seeds",
            ],
            "environments" => [
                "default_database" => "auth",
                "auth" => [
                    "name" => $this->config["db"]["name"],
                    "connection" => $this->db->getWrappedConnection(),
                ],
            ],
        ];
    }

    public function current_user() {
        if ($this->session->validated())
            return $this->users->get($this->session->username());
        else
            return null;
    }
}

