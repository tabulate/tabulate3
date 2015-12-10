<?php

class TestBase extends PHPUnit_Framework_TestCase
{

    public function setUp()
    {
        parent::setUp();

        // Install.
        $upgrade = new \Tabulate\Commands\UpgradeCommand();
        $upgrade->run();

        $db = new Tabulate\DB\Database();
        // Create some testing tables and link them together.
        $db->query('DROP TABLE IF EXISTS `test_table`');
        $db->query('CREATE TABLE `test_table` ('
                . ' id INT(10) AUTO_INCREMENT PRIMARY KEY,'
                . ' title VARCHAR(100) NOT NULL,'
                . ' description TEXT NULL,'
                . ' active BOOLEAN NULL DEFAULT TRUE,'
                . ' a_date DATE NULL,'
                . ' a_year YEAR NULL,'
                . ' type_id INT(10) NULL DEFAULT NULL,'
                . ' widget_size DECIMAL(10,2) NOT NULL DEFAULT 5.6,'
                . ' ranking INT(3) NULL DEFAULT NULL,'
                . ' a_numeric NUMERIC(7,2) NULL DEFAULT NULL COMMENT "NUMERIC is the same as DECIMAL."'
                . ');'
        );
        $db->query('DROP TABLE IF EXISTS `test_types`');
        $db->query('CREATE TABLE `test_types` ('
                . ' id INT(10) AUTO_INCREMENT PRIMARY KEY,'
                . ' title VARCHAR(100) NOT NULL UNIQUE'
                . ');'
        );
        $db->query('ALTER TABLE `test_table` '
                . ' ADD FOREIGN KEY ( `type_id` )'
                . ' REFERENCES `test_types` (`id`)'
                . ' ON DELETE CASCADE ON UPDATE CASCADE;'
        );
    }

    public function tearDown()
    {
        // Remove test tables.
        $db = new Tabulate\DB\Database();
        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        $db->query('DROP TABLE IF EXISTS `test_types`');
        $db->query('DROP TABLE IF EXISTS `test_table`');
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

//        $ct = new \WordPress\Tabulate\DB\ChangeTracker();
//        $ct->close_changeset();

        parent::tearDown();
    }
}
