<?php

use Tabulate\DB\Database;
use Tabulate\DB\Tables\Users;

class SchemaTest extends TestBase
{

    /**
     * @testdox Tables can be linked to each other; one is the referenced table, the other the referencing.
     * @test
     */
    public function references()
    {
        // That test_table references test_types
        $testTable = $this->db->getTable('test_table');
        $referencedTables = $testTable->getReferencedTables(true);
        $referencedTable = array_pop($referencedTables);
        $this->assertEquals('test_types', $referencedTable->getName());

        // And the other way around.
        $type_table = $this->db->getTable('test_types');
        $referencing_tables = $type_table->getReferencingTables();
        $referencing_table = array_pop($referencing_tables);
        $this->assertEquals('test_table', $referencing_table['table']->getName());
    }

    /**
     * @testdox More than one table can reference a table, and even a single table can reference a table more than once.
     * @test
     */
    public function multipleReferences()
    {
        $this->db->query('DROP TABLE IF EXISTS `test_widgets`');
        $this->db->query('CREATE TABLE `test_widgets` ('
                . ' id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' title VARCHAR(100) NOT NULL UNIQUE,'
                . ' type_1_a INT(10) UNSIGNED,'
                . ' type_1_b INT(10) UNSIGNED,'
                . ' type_2 INT(10) UNSIGNED'
                . ');'
        );
        $this->db->query('DROP TABLE IF EXISTS `test_widget_types_1`');
        $this->db->query('CREATE TABLE `test_widget_types_1` ('
                . ' id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' title VARCHAR(100) NOT NULL'
                . ');'
        );
        $this->db->query('DROP TABLE IF EXISTS `test_widget_types_2`');
        $this->db->query('CREATE TABLE `test_widget_types_2` ('
                . ' id INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,'
                . ' title VARCHAR(100) NOT NULL'
                . ');'
        );
        $this->db->query('ALTER TABLE `test_widgets` '
                . ' ADD FOREIGN KEY ( `type_1_a` ) REFERENCES `test_widget_types_1` (`id`),'
                . ' ADD FOREIGN KEY ( `type_1_b` ) REFERENCES `test_widget_types_1` (`id`),'
                . ' ADD FOREIGN KEY ( `type_2` ) REFERENCES `test_widget_types_2` (`id`);'
        );
        $this->db->reset();
        $table = $this->db->getTable('test_widgets');

        // Check references from Widgets to Types.
        $referencedTables = $table->getReferencedTables();
        $this->assertCount(3, $referencedTables);
        $this->assertArrayHasKey('type_1_a', $referencedTables);
        $this->assertArrayHasKey('type_1_b', $referencedTables);
        $this->assertArrayHasKey('type_2', $referencedTables);

        // Check references from Types to Widgets.
        $type1 = $this->db->getTable('test_widget_types_1');
        $referencingTables = $type1->getReferencingTables();
        $this->assertCount(2, $referencingTables);
    }

    /**
     * @testdox A not-null column "is required" but if it has a default value then no value need be set when saving.
     * @test
     */
    public function requiredColumns()
    {
        // 'widget_size' is a not-null column with a default value.
        $test_table = $this->db->getTable('test_table');
        $widget_size_col = $test_table->getColumn('widget_size');
        $this->assertFalse($widget_size_col->is_required());
        // 'title' is a not-null column with no default.
        $title_col = $test_table->getColumn('title');
        $this->assertTrue($title_col->is_required());

        // Create a basic record.
        $widget = array(
            'title' => 'Test Item'
        );
        $test_table->saveRecord($widget);
        $this->assertEquals(1, $test_table->getRecordCount());
        $widget_records = $test_table->get_records();
        $widget_record = array_shift($widget_records);
        $this->assertEquals(5.6, $widget_record->widget_size());
    }

    /**
     * @testdox Null values can be inserted, and existing values can be updated to be null.
     * @test
     */
    public function nullValues()
    {
        $testTable = $this->db->getTable('test_table');

        // Start with null.
        $widget = array(
            'title' => 'Test Item',
            'ranking' => null,
        );
        $record = $testTable->saveRecord($widget);
        $this->assertEquals('Test Item', $record->title());
        $this->assertNull($record->ranking());

        // Update to a number.
        $widget = array(
            'title' => 'Test Item',
            'ranking' => 12,
        );
        $record = $testTable->saveRecord($widget, 1);
        $this->assertEquals(12, $record->ranking());

        // Then update to null again.
        $widget = array(
            'title' => 'Test Item',
            'ranking' => null,
        );
        $record = $testTable->saveRecord($widget, 1);
        $this->assertNull($record->ranking());
    }

    /**
     * @testdox Only NOT NULL text fields are allowed to have empty strings.
     * @test
     */
    public function emptyString()
    {
        $testTable = $this->db->getTable('test_table');
        // Title is NOT NULL.
        $this->assertTrue($testTable->getColumn('title')->allowsEmptyString());
        // Description is NULLable.
        $this->assertFalse($testTable->getColumn('description')->allowsEmptyString());

        // Check with some data.
        $data = array(
            'title' => '',
            'description' => '',
        );
        $record = $testTable->saveRecord($data);
        $this->assertSame('', $record->title());
        $this->assertNull($record->description());
    }

