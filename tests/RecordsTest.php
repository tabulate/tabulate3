<?php

class RecordsTest extends TestBase
{

    /**
     * @testdox Getting the *FKTITLE() variant of a foreign key returns the title of the foreign record.
     * @test
     */
    public function related()
    {
        $testTable = $this->db->getTable('test_table');
        $typeRec = $this->db->getTable('test_types')->saveRecord(array('title' => 'Type 1'));
        $dataRec = $testTable->saveRecord(array('title' => 'Rec 1', 'type_id' => $typeRec->id()));
        $this->assertEquals('Type 1', $dataRec->type_idFKTITLE());
        $referecingRecs = $typeRec->get_referencing_records($testTable, 'type_id');
        $this->assertCount(1, $referecingRecs);
        $referecingRec = array_pop($referecingRecs);
        $this->assertEquals('Rec 1', $referecingRec->title());
    }

    /**
     * @testdox Where there is no unique column, the 'title' is just the foreign key.
     * @test
     */
    public function titles()
    {
        $testTable = $this->db->getTable('test_table');
        $this->assertEmpty($testTable->getUniqueColumns());
        $this->assertEquals('id', $testTable->getTitleColumn()->getName());
        $rec = $testTable->saveRecord(array('title' => 'Rec 1', 'description' => 'Lorem ipsum.'));
        $this->assertEquals('[ 1 | Rec 1 | Lorem ipsum. | 1 |  |  |  | 5.60 |  |  ]', $rec->getTitle());
    }

    /**
     * @testdox
     * @test
     */
    public function recordCounts()
    {
        $testTable = $this->db->getTable('test_table');

        // Initially empty.
        $this->assertEquals(0, $testTable->getRecordCount());

        // Add one.
        $rec1 = $testTable->saveRecord(array('title' => 'Rec 1', 'description' => 'Testing.'));
        $this->assertEquals(1, $testTable->getRecordCount());

        // Add 2.
        $testTable->saveRecord(array('title' => 'Rec 2'));
        $testTable->saveRecord(array('title' => 'Rec 3'));
        $this->assertEquals(3, $testTable->getRecordCount());

        // Add 50.
        for ($i = 0; $i < 50; $i ++) {
            $testTable->saveRecord(array('title' => "Record $i"));
        }
        $this->assertEquals(53, $testTable->getRecordCount());

        // Make sure it still works with filters applied.
        $testTable->addFilter('title', 'like', 'Record');
        $this->assertEquals(50, $testTable->getRecordCount());
        $testTable->resetFilters();
        $testTable->addFilter('description', 'like', 'Testing');
        $this->assertEquals(1, $testTable->getRecordCount());
        $testTable->resetFilters();
        $testTable->addFilter('description', 'not empty', '');
        $this->assertEquals(1, $testTable->getRecordCount());
        $testTable->resetFilters();
        $testTable->addFilter('description', 'empty', '');
        $this->assertEquals(52, $testTable->getRecordCount());
        $testTable->resetFilters();

        // Delete a record.
        $testTable->deleteRecord($rec1->id());
        $this->assertEquals(52, $testTable->getRecordCount());
    }

    /**
     * @testdox Cache the record counts for base tables which have no filters applied.
     * @test
     */
    public function countStoreSome()
    {
        $testTable = $this->db->getTable('test_table');
        // Add some records.
        for ($i = 0; $i < 50; $i ++) {
            $testTable->saveRecord(array('title' => "Record $i"));
        }
        $this->assertEquals(50, $testTable->getRecordCount());
        $transient_name = 'test_table_count';
        $this->assertEquals(50, $_SESSION[$transient_name]);
        // With filters applied, the transient value shouldn't change.
        $testTable->addFilter('title', 'like', 'Record 1');
        $this->assertEquals(11, $testTable->getRecordCount());
        $this->assertEquals(50, $_SESSION[$transient_name]);
    }

    /**
     * Bug description: when editing a record, and a filter has been applied to
     * a referenced table, the count is the *filtered* count (and thus
     * incorrect).
     * @test
     */
    public function countRelated()
    {
        // Create 50 types.
        $typesNormal = $this->db->getTable('test_types');
        for ($i = 0; $i < 50; $i ++) {
            $typesNormal->saveRecord(array('title' => "Type $i"));
        }
        $this->assertEquals(50, $typesNormal->getRecordCount());

        // The test_table should know there are 50.
        $testTable = $this->db->getTable('test_table');
        $typeCol = $testTable->getColumn('type_id');
        $referencedTables = $testTable->getReferencedTables(true);
        $typesReferenced = $referencedTables['type_id'];
        $this->assertNotSame($typesNormal, $typesReferenced);
        $this->assertCount(0, $typesNormal->get_filters());
        $this->assertCount(0, $typesReferenced->get_filters());
        $this->assertEquals(50, $typesNormal->getRecordCount());
        $this->assertEquals(50, $typesReferenced->getRecordCount());
        $this->assertEquals(50, $typeCol->getReferencedTable()->getRecordCount());

        // Now apply a filter to the test_types table.
        $typesNormal->addFilter('title', 'like', '20');
        $this->assertCount(1, $typesNormal->get_filters());
        $this->assertCount(0, $typesReferenced->get_filters());
        $this->assertEquals(1, $typesNormal->getRecordCount());
        $this->assertEquals(50, $typesReferenced->getRecordCount());
        $this->assertEquals(1, $typeCol->getReferencedTable()->getRecordCount());
    }
}
