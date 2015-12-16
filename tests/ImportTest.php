<?php

use Tabulate\Config;

class ImportTest extends TestBase
{

    /**
     * Save some CSV data to a file, and create a quasi-$_FILES entry for it.
     * @param string $data
     * @return string|array
     */
    private function saveDataFile($data)
    {
        $test_filename = Config::storageDirTmp('test') . '/' . uniqid() . '.csv';
        file_put_contents($test_filename, $data);
        $uploaded = array(
            'type' => 'text/csv',
            'file' => $test_filename,
        );
        return $uploaded;
    }

    /**
     * @testdox Rows can be imported from CSV.
     * @test
     */
    public function basicImport()
    {
        $testtypes_table = $this->db->getTable('test_types');
        $csv = '"ID","Title"' . "\r\n"
                . '"1","One"' . "\r\n"
                . '"2","Two"' . "\r\n";
        $uploaded = $this->saveDataFile($csv);
        $csv = new \Tabulate\CSV(null, $uploaded);
        $csv->loadData();
        $column_map = array('title' => 'Title');
        $csv->importData($testtypes_table, $column_map);
        // Make sure 2 records were imported.
        $this->assertEquals(2, $testtypes_table->getRecordCount());
        $rec1 = $testtypes_table->getRecord(1);
        $this->assertEquals('One', $rec1->title());
        // And that 1 changeset was created, with 4 changes. 1 changeset is from install-time.
        $sql = "SELECT COUNT(`id`) FROM `changesets`";
        $this->assertEquals(2, $this->db->query($sql)->fetchColumn());
        $sql = "SELECT COUNT(`id`) FROM `changes` WHERE `table_name` = 'test_types'";
        $this->assertEquals(4, $this->db->query($sql)->fetchColumn());
    }

    /**
     * @testdox Import rows that specify an existing PK will update existing records.
     * @test
     */
    public function primaryKey()
    {
        $testtable = $this->db->getTable('test_table');
        $rec1 = $testtable->saveRecord(array('title' => 'PK Test'));
        $this->assertEquals(1, $testtable->getRecordCount());
        $this->assertNull($rec1->description());

        // Add a field's value.
        $csv = '"ID","Title","Description"' . "\r\n"
                . '"1","One","A description"' . "\r\n";
        $uploaded = $this->saveDataFile($csv);
        $csv = new \Tabulate\CSV(null, $uploaded);
        $csv->loadData();
        $column_map = array('id' => 'ID', 'title' => 'Title', 'description' => 'Description');
        $csv->importData($testtable, $column_map);
        // Make sure there's still only one record, and that it's been updated.
        $this->assertEquals(1, $testtable->getRecordCount());
        $rec2 = $testtable->getRecord(1);
        $this->assertEquals('One', $rec2->title());
        $this->assertEquals('A description', $rec2->description());

        // Leave out a required field.
        $csv = '"ID","Description"' . "\r\n"
                . '"1","New description"' . "\r\n";
        $uploaded2 = $this->saveDataFile($csv);
        $csv2 = new \Tabulate\CSV(null, $uploaded2);
        $csv2->loadData();
        $column_map2 = array('id' => 'ID', 'description' => 'Description');
        $csv2->importData($testtable, $column_map2);
        // Make sure there's still only one record, and that it's been updated.
        $this->assertEquals(1, $testtable->getRecordCount());
        $rec3 = $testtable->getRecord(1);
        $this->assertEquals('One', $rec3->title());
        $this->assertEquals('New description', $rec3->description());
    }
}
