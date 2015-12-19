<?php

namespace Tabulate\DB;

class Record
{

    /** @var Table */
    protected $table;

    /** @var \stdClass */
    protected $data;

    const FKTITLE = 'FKTITLE';

    /**
     * Create a new Record object.
     * @param \Tabulate\DB\Table $table The table object.
     * @param array $data The data of this record.
     */
    public function __construct(Table $table, $data = array())
    {
        $this->table = $table;
        $this->data = (object) $data;
    }

    public function __set($name, $value)
    {
        $this->data->$name = $value;
    }

    /**
     * Set multiple columns' values.
     * @param type $data
     */
    public function set_multiple($data)
    {
        if (!is_array($data)) {
            return;
        }
        foreach ($data as $col => $datum) {
            $this->$col = $datum;
        }
    }

    /**
     * Get a column's value. If suffixed with 'FKTITLE', then get the title of
     * the foreign record (where applicable).
     * @param string $name The column name.
     * @param array $args [Parameter not used]
     * @return string|boolean
     */
    public function __call($name, $args)
    {

        // Foreign key 'title' values.
        $useTitle = substr($name, -strlen(self::FKTITLE)) == self::FKTITLE;
        if ($useTitle) {
            $name = substr($name, 0, -strlen(self::FKTITLE));
            $col = $this->get_col($name);
            if ($col->isForeignKey() && !empty($this->data->$name)) {
                $referencedTable = $col->getReferencedTable();
                $fkRecord = $referencedTable->getRecord($this->data->$name);
                $fkTitleCol = $referencedTable->getTitleColumn();
                $fkTitleColName = $fkTitleCol->getName();
                if ($fkTitleCol->isForeignKey()) {
                    // Use title if the FK's title column is also an FK.
                    $fkTitleColName .= self::FKTITLE;
                }
                return $fkRecord->$fkTitleColName();
            }
        }
        $col = $this->get_col($name);

        // Booleans
        if ($col->isBoolean()) {
            // Numbers are fetched from the DB as strings.
            if ($this->data->$name === '1') {
                return true;
            } elseif ($this->data->$name === '0') {
                return false;
            } else {
                return null;
            }
        }

        // Standard column values.
        if (isset($this->data->$name)) {
            return $this->data->$name;
        }
    }

    /**
     * Get a column of this record's table, optionally throwing an Exception if
     * it doesn't exist.
     * @param boolean $required True if this should throw an Exception.
     * @return \Tabulate\DB\Column The column.
     * @throws \Exception If the column named doesn't exist.
     */
    protected function get_col($name, $required = true)
    {
        $col = $this->table->getColumn($name);
        if ($required && $col === false) {
            throw new \Exception("Unable to get column $name on table " . $this->table->getName());
        }
        return $col;
    }

    public function __toString()
    {
        return print_r($this->data, true);
    }

    /**
     * Get the value of this record's primary key, or false if it doesn't have
     * one.
     *
     * @return string|false
     */
    public function getPrimaryKey()
    {
        if ($this->table->getPkColumn()) {
            $pk_col_name = $this->table->getPkColumn()->getName();
            if (isset($this->data->$pk_col_name)) {
                return $this->data->$pk_col_name;
            }
        }
        return false;
    }

    /**
     * Get the value of this Record's title column.
     * @return string
     */
    public function getTitle()
    {
        $title_col = $this->table->getTitleColumn();
        if ($title_col !== $this->table->getPkColumn()) {
            $title_col_name = $title_col->getName();
            return $this->data->$title_col_name;
        } else {
            $title_parts = array();
            foreach ($this->table->getColumns() as $col) {
                $col_name = $col->getName() . self::FKTITLE;
                $title_parts[] = $this->$col_name();
            }
            return '[ ' . join(' | ', $title_parts) . ' ]';
        }
    }

    /**
     * Get the record that is referenced by this one from the column given.
     *
     * @param string $column_name
     * @return boolean|\Tabulate\DB\Record
     */
    public function getReferencedRecord($column_name)
    {
        if (!isset($this->data->$column_name)) {
            return false;
        }
        return $this->table
                        ->getColumn($column_name)
                        ->getReferencedTable()
                        ->getRecord($this->data->$column_name);
    }

    /**
     * Get a list of records that reference this record in one of their columns.
     *
     * @param string|\Tabulate\DB\Table $foreign_table
     * @param string|\Tabulate\DB\Column $foreign_column
     * @param boolean $with_pagination Whether to only return the top N records.
     * @return \Tabulate\DB\Record[]
     */
    public function get_referencing_records($foreign_table, $foreign_column, $with_pagination = true)
    {
        $foreign_table->resetFilters();
        $foreign_table->addFilter($foreign_column, '=', $this->getPrimaryKey(), true);
        return $foreign_table->getRecords($with_pagination);
    }

    /**
     * Get most recent changes.
     * @return \PDOStatement
     */
    public function getChanges($lim = 10)
    {
        $limit = (int) $lim;
        $sql = "SELECT cs.id AS changeset_id, c.id AS change_id, date_and_time, "
                . "u.name, table_name, record_ident, column_name, old_value, "
                . "new_value, comment "
                . "FROM `changes` c "
                . "  JOIN `changesets` cs ON (c.changeset = cs.id) "
                . "  JOIN `users` u ON (u.id = cs.user) "
                . "WHERE table_name = :table AND record_ident = :ident "
                . "ORDER BY date_and_time DESC, cs.id DESC "
                . "LIMIT $limit";
        $params = array('table' => $this->table->getName(), 'ident' => $this->getPrimaryKey());
        return $this->table->getDatabase()->query($sql, $params)->fetchAll();
    }
}
