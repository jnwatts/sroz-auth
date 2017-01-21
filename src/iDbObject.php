<?php
namespace Auth;

interface iDbObject {
    public function db_table();
    public function db_version();
    public function db_create(\Doctrine\DBAL\Schema\Schema $schema);
}
