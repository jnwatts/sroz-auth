<?php

use Phinx\Migration\AbstractMigration;

class InitialVersion extends AbstractMigration
{
    /**
     * Change Method.
     *
     * Write your reversible migrations using this method.
     *
     * More information on writing migrations is available here:
     * http://docs.phinx.org/en/latest/migrations.html#the-abstractmigration-class
     *
     * The following commands can be used in this method and Phinx will
     * automatically reverse them when rolling back:
     *
     *    createTable
     *    renameTable
     *    addColumn
     *    renameColumn
     *    addIndex
     *    addForeignKey
     *
     * Remember to call "create()" or "update()" and NOT "save()" when working
     * with the Table class.
     */
    public function change()
    {
        $session = $this->table("session", ["id" => false, "primary_key" => ["token"]])
            ->addColumn("token", "string", ["length" => strlen("xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx")])
            ->addColumn("username", "string", ["length" => 32, "null" => true])
            ->addColumn("validated_password", "boolean", ["default" => false])
            ->addColumn("validated_u2f", "boolean", ["default" => false])
            ->addColumn("ip_address", "string", ["null" => true])
            ->addTimestamps()
            ->create();

        $u2f = $this->table("u2f", ["id" => false, "primary_key" => ["username", "key_handle"]])
            ->addColumn("username", "string", ["length" => 32])
            ->addColumn("key_handle", "text")
            ->addColumn("public_key", "text")
            ->addColumn("certificate", "text")
            ->addColumn("counter", "integer")
            ->addTimestamps()
            ->create();

    }
}
