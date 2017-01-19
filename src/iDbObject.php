<?php
namespace Auth;

interface iDbObject {
    public function db_table();
    public function db_version();
    public function db_upgrade(\Doctrine\DBAL\Schema\Schema $fromSchema, \Doctrine\DBAL\Schema\Schema $toSchema);
}
