<?php

/**
 * This file contains only the RecordCounter class.
 *
 * @package Tabulate
 * @file
 */

namespace Tabulate\DB;

/**
 * A record counter takes care of counting and caching the records in a single
 * table.
 */
class RecordCounter
{

    /**
     * The table.
     * @var \Tabulate\DB\Table
     */
    protected $table;

    /**
     * Create a new RecordCounter.
     * @param \Tabulate\DB\Table $table The table to count.
     */
    public function __construct(\Tabulate\DB\Table $table)
    {
        $this->table = $table;
    }

    /**
     * Get the record count of this table. Will use a cached value only for base
     * tables and where there are no filters.
     * @return integer The record count.
     */
    public function getCount()
    {
        // Only cache if this is a base table and there are no filters.
        $canCache = $this->table->isTable() && count($this->table->get_filters()) === 0;

        if ($canCache) {
            if (isset($_SESSION[$this->sessionKey()])) {
                return $_SESSION[$this->sessionKey()];
            }
        }

        // Otherwise, run the COUNT() query.
        $pkCol = $this->table->getPkColumn();
        if ($pkCol instanceof Column) {
            $count_col = '`' . $this->table->getName() . '`.`' . $pkCol->getName() . '`';
        } else {
            $count_col = '*';
        }
        $sql = 'SELECT COUNT(' . $count_col . ') as `count` FROM `' . $this->table->getName() . '`';
        $params = $this->table->applyFilters($sql);
        $count = $this->table->getDatabase()->query($sql, $params)->fetchColumn();
        if ($canCache) {
            $_SESSION[$this->sessionKey()] = $count;
        }
        return $count;
    }

    /**
     * Empty the cached record count for this table.
     * @return void
     */
    public function clear()
    {
        if (isset($_SESSION[$this->sessionKey()])) {
            unset($_SESSION[$this->sessionKey()]);
        }
    }

    /**
     * Get the name of the session key under which this table's record count is stored.
     * @return string
     */
    public function sessionKey()
    {
        return $this->table->getName() . '_count';
    }
}
