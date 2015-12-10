<?php

namespace Tabulate\DB;

use Tabulate\DB\Database;
use Tabulate\DB\Table;
use Tabulate\DB\Record;

class ChangeTracker
{

    /** @var \Tabulate\DB\Database */
    protected $db;
    private static $current_changeset_id = false;
    private $current_changeset_comment = null;

    /** @var \Tabulate\DB\Record|false */
    private $old_record = false;

    /** @var boolean Whether the changeset should be closed after the first after_save() call. */
    private static $keep_changeset_open = false;

    public function __construct(Database $db, $comment = null)
    {
        $this->db = $db;
        $this->current_changeset_comment = $comment;
    }

    /**
     * When destroying a ChangeTracker object, close the current changeset
     * unless it has specifically been requested to be kept open.
     */
    public function __destruct()
    {
        if (!self::$keep_changeset_open) {
            $this->close_changeset();
        }
    }

    /**
     * Open a new changeset. If one is already open, this does nothing.
     * @param string $comment
     * @param boolean $keep_open Whether the changeset should be kept open (and manually closed) after after_save() is called.
     */
    public function open_changeset($comment, $keep_open = null)
    {
        $currentUser = new User($this->db);
        if (!is_null($keep_open)) {
            self::$keep_changeset_open = $keep_open;
        }
        if (!self::$current_changeset_id) {
            $data = array(
                'date_and_time' => date('Y-m-d H:i:s'),
                'user_id' => $currentUser->getId(),
                'comment' => $comment,
            );
            $changesetsTable = $this->db->get_table('changesets');
            $changesetsTable->save_record($data);
            //$ret = $this->db->query(self::changesets_name(), $data);
//            if ($ret === false) {
//                throw new Exception($this->db->last_error . ' -- Unable to open changeset');
//            }
            self::$current_changeset_id = $this->db->insert_id;
        }
    }

    /**
     * Close the current changeset.
     * @return void
     */
    public function close_changeset()
    {
        self::$current_changeset_id = false;
        $this->current_changeset_comment = null;
    }

    public function before_save(Table $table, $data, $pk_value)
    {
        // Don't save changes to the changes tables.
        if (in_array($table->getName(), $this->table_names())) {
            return false;
        }

        // Open a changeset if required.
        $this->open_changeset($this->current_changeset_comment);

        // Get the current (i.e. soon-to-be-old) data for later use.
        $this->old_record = $table->getRecord($pk_value);
    }

    public function after_save(Table $table, Record $new_record)
    {
        // Don't save changes to the changes tables.
        if (in_array($table->getName(), self::table_names())) {
            return false;
        }

        // Save a change for each changed column.
        foreach ($table->getColumns() as $column) {
            $col_name = ( $column->is_foreign_key() ) ? $column->getName() . Record::FKTITLE : $column->getName();
            $old_val = ( is_callable(array($this->old_record, $col_name)) ) ? $this->old_record->$col_name() : null;
            $new_val = $new_record->$col_name();
            if ($new_val == $old_val) {
                // Ignore unchanged columns.
                continue;
            }
            $data = array(
                'changeset_id' => self::$current_changeset_id,
                'change_type' => 'field',
                'table_name' => $table->getName(),
                'column_name' => $column->getName(),
                'record_ident' => $new_record->getPrimaryKey(),
            );
            // Daft workaround for https://core.trac.wordpress.org/ticket/15158
            if (!is_null($old_val)) {
                $data['old_value'] = $old_val;
            }
            if (!is_null($new_val)) {
                $data['new_value'] = $new_val;
            }
            // Save the change record.
            $this->db->insert($this->changes_name(), $data);
        }

        // Close the changeset if required.
        if (!self::$keep_changeset_open) {
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
    public static function table_names()
    {
        return array(self::changesets_name(), self::changes_name());
    }
}
