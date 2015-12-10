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
class RecordCounter {

    /**
     * The table.
     * @var \WordPress\Tabulate\DB\Table
     */
    protected $table;

    /**
     * The time-to-live of the cached record count, in seconds.
     * @var integer
     */
    protected $transient_expiration;

    /**
     * Create a new RecordCounter.
     * @param \WordPress\Tabulate\DB\Table $table The table to count.
     */
    public function __construct(\Tabulate\DB\Table $table) {
        $this->table = $table;
        $this->transient_expiration = 5 * 60;
    }

    /**
     * Get the record count of this table. Will use a cached value only for base
     * tables and where there are no filters.
     * @return integer The record count.
     */
    public function get_count() {
        // Only cache if this is a base table and there are no filters.
        $can_cache = $this->table->is_table() && count($this->table->get_filters()) === 0;

        if ($can_cache) {
            if (isset($_SESSION[$this->transient_name()])) {
                return $_SESSION[$this->transient_name()];
            }
        }

        // Otherwise, run the COUNT() query.
        $pk_col = $this->table->get_pk_column();
        if ($pk_col instanceof Column) {
            $count_col = '`' . $this->table->getName() . '`.`' . $pk_col->getName() . '`';
        } else {
            $count_col = '*';
        }
        $sql = 'SELECT COUNT(' . $count_col . ') as `count` FROM `' . $this->table->getName() . '`';
        $params = $this->table->apply_filters($sql);
        if (!empty($params)) {
            $sql = $this->table->getDatabase()->query($sql, $params);
        }
        $count = $this->table->getDatabase()->query($sql)->fetchColumn();
        if ($can_cache) {
            $_SESSION[$this->transient_name()] = $count;
        }
        return $count;
    }

    /**
     * Empty the cached record count for this table.
     * @return void
     */
    public function clear() {
        delete_transient($this->transient_name());
    }

    /**
     * Get the name of the transient under which this table's record count is
     * stored. All Tabulate transients start with TABULATE_SLUG.
     * @return string
     */
    public function transient_name() {
        return $this->table->getName() . '_count';
    }

}
