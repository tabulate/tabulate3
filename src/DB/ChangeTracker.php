<?php

namespace Tabulate\DB;

use Tabulate\DB\Database;
use Tabulate\DB\Table;
use Tabulate\DB\Record;

class ChangeTracker
{

    /** @var \Tabulate\DB\Database */
    protected $db;
    private static $currentChangesetId = false;
    private $currentChangesetComment = null;

    /** @var \Tabulate\DB\Record|false */
    private $oldRecord = false;

    /** @var boolean Whether the changeset should be closed after the first after_save() call. */
    private static $keepChangesetOpen = false;

    public function __construct(Database $db, $comment = null)
    {
        $this->db = $db;
        $this->currentChangesetComment = $comment;
    }

    /**
     * When destroying a ChangeTracker object, close the current changeset
     * unless it has specifically been requested to be kept open.
     */
    public function __destruct()
    {
        if (!self::$keepChangesetOpen) {
            $this->closeChangeset();
        }
    }

    /**
     * Open a new changeset. If one is already open, this does nothing.
     * @param string $comment
     * @param boolean $keepOpen Whether the changeset should be kept open (and manually closed) after after_save() is called.
     */
    public function openChangeset($comment, $keepOpen = null)
    {
        if (!is_null($keepOpen)) {
            self::$keepChangesetOpen = $keepOpen;
        }
        if (!self::$currentChangesetId) {
            $sql = "INSERT INTO changesets SET "
                    . " `date_and_time` = :date_and_time,"
                    . " `user` = :user,"
                    . " `comment` = :comment ";
            $data = array(
                'date_and_time' => date('Y-m-d H:i:s'),
                'user' => $this->db->getCurrentUser(),
                'comment' => $comment,
            );
            $this->db->query($sql, $data);
            self::$currentChangesetId = $this->db->lastInsertId();
        }
    }

    /**
     * Close the current changeset.
     * @return void
     */
    public function closeChangeset()
    {
        self::$keepChangesetOpen = false;
        self::$currentChangesetId = false;
        $this->currentChangesetComment = null;
    }

    public function beforeSave(Table $table, $data, $pk_value)
    {
        // Don't save changes to the changes tables.
        if (in_array($table->getName(), ['changesets', 'changes'])) {
            return false;
        }

        // Open a changeset if required.
        $this->openChangeset($this->currentChangesetComment);

        // Get the current (i.e. soon-to-be-old) data for later use.
        $this->oldRecord = $table->getRecord($pk_value);
    }

    public function after_save(Table $table, Record $new_record)
    {
        // Don't save changes to the changes tables.
        if (in_array($table->getName(), self::tableNames())) {
            return false;
        }

        $changesetsTable = $this->db->getTable('changesets', false);
        if (!$changesetsTable || !$changesetsTable->getRecord(self::$currentChangesetId)) {
            throw new \Exception("Failed to open changeset #".self::$currentChangesetId);
        }

        // Save a change for each changed column.
        foreach ($table->getColumns() as $column) {
            $col_name = ( $column->isForeignKey() ) ? $column->getName() . Record::FKTITLE : $column->getName();
            $old_val = ( is_callable(array($this->oldRecord, $col_name)) ) ? $this->oldRecord->$col_name() : null;
            $new_val = $new_record->$col_name();
            if ($new_val == $old_val) {
                // Ignore unchanged columns.
                continue;
            }
            $sql = "INSERT INTO changes SET "
                    . " `changeset` = :changeset, "
                    . " `change_type` = 'field', "
                    . " `table_name` = :table_name, "
                    . " `column_name` = :column_name, "
                    . " `record_ident` = :record_ident, "
                    . " `old_value` = :old_value, "
                    . " `new_value` = :new_value ";
            $data = array(
                'changeset' => self::$currentChangesetId,
                'table_name' => $table->getName(),
                'column_name' => $column->getName(),
                'record_ident' => $new_record->getPrimaryKey(),
                'old_value' => $old_val,
                'new_value' => $new_val,
            );
            // Save the change record.
            $this->db->query($sql, $data);
        }

        // Close the changeset if required.
        if (!self::$keepChangesetOpen) {
            $this->closeChangeset();
        }
    }

    public static function changesets_name()
    {
        return 'changesets';
    }

    public static function changes_name()
    {
        return 'changes';
    }

    /**
     * Get a list of the names used by the change-tracking subsystem.
     * @global wpdb $wpdb
     * @return array|string
     */
    public static function tableNames()
    {
        return array(self::changesets_name(), self::changes_name());
    }
}
