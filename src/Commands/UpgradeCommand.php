<?php

namespace Tabulate\Commands;

class UpgradeCommand extends \Tabulate\Commands\CommandBase
{

    public function run()
    {
        $db = new \Tabulate\DB\Database();
        if (!$db->get_table('users')) {
            $this->write("Creating table 'users'");
            $db->query("CREATE TABLE `users` ("
                    . " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
                    . " `name` VARCHAR(200) NOT NULL UNIQUE,"
                    . " `password` VARCHAR(100) NOT NULL"
                    . ");");
        }
        if (!$db->get_table('users')) {
            $this->write("Creating table 'users'");
            $db->query("CREATE TABLE `users` ("
                    . " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
                    . " `name` VARCHAR(200) NOT NULL UNIQUE"
                    . ");");
        }
        if (!$db->get_table('groups')) {
            $this->write("Creating table 'groups'");
            $db->query("CREATE TABLE `groups` ("
                    . " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
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
        if (!$db->get_table('grants')) {
            $this->write("Creating table 'grants'");
            $db->query("CREATE TABLE `grants` ("
                    . " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
                    . " `group` INT(10) UNSIGNED NOT NULL,"
                    . " FOREIGN KEY (`group`) REFERENCES `groups` (`id`),"
                    . " `permission` VARCHAR(200) NOT NULL DEFAULT '*',"
                    . " `table_name` VARCHAR(100) NOT NULL DEFAULT '*' "
                    . ");");
        }
        if (!$db->get_table('changesets')) {
            $this->write("Creating table 'changesets'");
            $sql = "CREATE TABLE IF NOT EXISTS `changesets` (
			`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`date_and_time` DATETIME NOT NULL,
			`user` INT(10) UNSIGNED NOT NULL,
			FOREIGN KEY (`user`) REFERENCES `users` (`id`),
			`comment` TEXT NULL DEFAULT NULL
			);";
            $db->query($sql);
        }
        if (!$db->get_table('changes')) {
            $this->write("Creating table 'changes'");
            $sql = "CREATE TABLE IF NOT EXISTS `changes` (
			`id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`changeset` INT(10) UNSIGNED NOT NULL,
			FOREIGN KEY (`changeset`) REFERENCES `changesets` (`id`),
			`change_type` ENUM('field', 'file', 'foreign_key') NOT NULL DEFAULT 'field',
			`table_name` TEXT(65) NOT NULL,
			`record_ident` TEXT(65) NOT NULL,
			`column_name` TEXT(65) NOT NULL,
			`old_value` LONGTEXT NULL DEFAULT NULL,
			`new_value` LONGTEXT NULL DEFAULT NULL
			);";
            $db->query($sql);
        }

        $this->write("Upgrade complete");
    }
}
