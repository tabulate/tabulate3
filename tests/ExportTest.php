<?php

class ExportTest extends TestBase
{

    /**
     * @testdox A table can be exported to CSV.
     * @test
     */
    public function basic_export()
    {
        // Add some data to the table.
        $testTable = $this->db->getTable('test_types');
        $testTable->saveRecord(array('title' => 'One'));
        $testTable->saveRecord(array('title' => 'Two'));
        $filename = $testTable->export();
        $this->assertFileExists($filename);
        $csv = '"ID","Title"' . "\r\n"
                . '"1","One"' . "\r\n"
                . '"2","Two"' . "\r\n";
        $this->assertEquals($csv, file_get_contents($filename));
    }

    /**
     * @testdox Point colums are exported as WKT.
     * @test
     */
    public function point_wkt()
    {
        $this->db->query('DROP TABLE IF EXISTS `point_export_test`');
        $this->db->query('CREATE TABLE `point_export_test` ('
                . ' id INT(10) AUTO_INCREMENT PRIMARY KEY,'
                . ' title VARCHAR(100) NOT NULL,'
                . ' geo_loc POINT NULL DEFAULT NULL'
                . ');'
        );
        $this->db->reset();
        $testTable = $this->db->getTable('point_export_test');
        $testTable->saveRecord(array('title' => 'Test', 'geo_loc' => 'POINT(10.1 20.2)'));
        $filename = $testTable->export();
        $this->assertFileExists($filename);
        $csv = '"ID","Title","Geo Loc"' . "\r\n"
                . '"1","Test","POINT(10.1 20.2)"' . "\r\n";
        $this->assertEquals($csv, file_get_contents($filename));

        // Check nullable.
        $testTable->saveRecord(array('title' => 'Test 2', 'geo_loc' => null));
        $filename2 = $testTable->export();
        $this->assertFileExists($filename2);
        $csv2 = '"ID","Title","Geo Loc"' . "\r\n"
                . '"1","Test","POINT(10.1 20.2)"' . "\r\n"
                . '"2","Test 2",""' . "\r\n";
        $this->assertEquals($csv2, file_get_contents($filename2));
    }
}
