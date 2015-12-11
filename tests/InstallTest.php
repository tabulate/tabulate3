<?php

use Tabulate\DB\Database;
use Tabulate\DB\Tables\Users;

class InstallTest extends TestBase
{

    public function testBasics()
    {
        $db = new Database();
        // Make sure the default records were created.
        $this->assertEquals(2, $db->getTable('users', false)->getRecordCount());
        $this->assertEquals(2, $db->getTable('groups', false)->getRecordCount());

        // The anon user can't see anything.
        $this->assertEquals(Users::ANON, $db->getCurrentUser());
        $this->assertEmpty($db->getTables());

        // The admin user can see everything.
        $db->setCurrentUser(Users::ADMIN);
        $this->assertEquals(['users', 'groups', 'changesets'], $db->getTableNames(), '', 0, 1, true, true);

        // Initially we can't see any tables.
        // Become the Administrator.
//        $admin = $db->getTable('users', false)->getRecord(Tabulate\DB\Tables\Users::ADMIN);
//        $db->setCurrentUser($admin->id());
//        var_dump($admin);
        //$admin = $db->getTable('users')->getRecord(1);
    }
}
