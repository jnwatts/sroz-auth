<?
namespace Auth;

class Auth {
    private $config;
    public $users;
    public $access;
    public $u2f;
    public $session;
    private $schema_version_table;

    public function __construct(&$config) {
        $this->config =& $config;

        $this->db_config = new \Doctrine\DBAL\Configuration();
        $this->db = \Doctrine\DBAL\DriverManager::getConnection($this->config['db'], $this->db_config);
        $this->config["db"]["connection"] = $this->db;

        $this->users = new Users($config);
        $this->access = new Access($config);
        $this->u2f = new U2f($config);
        $this->session = new Session($config);

        $this->init_db();

        $this->session->start();
    }

    private function init_db() {
        //unlink("/srv/http/auth.db");
        $this->schema_version_table = "SROZ_SchemaVersions";

        $db_objects = [
            $this->session,
            $this->u2f,
        ];

        $db = $this->db;
        $sm = $db->getSchemaManager();
        //TODO: Table prefixes

        if (!$sm->tablesExist([$this->schema_version_table])) {
            $this->upgrade_schema($db_objects);
        } else {
            $query = $db->createQueryBuilder();
            $query->select("name", "version")->from($this->schema_version_table);
            $result = $query->execute();
            $schema_versions = $result->fetchAll();
            $updated_db_objects = [];
            foreach ($schema_versions as $sv) {
                foreach ($db_objects as $dbo) {
                    if ($dbo->db_table() == $sv["name"]) {
                        if ($dbo->db_version() > $sv["version"]) {
                            $updated_db_objects[] = $dbo;
                        }
                        break;
                    }
                }
            }
            if (count($updated_db_objects) > 0) {
                $this->upgrade_schema($updated_db_objects);
            }
        }
    }

    private function upgrade_schema($db_objects) {
        $db = $this->db;
        $sm = $db->getSchemaManager();
        $fromSchema = $sm->createSchema();
        $toSchema = new \Doctrine\DBAL\Schema\Schema();

        $table = $toSchema->createTable($this->schema_version_table);
        $table->addColumn("name", "string", ["length" => 32]);
        $table->addColumn("version", "integer", ["unsigned" => true]);
        $table->setPrimaryKey(["name"]);
        $table->addUniqueIndex(["name"]);
        foreach ($db_objects as $dbo) {
            $dbo->db_create($toSchema);
        }

        $comparator = new \Doctrine\DBAL\Schema\Comparator();
        $schemaDiff = $comparator->compare($fromSchema, $toSchema);
        $queries = $schemaDiff->toSql($db->getDatabasePlatform());
        foreach ($queries as $sql) {
            $db->executeQuery($sql);
        }
        foreach ($db_objects as $dbo) {
            $query = $db->createQueryBuilder();
            $query->select("version")
                ->from($this->schema_version_table)
                ->where("name", "?")->setParameter(0, $dbo->db_table());
            $result = $query->execute();
            if ($result->fetch()) {
                $query = $db->createQueryBuilder();
                $query->update($this->schema_version_table)
                    ->set("version", "?")->setParameter(0, $dbo->db_version())
                    ->where("name = ?")->setParameter(1, $dbo->db_table());
                $query->execute();
            } else {
                $query = $db->createQueryBuilder();
                $query->insert($this->schema_version_table)
                    ->setValue("version", "?")->setParameter(0, $dbo->db_version())
                    ->setValue("name", "?")->setParameter(1, $dbo->db_table());
                $query->execute();
            }
        }
    }

    public function current_user() {
        if ($this->session->validated())
            return $this->users->get($this->session->username());
        else
            return null;
    }
}

