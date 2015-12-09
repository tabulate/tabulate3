<?php

namespace Tabulate\Commands;

class UpgradeCommand extends \Tabulate\Commands\CommandBase {

    public function run() {
        $db = new \Tabulate\DB\Database();
        if (!$db->get_table('users')) {
            $this->write("Creating table 'users'");
            $db->query("CREATE TABLE `users` ("
                    . " `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY,"
                    . " `name` VARCHAR(200) NOT NULL UNIQUE"
                    . ");");
        }
        if (!$db->get_table('groups')) {
            $this->write("Creating table 'groups'");
            $db->query("CREATE TABLE `groups` ("
                    . " `id` INT(10) UNSIGNED NOT NULL PRIMARY KEY,"
                    . " `name` VARCHAR(200) NOT NULL UNIQUE"
                    . ");");
        }
        if (!$db->get_table('users_groups')) {
            $this->write("Creating table 'users_groups'");
            $db->query("CREATE TABLE `users_groups` ("
                    . " `user` INT(10) UNSIGNED NOT NULL,"
                    . " FOREIGN KEY (`user`) REFERENCES `users` (`id`),"
                    . " `group` INT(10) UNSIGNED NOT NULL,"
                    . " FOREIGN KEY (`group`) REFERENCES `groups` (`id`),"
                    . " PRIMARY KEY (`user`, `group`)"
                    . ");");
        }
        $this->write("Upgrade complete");
    }

}
