<?php

use Tabulate\DB\Tables\Users;

class TestBase extends PHPUnit_Framework_TestCase
{

    /** @var \Tabulate\DB\Database */
    protected $db;

    public function setUp()
    {
        parent::setUp();
        \Eloquent\Asplode\Asplode::install();

        // Install.
        $upgrade = new \Tabulate\Commands\UpgradeCommand();
        $upgrade->run();
        $this->db = new Tabulate\DB\Database();

        // Create some testing tables and link them together.
        $this->db->query('DROP TABLE IF EXISTS `test_table`');
        $this->db->query('CREATE TABLE `test_table` ('
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
        $this->db->query('DROP TABLE IF EXISTS `test_types`');
        $this->db->query('CREATE TABLE `test_types` ('
                . ' id INT(10) AUTO_INCREMENT PRIMARY KEY,'
                . ' title VARCHAR(100) NOT NULL UNIQUE'
                . ');'
        );
        $this->db->query('ALTER TABLE `test_table` '
                . ' ADD FOREIGN KEY ( `type_id` )'
                . ' REFERENCES `test_types` (`id`)'
                . ' ON DELETE CASCADE ON UPDATE CASCADE;'
        );

        $this->db->reset();
        $this->db->setCurrentUser(Users::ADMIN);
    }

    public function tearDown()
    {

        // Close any still-open changeset.
        $changeTracker = new \Tabulate\DB\ChangeTracker($this->db);
        $changeTracker->closeChangeset();

        // Remove all tables.
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($this->db->getTableNames(false) as $tbl) {
            $this->db->query("DROP TABLE IF EXISTS `$tbl`");
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        parent::tearDown();
    }
}
