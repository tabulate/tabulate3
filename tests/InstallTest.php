<?php

use Tabulate\DB\Database;
use Tabulate\DB\Tables\Users;

class InstallTest extends TestBase
{

    public function testBasics()
    {
        $db = new Database();
        // Make sure the default records were created.
        $db->setCurrentUser(Users::ADMIN);
        $this->assertEquals(2, $db->getTable('users')->getRecordCount());
        $this->assertEquals(2, $db->getTable('groups')->getRecordCount());
        $this->assertEquals(2, $db->getTable('group_members')->getRecordCount());

        // The anon user can't see anything.
        $db->setCurrentUser(Users::ANON);
        $this->assertEquals(Users::ANON, $db->getCurrentUser());
        $this->assertEmpty($db->getTables());

        // The admin user can see everything.
        $db->setCurrentUser(Users::ADMIN);
        $expectedTables = ['changes', 'changesets', 'grants', 'group_members', 'groups', 'sessions', 'test_table',
            'test_types', 'users', 'report_sources', 'reports'];
        $this->assertEquals($expectedTables, $db->getTableNames(), '', 0, 1, true, true);

    }
}
