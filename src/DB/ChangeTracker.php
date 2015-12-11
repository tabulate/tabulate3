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
            $this->close_changeset();
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
            $data = array(
                'date_and_time' => date('Y-m-d H:i:s'),
                'user' => $this->db->getCurrentUser(),
                'comment' => $comment,
            );
            $changesetsTable = $this->db->getTable('changesets', false);
            if ($changesetsTable === false) {
                var_dump($this->db->getTableNames(false));
                throw new \Exception("Unable to save changeset");
            }
            $changeset = $changesetsTable->saveRecord($data, null, false);
            self::$currentChangesetId = $changeset->id();
        }
    }

    /**
     * Close the current changeset.
     * @return void
     */
    public function close_changeset()
    {
        self::$currentChangesetId = false;
        $this->currentChangesetComment = null;
    }

    public function beforeSave(Table $table, $data, $pk_value)
    {
        // Don't save changes to the changes tables.
        if (in_array($table->getName(), $this->tableNames())) {
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

        // Save a change for each changed column.
        foreach ($table->getColumns() as $column) {
            $col_name = ( $column->isForeignKey() ) ? $column->getName() . Record::FKTITLE : $column->getName();
            $old_val = ( is_callable(array($this->oldRecord, $col_name)) ) ? $this->oldRecord->$col_name() : null;
            $new_val = $new_record->$col_name();
            if ($new_val == $old_val) {
                // Ignore unchanged columns.
                continue;
            }
            $data = array(
                'changeset' => self::$currentChangesetId,
                'change_type' => 'field',
                'table_name' => $table->getName(),
                'column_name' => $column->getName(),
                'record_ident' => $new_record->getPrimaryKey(),
                'old_value' => $old_val,
                'new_value' => $new_val,
            );
            // Save the change record.
            $this->db->getTable('changes', false)->saveRecord($data, null, false);
        }

        // Close the changeset if required.
        if (!self::$keepChangesetOpen) {
            $this->close_changeset();
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
