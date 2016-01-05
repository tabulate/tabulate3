<?php

use \Tabulate\DB\ChangeTracker;
use \Tabulate\DB\Tables\Groups;
use \Tabulate\DB\Tables\Grants;
use \Tabulate\DB\Tables\Users;

class ChangeTrackerTest extends TestBase
{

    /**
     * @testdox Saving a new record creates a changeset and some changes.
     * @test
     */
    public function basic()
    {
        // test_table: { id, title }
        $testTable = $this->db->getTable('test_types');
        $rec = $testTable->saveRecord(array('title' => 'One'));

        // Initial changeset and changes.
        $changes1 = $rec->getChanges();
        $this->assertCount(2, $changes1);
        // Check the second change record.
        $changes1Rec = array_pop($changes1);
        $this->assertequals('title', $changes1Rec->column_name);
        $this->assertNull($changes1Rec->old_value);
        $this->assertEquals('One', $changes1Rec->new_value);

        // Modify one value, and inspect the new change record.
        $rec2 = $testTable->saveRecord(array('title' => 'Two'), $rec->id());
        $changes2 = $rec2->getChanges();
        $this->assertCount(3, $changes2);
        $changes2Rec = array_shift($changes2);
        $this->assertequals('title', $changes2Rec->column_name);
        $this->assertequals('One', $changes2Rec->old_value);
        $this->assertEquals('Two', $changes2Rec->new_value);
    }

    /**
     * @testdox A changeset can have an associated comment.
     * @test
     */
    public function changesetComment()
    {
        $testTypes = $this->db->getTable('test_types');
        $rec = $testTypes->saveRecord(array('title' => 'One', 'changeset_comment' => 'Testing.'));
        $changes = $rec->getChanges();
        $change = array_pop($changes);
        $this->assertEquals("Testing.", $change->comment);
    }

    /**
     * @testdox A user who can only create records in one table can still use the change-tracker (i.e. creating changesets is not influenced by standard grants).
     * @test
     */
    public function minimalGrants()
    {
        $this->db->setCurrentUser(Users::ANON);
        $this->db->query("INSERT IGNORE INTO `grants` SET `group`=:group, `table_name`='test_table'", ['group' => Groups::GENERAL_PUBLIC]);
        $this->db->reset();

        // Assert that the permissions are set as we want them.
        $this->assertTrue($this->db->checkGrant(Grants::CREATE, 'test_table'));
        $this->assertFalse($this->db->checkGrant(Grants::CREATE, 'changesets'));
        $this->assertFalse($this->db->checkGrant(Grants::CREATE, 'changes'));

        // Succcessfully save a record.
        $test_table = $this->db->getTable('test_table');
        $rec = $test_table->saveRecord(array('title' => 'One', 'changeset_comment' => 'Testing.'));
        $this->assertEquals(1, $rec->id());
    }

    /**
     * @testdox Foreign Keys are tracked by their titles (not their PKs).
     * @test
     */
    public function foreignKeyTitles()
    {
        // Set up data.
        $test_types = $this->db->getTable('test_types');
        $type = $test_types->saveRecord(array('title' => 'The Type'));
        $test_table = $this->db->getTable('test_table');
        $rec = $test_table->saveRecord(array('title' => 'A Record', 'type_id' => $type->id()));
        // Test.
        $changes = $rec->getChanges();
        $change = $changes[3];
        $this->assertEquals("type_id", $change->column_name);
        $this->assertEquals("The Type", $change->new_value);
    }

    /**
     * @testdox A record can be deleted, and it's history along with it.
     */
    public function delete()
    {
        $testTypes = $this->db->getTable('test_types');
        $changesets = $this->db->getTable(ChangeTracker::changesets_name());

        // Create two, to make sure only one is deleted.
        $testTypes->saveRecord(array('title' => 'First Type'));
        $testTypes->saveRecord(array('title' => 'Second Type'));

        // Make sure we've got the right number of changesets (one is from the install process).
        $this->assertEquals(2, $testTypes->getRecordCount());
        $this->assertEquals(3, $this->db->query('SELECT COUNT(*) FROM changesets')->fetchColumn());
        $this->assertEquals(3, $changesets->getRecordCount());

        // Delete a record, and both counts should go down by one.
        $testTypes->deleteRecord(2);
        $this->assertEquals(1, $testTypes->getRecordCount());
        $this->assertEquals(2, $this->db->query('SELECT COUNT(*) FROM changesets')->fetchColumn());
        $this->assertEquals(2, $changesets->getRecordCount());
    }
}
