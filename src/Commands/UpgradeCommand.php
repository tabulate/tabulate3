<?php

namespace Tabulate\Commands;

class UpgradeCommand extends \Tabulate\Commands\CommandBase {

    public function run() {
        $db = new \Tabulate\DB\Database();
        if (!$db->get_table('users')) {
            $this->write("Creating table 'users'");
            $db->query("CREATE TABLE IF NOT EXISTS users ("
                    . " id INT(10) UNSIGNED NOT NULL PRIMARY KEY,"
                    . " name VARCHAR(200) NOT NULL UNIQUE"
                    . ");");
        }
        $this->write("Upgrade complete");
    }

}
