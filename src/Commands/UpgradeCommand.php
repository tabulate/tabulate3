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
        $this->write("Confirming existance of administrative user, group, and grant");

        // Can't log changes without a user (admin, in this case). So we create a user manually.
        $db->query("INSERT IGNORE INTO `users` SET `id`=:id, `name`=:name", ['id' => Users::ADMIN, 'name' => 'Administrator']);
        // Then we want to create a second user (anon), but this time recording changes.
        // The change-tracker needs to know about permissions, so before creating the 2nd user that we need to grant permission to admin.
        // Permissions are granted to groups, not users, so we put admin in an admin group (manually).
        $db->query("INSERT IGNORE INTO `groups` SET `id`=:id, `name`=:name", ['id' => Groups::ADMINISTRATORS, 'name' => 'Administrators']);
        $db->query("INSERT IGNORE INTO `group_members` SET `user`=:user, `group`=:group", ['user' => Users::ADMIN, 'group' => Groups::ADMINISTRATORS]);
        // Now we can grant everything (on everything) to the admin group.
        $db->query("INSERT IGNORE INTO `grants` SET `group`=:group", ['group' => Groups::ADMINISTRATORS]);
        // And finally 'reset' the DB so it knows about the above new records.
        $db->reset();

        // Start tracking changes now that there's a user to attribute it to.
        $db->setCurrentUser(Users::ADMIN);
        $changeTracker = new \Tabulate\DB\ChangeTracker($db);
        $changeTracker->openChangeset('Installation', true);

        // Create remaining default users and groups.
        if (!$db->getTable('users')->getRecord(Users::ANON)) {
            $this->write("Inserting user 'Anonymous'");
            $db->getTable('users')->saveRecord(['id' => Users::ANON, 'name' => 'Anonymous']);
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
