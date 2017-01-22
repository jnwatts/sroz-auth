<?
namespace Auth;

class U2f implements iDbObject {
    private $config;
    private $lib;
    private $db;

    private static $param_map = [
        "key_handle" => "keyHandle",
        "public_key" => "publicKey",
        "certificate" => "certificate",
        "counter" => "counter",
    ];

    public function __construct(&$config) {
        $this->config =& $config;
        $this->db = $config["db"]["connection"];
        $this->lib = new \u2flib_server\U2F($this->api());
    }

    public function api() {
        return $this->config["u2f"]["appId"];
    }

    private static function fromDb($arg) {
        $_fromDb = function ($reg) {
            foreach (U2f::$param_map as $db => $obj) {
                if (isset($reg->$db) && $db != $obj) {
                    $reg->$obj = $reg->$db;
                    unset($reg->$db);
                }
            }
            return $reg;
        };
        if (!$arg)
            return $arg;
        if (is_array($arg)) {
            foreach ($arg as &$reg) {
                $reg = $_fromDb($reg);
            }
        } else {
            $arg = $_fromDb($arg);
        }
        return $arg;
    }

    private static function toDb($arg) {
        $_fromDb = function ($reg) {
            foreach (U2f::$param_map as $db => $obj) {
                if (isset($reg->$obj) && $db != $obj) {
                    $reg->$db = $reg->$obj;
                    unset($reg->$obj);
                }
            }
            return $reg;
        };
        if (!$arg)
            return $arg;
        if (is_array($arg)) {
            foreach ($arg as &$reg) {
                $reg = $_toDb($reg);
            }
        } else {
            $arg = $_toDb($arg);
        }
        return $arg;
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
        $query->select("key_handle as keyHandle")
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
        $query->select(array_keys(U2f::$param_map))
            ->from($this->db_table())
            ->where("key_handle = ".$query->createNamedParameter($keyHandle)." AND username = ".$query->createNamedParameter($user->name));

        $result = $query->execute();
        $result->setFetchMode(\PDO::FETCH_OBJ);

        $reg = U2f::fromDb($result->fetch());

        return $reg;
    }

    public function getRegistrations($user) {
        $db = $this->db;

        $query = $db->createQueryBuilder();
        $query->select(array_keys(U2f::$param_map))
            ->from($this->db_table())
            ->where("username = ".$query->createNamedParameter($user->name));

        $result = $query->execute();
        $result->setFetchMode(\PDO::FETCH_OBJ);

        $regs = U2f::fromDb($result->fetchAll());

        return $regs;
    }

    public function addRegistration($user, $new_reg) {
        $db = $this->db;

        $query = $db->createQueryBuilder();
        $reg = $this->getRegistration($user, $new_reg->keyHandle);
        if ($reg) {
            $query->update($this->db_table());
            foreach (U2f::$param_map as $db => $obj) {
                if (isset($new_reg->$obj))
                    $query->set($db, $query->createNamedParameter($new_reg->$obj));
            }
            DbHelper::updateTimestamp($query);
            $query->where("key_handle = ".$query->createNamedParameter($new_reg->keyHandle) .
                " AND username = ".$query->createNamedParameter($user->name));
        } else {
            $query->insert($this->db_table());
            $query->setValue("username", $query->createNamedParameter($user->name));
            foreach (U2f::$param_map as $db => $obj) {
                if (isset($new_reg->$obj))
                    $query->setValue($db, $query->createNamedParameter($new_reg->$obj));
            }
            DbHelper::initTimestamp($query);
        }
        $query->execute();
    }

    public function removeRegistration($user, $keyHandle) {
        $db = $this->db;

        $query = $db->createQueryBuilder();
        $query->delete($this->db_table())
            ->where("key_handle = ".$query->createNamedParameter($keyHandle) .
                " AND username = ".$query->createNamedParameter($user->name));
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
        return "u2f";
    }
}
