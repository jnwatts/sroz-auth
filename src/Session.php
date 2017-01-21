<?
namespace Auth;

class Session implements iDbObject {
    private $config;
    private $params;
    private $data;
    private $token;

    public function __construct(&$config) {
        $this->config =& $config;
        $this->params = [
            "username",
            "validated_password",
            "validated_u2f",
        ];
        $this->db = $config["db"]["connection"];
    }

    public function start() {
		$db = $this->db;
        $token = $this->config["token"];

        $this->token = isset($_COOKIE[$token["name"]]) ? $_COOKIE[$token["name"]] : "";
        $data = $this->read();
        if ($data) {
            $this->data = $data;
        } else {
            $this->data = (object)[];
            foreach ($this->params as $param) {
                $this->data->$param = null;
            }
        }
    }

    private function read() {
        $db = $this->db;
        $query = $db->createQueryBuilder();
        $query->select("username", "validated_password", "validated_u2f")
            ->from($this->db_table())
            ->where("token = ?")->setParameter(0, $this->token);

        $result = $query->execute();
        return $result->fetch(\PDO::FETCH_OBJ);
    }

    public function clear() {
        if ($this->token) {
            if ($this->validated()) {
                $db = $this->db;
                $query = $db->createQueryBuilder();
                $query->delete($this->db_table())
                    ->where("token = " . $query->createNamedParameter($this->token));
                $query->execute();
            }
            $token = $this->config["token"];
            setcookie($token["name"], "", time()-3600, $token["path"], $token["domain"], true, true);
            unset($_COOKIE[$token["name"]]);
        }
        $this->data = null;
        $this->token = null;
    }

    public function save($param = null) {
		$db = $this->db;
        $params = $this->params;
        if ($param !== null)
            $params = [$param];

        $query = $db->createQueryBuilder();
        $data = $this->read();
        if ($data) {
            $query->update($this->db_table());
            foreach ($params as $p) {
                $query->set($db->quoteIdentifier($p), $query->createNamedParameter($this->data->$p));
            }
            $query->where($db->quoteIdentifier("token") . " = " . $query->createNamedParameter($this->token));
        } else {
            $this->token = $this->uuidv4();
            $token = $this->config["token"];
            setcookie($token["name"], $this->token, time()+$token["lifetime"], $token["path"], $token["domain"], true, true);
            $_COOKIE[$token["name"]] = $this->token;
            $query->insert($this->db_table())
                ->setValue("token", $query->createNamedParameter($this->token));
            foreach ($params as $p) {
                $query->setValue($db->quoteIdentifier($p), $query->createNamedParameter($this->data->$p));
            }
        }
        try {
            $query->execute();
        } catch (\Exception $e) {
            die($e);
        }
    }

    private function param($param, $val = null) {
        if ($val !== null) {
            if ($this->data->$param != $val) {
                $this->data->$param = $val;
                $this->save($param);
            }
        } else {
            return $this->data->$param;
        }
    }

    public function username($val = null) {
        return $this->param("username", $val);
    }

    public function validated_password($val = null) {
        return $this->param("validated_password", $val);
    }

    public function validated_u2f($val = null) {
        return $this->param("validated_u2f", $val);
    }

    public function validated() {
        return $this->validated_password() && $this->validated_u2f();
    }

    public function dump() {
        return $this->data;
    }

    public function db_table() {
        return "SROZ_Session";
    }

    public function db_version() {
        return 0;
    }

    public function db_create(\Doctrine\DBAL\Schema\Schema $schema) {
        $t = $schema->createTable($this->db_table());
        $t->addColumn("token", "string", ["length" => strlen("xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx")]);
        //TODO: Change username to user_id
        //TODO: Add foreign key to users table
        $t->addColumn("username", "string", ["length" => 32, "notnull" => false]);
        $t->addColumn("validated_password", "boolean", ["default" => false]);
        $t->addColumn("validated_u2f", "boolean", ["default" => false]);
        //TODO: Add last modified column
        $t->setPrimaryKey(["token"]);
    }

	/**
	 * Return a UUID (version 4) using random bytes
	 * Note that version 4 follows the format:
	 *     xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
	 * where y is one of: [8, 9, A, B]
	 * 
	 * We use (random_bytes(1) & 0x0F) | 0x40 to force
	 * the first character of hex value to always be 4
	 * in the appropriate position.
	 * 
	 * For 4: http://3v4l.org/q2JN9
	 * For Y: http://3v4l.org/EsGSU
	 * For the whole shebang: https://3v4l.org/LNgJb
	 * 
	 * @ref https://stackoverflow.com/a/31460273/2224584
	 * @ref https://paragonie.com/b/JvICXzh_jhLyt4y3
	 * 
	 * @return string
	 */
	private function uuidv4()
	{
		return implode('-', [
			bin2hex(random_bytes(4)),
			bin2hex(random_bytes(2)),
			bin2hex(chr((ord(random_bytes(1)) & 0x0F) | 0x40)) . bin2hex(random_bytes(1)),
			bin2hex(chr((ord(random_bytes(1)) & 0x3F) | 0x80)) . bin2hex(random_bytes(1)),
			bin2hex(random_bytes(6))
		]);
	}
}
