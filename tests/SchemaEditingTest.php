<?php

class SchemaEditingTest extends TestBase
{

    /**
     * @testdox It is possible to rename a table.
     * @test
     */
    public function renameTable()
    {
        $testTable = $this->db->getTable('test_table');
        $testTable->rename('testing_table');
        $testingTable = $this->db->getTable('testing_table');
        $this->assertEquals('testing_table', $testingTable->getName());
        $this->assertEquals('testing_table', $testTable->getName());
        $this->assertFalse($this->db->getTable('test_table'));
    }

    /**
     * @testdox When renaming a table, its history comes along with it.
     * @test
     */
    public function renameTableHistory()
    {
        // Create a record in the table and check its history size.
        $test_table = $this->db->getTable('test_table');
        $rec1 = $test_table->saveRecord(array('title' => 'Testing'));
        $this->assertEquals(1, $rec1->id());
        $this->assertCount(4, $rec1->getChanges());

        // Rename the table, and make sure the history is the same size.
        $test_table->rename('testing_table');
        $testing_table = $this->db->getTable('testing_table');
        $rec2 = $testing_table->getRecord(1);
        $this->assertCount(4, $rec2->getChanges());
    }

    public function tearDown()
    {
        $this->db->query("DROP TABLE IF EXISTS `testing_table`;");
        parent::tearDown();
    }
}
