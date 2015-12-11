<?php

namespace Tabulate\Commands;

use \Tabulate\DB\Tables\Groups;
use \Tabulate\DB\Tables\Users;
use \Tabulate\DB\ChangeTracker;

class UpgradeCommand extends \Tabulate\Commands\CommandBase
{

    public function run()
    {
        $db = new \Tabulate\DB\Database();
        $this->installStructure($db);
        $this->installData($db);
        $this->write("Upgrade complete");
    }

    protected function installStructure(\Tabulate\DB\Database $db)
    {

        if (!$db->getTable('users', false)) {
            $this->write("Creating table 'users'");
            $db->query("CREATE TABLE `users` ("
                    . " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
                    . " `name` VARCHAR(200) NOT NULL UNIQUE,"
                    . " `password` VARCHAR(100) NOT NULL"
                    . ");");
        }
        if (!$db->getTable('users', false)) {
            $this->write("Creating table 'users'");
            $db->query("CREATE TABLE `users` ("
                    . " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
                    . " `name` VARCHAR(200) NOT NULL UNIQUE"
                    . ");");
        }
        if (!$db->getTable('groups', false)) {
            $this->write("Creating table 'groups'");
            $db->query("CREATE TABLE `groups` ("
                    . " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
                    . " `name` VARCHAR(200) NOT NULL UNIQUE"
                    . ");");
        }
        if (!$db->getTable('group_members', false)) {
            $this->write("Creating table 'group_members'");
            $db->query("CREATE TABLE `group_members` ("
                    . " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
                    . " `group` INT(10) UNSIGNED NOT NULL,"
                    . " FOREIGN KEY (`group`) REFERENCES `groups` (`id`),"
                    . " `user` INT(10) UNSIGNED NOT NULL,"
                    . " FOREIGN KEY (`user`) REFERENCES `users` (`id`)"
                    . ");");
        }
        if (!$db->getTable('grants', false)) {
            $this->write("Creating table 'grants'");
            $db->query("CREATE TABLE `grants` ("
                    . " `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,"
                    . " `group` INT(10) UNSIGNED NOT NULL,"
                    . " FOREIGN KEY (`group`) REFERENCES `groups` (`id`),"
                    . " `permission` VARCHAR(200) NOT NULL DEFAULT '*',"
                    . " `table_name` VARCHAR(100) NOT NULL DEFAULT '*' "
                    . ");");
        }
        if (!$db->getTable('changesets', false)) {
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
        if (!$db->getTable('changes', false)) {
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
        $db->reset();
    }

    protected function installData(\Tabulate\DB\Database $db)
    {
        // Can't log changes without a user (admin, in this case).
        // Can't grant permissions without a group (also admin).
        // ...so after these two have been created, we revert to normal.
//        $sql = "INSERT IGNORE INTO `users` (`id`,`name`) VALUES (:id1, :name1), (:id2, :name2)";
//        $params = [
//            ['id' => Users::ANON, 'name' => 'Anonymous'],
//            ['id' => Users::ADMIN, 'name' => 'Administrator'],
//        ];
//        $db->query($sql, $params);
//        if (!$db->getTable('users', false)->getRecord(Users::ADMIN)) {
//            $this->write("Inserting user 'Administrator'");
//            $db->getTable('users', false)->saveRecord(['id' => Users::ADMIN, 'name' => 'Administrator'], null, false);
//        }
//        if (!$db->getTable('groups', false)->getRecord(Groups::ADMINISTRATORS)) {
//            $this->write("Inserting group 'Administrators'");
//            $db->getTable('groups', false)->saveRecord(['id' => Groups::ADMINISTRATORS, 'name' => 'Administrators'], null, false);
//        }
        $db->query("INSERT IGNORE INTO `users` SET `id`=:id, `name`=:name", ['id' => Users::ADMIN, 'name' => 'Administrator']);
        $db->query("INSERT IGNORE INTO `groups` SET `id`=:id, `name`=:name", ['id' => Groups::ADMINISTRATORS, 'name' => 'Administrators']);
        $db->query("INSERT IGNORE INTO `group_members` SET `user`=:user, `group`=:group", ['user' => Users::ADMIN, 'group' => Groups::ADMINISTRATORS]);

        // Make sure Administrators can do anything (default is '*' for table and permission).
        $db->query("INSERT IGNORE INTO `grants` SET `group`=:group", ['group' => Groups::ADMINISTRATORS]);
//        $grants = $db->getTable('grants', false);
//        $grants->addFilter('group', '=', Groups::ADMINISTRATORS);
//        $grants->addFilter('table_name', '=', '*');
//        $grants->addFilter('permission', '=', '*');
//        if ($grants->getRecordCount() === 0) {
//            $grants->saveRecord(['group' => Groups::ADMINISTRATORS], null, false);
//        }
        $db->reset();
        // Start tracking changes now that there's a user to attribute it to.
        $changeTracker = new \Tabulate\DB\ChangeTracker($db);
        $changeTracker->openChangeset('Installation', true);

        // Create remaining default users and groups.
        if (!$db->getTable('users', false)->getRecord(Users::ANON)) {
            $this->write("Inserting user 'Anonymous'");
            $db->getTable('users', false)->saveRecord(['id' => Users::ANON, 'name' => 'Anonymous'], null, false);
        }
        if (!$db->getTable('groups', false)->getRecord(Groups::GENERAL_PUBLIC)) {
            $this->write("Inserting group 'General Public'");
            $db->getTable('groups', false)->saveRecord(['id' => Groups::GENERAL_PUBLIC, 'name' => 'General Public']);
        }

        // Add Administrator to the Administrators group.
        $groupMembers = $db->getTable('group_members', false);
        $groupMembers->addFilter('group', '=', Groups::ADMINISTRATORS);
        $groupMembers->addFilter('group', '=', Users::ADMIN);
        if ($groupMembers->getRecordCount() === 0) {
            $this->write("Adding user 'Administrator' to group 'Administrators'");
            $groupMembers->saveRecord(['group' => Groups::ADMINISTRATORS, 'user' => Users::ADMIN]);
        }

        // Finish up.
        $changeTracker->close_changeset();
    }
}
