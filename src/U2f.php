<?
namespace Auth;

class U2f implements iDbObject {
    private $config;
    private $lib;
    private $db;
    private $key_params;

    public function __construct(&$config) {
        $this->config =& $config;
        $this->db = $config["db"]["connection"];
        $this->lib = new \u2flib_server\U2F($this->api());

        $this->key_params = ["keyHandle", "publicKey", "certificate", "counter"];
    }

    public function api() {
        return $this->config["u2f"]["appId"];
    }

    public function validRegistrationCount($user) {
        $db = $this->db;

        $query = $db->createQueryBuilder();
        $query->select("count(*)")
            ->from($this->db_table())
            ->where("counter > 0 AND username = ".$query->createNamedParameter($user->name));

        return $query->execute()->fetchColumn(0);
    }

    public function keyHandles($user) {
        $keyHandles = [];
        $db = $this->db;

        $query = $db->createQueryBuilder();
        $query->select("keyHandle")
            ->from($this->db_table())
            ->where("username = " . $query->createNamedParameter($user->name));

        $result = $query->execute();
        if ($result)
            $keyHandles = $result->fetchAll();

        return $keyHandles;
    }

    public function getRegistration($user, $keyHandle) {
        $db = $this->db;

        $query = $db->createQueryBuilder();
        $query->select($this->key_params)
            ->from($this->db_table())
            ->where("keyHandle = ".$query->createNamedParameter($keyHandle)." AND username = ".$query->createNamedParameter($user->name));

        $result = $query->execute();

        return $result->fetch(\PDO::FETCH_OBJ);
    }

    public function getRegistrations($user) {
        $db = $this->db;

        $query = $db->createQueryBuilder();
        $query->select($this->key_params)
            ->from($this->db_table())
            ->where("username = ".$query->createNamedParameter($user->name));

        $result = $query->execute();

        $reg = $result->fetchAll(\PDO::FETCH_OBJ);

        return $reg;
    }

    public function addRegistration($user, $new_reg) {
        $db = $this->db;

        $query = $db->createQueryBuilder();
        $reg = $this->getRegistration($user, $new_reg->keyHandle);
        if ($reg) {
            $query->update($this->db_table());
            foreach ($this->key_params as $param) {
                $query->set($param, $query->createNamedParameter($new_reg->$param));
            }
            $query->where("keyHandle = ".$query->createNamedParameter($new_reg->keyHandle) . 
                " AND username = ".$query->createNamedParameter($user->name));
        } else {
            $query->insert($this->db_table());
            $query->setValue("username", $query->createNamedParameter($user->name));
            foreach ($this->key_params as $param) {
                $query->setValue($param, $query->createNamedParameter($new_reg->$param));
            }
        }
        $query->execute();
    }

    public function removeRegistration($user, $keyHandle) {
        $db = $this->db;

        $query = $db->createQueryBuilder();
        $query->delete($this->db_table)
            ->where("keyHandle = ".$query->createNamedParameter($keyHandle) . 
                " AND username = ".$query->createPositionalParameter($user->name));
        $query->execute();
    }

    public function register($user, $reg_request = null, $reg_response = null) {
        if (!$reg_request) {
            list($reg, $sign) = $this->lib->getRegisterData($this->getRegistrations($user));
            return ["reg" => $reg, "sign" => $sign];
        } else {
            $reg = $this->lib->doRegister($reg_request, $reg_response);
            if ($reg)
                $this->addRegistration($user, $reg);
            return ($reg !== null);
            
        }
    }

    public function authenticate($user, $auth_request = null, $auth_response = null) {
        if (!$auth_request) {
            return $this->lib->getAuthenticateData($this->getRegistrations($user));
        } else {
            $reg = $this->lib->doAuthenticate($auth_request, $this->getRegistrations($user), $auth_response);
            if ($reg)
                $this->addRegistration($user, $reg);
            return ($reg !== null);
        }
    }

    public function db_table() {
        return "SROZ_U2f";
    }

    public function db_version() {
        return 0;
    }

    public function db_upgrade(\Doctrine\DBAL\Schema\Schema $fromSchema, \Doctrine\DBAL\Schema\Schema $toSchema) {
        $t = $toSchema->createTable($this->db_table());
        //TODO: Change username to user_id
        //TODO: Add foreign key to users table
        $t->addColumn("username", "string", ["length" => 32]);
        $t->addColumn("keyHandle", "text");
        $t->addColumn("publicKey", "text");
        $t->addColumn("certificate", "text");
        $t->addColumn("counter", "integer");
        //TODO: Add last modified column
        //TODO: Add failure count?
        $t->setPrimaryKey(["username"]);
        $t->addUniqueIndex(["keyHandle"]);
    }
}