    /**
     * @testdox Date and time values are saved correctly.
     * @test
     */
    public function dateAndTime()
    {
        $test_table = $this->db->getTable('test_table');
        $rec = $test_table->saveRecord(array(
            'title' => 'Test',
            'a_date' => '1980-01-01',
            'a_year' => '1980',
        ));
        $this->assertEquals('1980-01-01', $rec->a_date());
        $this->assertEquals('1980', $rec->a_year());
    }

    /**
     * @testdox VARCHAR columns can be used as Primary Keys.
     * @test
     */
    public function varcharPrimaryKey()
    {
        $this->db->query('DROP TABLE IF EXISTS `test_varchar_pk`');
        $this->db->query('CREATE TABLE `test_varchar_pk` ('
                . ' ident VARCHAR(10) PRIMARY KEY,'
                . ' description TEXT'
                . ');'
        );
        $this->db->reset();
        $tbl = $this->db->getTable('test_varchar_pk');
        $this->assertEquals('ident', $tbl->getPkColumn()->getName());
        $rec = $tbl->saveRecord(array('ident' => 'TEST123'));
        $this->assertEquals('TEST123', $rec->getPrimaryKey());
    }

    /**
     * @testdox Numeric and Decimal columns.
     * @test
     */
    public function decimal()
    {
        $test_table = $this->db->getTable('test_table');
        $rec = $test_table->saveRecord(array(
            'title' => 'Decimal Test',
            'a_numeric' => '123.4',
        ));
        $this->assertEquals('123.40', $rec->a_numeric());
        $comment = $test_table->getColumn('a_numeric')->get_comment();
        $this->assertEquals('NUMERIC is the same as DECIMAL.', $comment);
    }

    /**
     * @link https://github.com/tabulate/tabulate/issues/21
     * @test
     */
    public function githubIssue21()
    {
        $this->db->query('DROP TABLE IF EXISTS `test_pb_servicio`');
        $sql = "CREATE TABLE IF NOT EXISTS test_pb_servicio (
			s_id VARCHAR(4) NOT NULL COMMENT 'código identificador del servicio',
			s_nom VARCHAR(80) NOT NULL COMMENT 'nombre del servicio',
			s_des TEXT COMMENT 'texto con información/condiciones/descripción del servicio',
			s_pre NUMERIC (10,2) NOT NULL DEFAULT '0' COMMENT 'precio por persona del servicio',
			s_iva NUMERIC (3,2) NOT NULL DEFAULT '0.21' COMMENT 'IVA del artículo -por defecto el 21%-',
			s_dto NUMERIC (3,2) NOT NULL DEFAULT '0' COMMENT 'si es 0.5, tiene un descuento del 50%',
			s_tsini TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'timestamp con la fecha de creación del servicio',
			s_tsfin TIMESTAMP COMMENT 'timestamp con la fecha de la desactivación del servicio',
			s_img VARCHAR(160) COMMENT 'url o dirección del fichero de imagen asociado al servicio',
			s_bitblo ENUM('0','1') NOT NULL DEFAULT '0' COMMENT 'activación(0)/bloqueo(1) del servicio',
			CONSTRAINT servicio_pk PRIMARY KEY(s_id)
			)
			ENGINE InnoDB
			COMMENT 'producto concertado con un proveedor o proveedores';";
        $this->db->query($sql);
        $this->db->reset();
        $tbl = $this->db->getTable('test_pb_servicio');
        $this->assertTrue($tbl->getColumn('s_pre')->is_numeric());
        $rec = $tbl->saveRecord(array(
            's_id' => 'TEST',
            's_nom' => 'A name',
            's_pre' => 123.45,
            's_bitblo' => '1',
        ));
        $this->assertEquals(123.45, $rec->s_pre());
        $this->assertEquals(0.21, $rec->s_iva());
        $s_pre = $tbl->getColumn('s_pre');
        $this->assertTrue($s_pre->is_numeric());
        $this->assertEquals(1, $rec->s_bitblo());
    }

    /**
     * @testdox It should be possible to provide a value for a (non-autoincrementing) PK.
     * @test
     */
    public function timestampPrimaryKey()
    {
        $this->db->query('DROP TABLE IF EXISTS `provided_pk`');
        $this->db->query("CREATE TABLE `provided_pk` ( "
                . "  `code` VARCHAR(10) NOT NULL PRIMARY KEY, "
                . "  `title` VARCHAR(100) "
                . ");");
        $this->db->reset();
        $tbl = $this->db->getTable('provided_pk');
        $rec = $tbl->saveRecord(array('code' => 'TEST'));
        $this->assertEquals('TEST', $rec->getPrimaryKey());
    }
}
