<?php

namespace Tabulate\DB;

class Table {

    /** @static A base table. */
    const TYPE_TABLE = 'table';

    /** @static A database view, possibly of multiple base tables. */
    const TYPE_VIEW = 'view';

    /** @var \Tabulate\DB\Database The database to which this table belongs. */
    protected $database;

    /** @var string The name of this table. */
    protected $name;

    /** @var string This table's comment. False until initialised. */
    protected $comment = false;

    /** @var string Either self::TYPE_TABLE or self::TYPE_VIEW. */
    protected $type;

    /** @var string The SQL statement used to create this table. */
    protected $defining_sql;

    /** @var string The SQL statement most recently saved by $this->get_records() */
    protected $saved_sql;

    /** @var string[] The statement parameters most recently saved by $this->get_records() */
    protected $saved_parameters;

    /** @var \WordPress\Tabulate\DB\Table[] Array of tables referred to by columns in this one. */
    protected $referenced_tables;

    /** @var string[] The names (only) of tables referenced by columns in this one. */
    protected $referenced_table_names;

    /** @var int Each joined table gets a unique alias, based on this. */
    protected $alias_count = 1;

    /**
     * @var \WordPress\Tabulate\DB\Column[] Array of column names and objects for all of the
     * columns in this table.
     */
    protected $columns;

    /** @var array */
    protected $filters = array();

    /** @var array Permitted operators. */
    protected $operators = array(
        'like' => 'contains',
        'not like' => 'does not contain',
        '=' => 'is',
        '!=' => 'is not',
        'empty' => 'is empty',
        'not empty' => 'is not empty',
        'in' => 'is one of',
        'not in' => 'is not one of',
        '>=' => 'is greater than or equal to',
        '>' => 'is greater than',
        '<=' => 'is less than or equal to',
        '<' => 'is less than'
    );

    /**
     * @var string|false The name of the column by which to order, or false if
     * no column has been set.
     */
    protected $order_by = false;

    /** @var string The direction in which results should be ordered. */
    protected $order_dir = 'ASC';

    /** @var RecordCounter */
    protected $record_counter;

    /** @var integer The current page number. */
    protected $current_page_num = 1;

    /** @var integer The number of records to show on each page. */
    protected $records_per_page = 30;

    /**
     * Create a new database table object.
     *
     * @param \WordPress\Tabulate\DB\Database $database The database to which this table belongs.
     * @param string $name The name of the table.
     */
    public function __construct($database, $name) {
        $this->database = $database;
        $this->name = $name;
        $this->columns = array();
        $columns = $this->database->query("SHOW FULL COLUMNS FROM `$name`");
        foreach ($columns as $column_info) {
            $column = new Column($this->database, $this, $column_info);
            $this->columns[$column->getName()] = $column;
        }
        $this->record_counter = new RecordCounter($this);
    }

    /**
     * Add a filter.
     * @param string|\WordPress\Tabulate\DB\Column $column Column name or object.
     * @param string $operator
     * @param string $value
     * @param boolean $force Whether to transform the value, for FKs.
     */
    public function add_filter($column, $operator, $value, $force = false) {
        // Allow Columns to be passed in.
        if ($column instanceof Column) {
            $column = $column->getName();
        }
        // Validate the parts of the filter.
        $valid_columm = in_array($column, array_keys($this->get_columns()));
        $valid_operator = in_array($operator, array_keys($this->operators));
        $emptyValueAllowed = (strpos($operator, 'empty') === false && !empty($value));
        $valid_value = (strpos($operator, 'empty') !== false) || $emptyValueAllowed;
        if ($valid_columm && $valid_operator && $valid_value) {
            // Save the filter for later application.
            $this->filters[] = array(
                'column' => $column,
                'operator' => $operator,
                'value' => $value,
                'force' => $force,
            );
        }
    }

    /**
     * Add multiple filters.
     */
    public function add_filters($filters) {
        foreach ($filters as $filter) {
            $column = (isset($filter['column'])) ? $filter['column'] : false;
            $operator = (isset($filter['operator'])) ? $filter['operator'] : false;
            $value = (isset($filter['value'])) ? $filter['value'] : false;
            $this->add_filter($column, $operator, $value);
        }
    }

    public function get_filters() {
        return $this->filters;
    }

    protected function get_fk_join_clause($table, $alias, $column) {
        return 'LEFT OUTER JOIN `' . $table->getName() . '` AS f' . $alias
                . ' ON (`' . $this->getName() . '`.`' . $column->getName() . '` '
                . ' = `f' . $alias . '`.`' . $table->get_pk_column()->getName() . '`)';
    }

    /**
     * Apply the stored filters to the supplied SQL.
     *
     * @param string $sql The SQL to modify
     * @return array Parameter values, in the order of their occurence in $sql
     */
    public function apply_filters(&$sql) {

        $params = array();
        $param_num = 1; // Incrementing parameter suffix, to permit duplicate columns.
        $where_clause = '';
        $join_clause = '';
        foreach ($this->filters as $filter) {
            $f_column = $filter['column'];
            $param_name = $filter['column'] . $param_num;

            // Filters on foreign keys need to work on the FKs title column.
            $column = $this->columns[$f_column];
            if ($column->is_foreign_key() && !$filter['force']) {
                $join = $this->join_on($column);
                $f_column = $join['column_alias'];
                $join_clause .= $join['join_clause'];
            } else {
                // The result of join_on() above is quoted, so this must also be.
                $f_column = "`" . $this->getName() . "`.`$f_column`";
            }

            // LIKE or NOT LIKE
            if ($filter['operator'] == 'like' || $filter['operator'] == 'not like') {
                $where_clause .= " AND CONVERT($f_column, CHAR) " . strtoupper($filter['operator']) . " %s ";
                $params[$param_name] = '%' . trim($filter['value']) . '%';
            } // Equals or does-not-equal
            elseif ($filter['operator'] == '=' || $filter['operator'] == '!=') {
                $where_clause .= " AND $f_column " . strtoupper($filter['operator']) . " %s ";
                $params[$param_name] = trim($filter['value']);
            } // IS EMPTY
            elseif ($filter['operator'] == 'empty') {
                $where_clause .= " AND ($f_column IS NULL OR $f_column = '')";
            } // IS NOT EMPTY
            elseif ($filter['operator'] == 'not empty') {
                $where_clause .= " AND ($f_column IS NOT NULL AND $f_column != '')";
            } // IN or NOT IN
            elseif ($filter['operator'] == 'in' || $filter['operator'] == 'not in') {
                $values = explode("\n", $filter['value']);
                $placeholders = array();
                foreach ($values as $vid => $val) {
                    $placeholders[] = "%s";
                    $params[$param_name . '_' . $vid] = trim($val);
                }
                $negate = ( $filter['operator'] == 'not in' ) ? 'NOT' : '';
                $where_clause .= " AND ($f_column $negate IN (" . join(", ", $placeholders) . "))";
            } // Other operators. They're already validated in $this->addFilter()
            else {
                $where_clause .= " AND ($f_column " . $filter['operator'] . " %s)";
                $params[$param_name] = trim($filter['value']);
            }

            $param_num++;
        } // end foreach filter
        // Add clauses into SQL
        if (!empty($where_clause)) {
            $where_clause_pattern = '/^(.* FROM .*?)((?:GROUP|HAVING|ORDER|LIMIT|$).*)$/m';
            $where_clause = substr($where_clause, 5); // Strip leading ' AND'.
            $where_clause = "$1 $join_clause WHERE $where_clause $2";
            $sql = preg_replace($where_clause_pattern, $where_clause, $sql);
        }

        return $params;
    }

    /**
     * Get the name of the column by which this table should be ordered.
     *
     * There is no default for this, as some orderings can result in quite slow
     * queries and it's best to let the user request this. It used to order by
     * the title column by default.
     *
     * @return string
     */
    public function get_order_by() {
        return $this->order_by;
    }

    /**
     * Change the column by which this table is ordered.
     * @param string $order_by The name of the column to order by.
     */
    public function set_order_by($order_by) {
        if (in_array($order_by, array_keys($this->columns))) {
            $this->order_by = $order_by;
        }
    }

    public function get_order_dir() {
        if (empty($this->order_dir)) {
            $this->order_dir = 'ASC';
        }
        return $this->order_dir;
    }

    /**
     * Set the direction of ordering.
     * @param string $order_dir Either 'ASC' or 'DESC' (case insensitive).
     */
    public function set_order_dir($order_dir) {
        if (in_array(strtoupper($order_dir), array('ASC', 'DESC'))) {
            $this->order_dir = $order_dir;
        }
    }

    /**
     * For a given foreign key column, get an alias and join clause for selecting
     * against that column's foreign values. If the column is not a foreign key,
     * the alias will just be the qualified column name, and the join clause will
     * be the empty string.
     *
     * @param Column $column The FK column
     * @return array Array with 'join_clause' and 'column_alias' keys
     */
    public function join_on($column) {
        $join_clause = '';
        $column_alias = '`' . $this->getName() . '`.`' . $column->getName() . '`';
        if ($column->is_foreign_key()) {
            $fk1_table = $column->get_referenced_table();
            $fk1_title_column = $fk1_table->get_title_column();
            $join_clause .= ' LEFT OUTER JOIN `' . $fk1_table->getName() . '` AS f' . $this->alias_count
                    . ' ON (`' . $this->getName() . '`.`' . $column->getName() . '` '
                    . ' = `f' . $this->alias_count . '`.`' . $fk1_table->get_pk_column()->getName() . '`)';
            $column_alias = "`f$this->alias_count`.`" . $fk1_title_column->getName() . "`";
            // FK is also an FK?
            if ($fk1_title_column->is_foreign_key()) {
                $fk2_table = $fk1_title_column->get_referenced_table();
                $fk2_title_column = $fk2_table->get_title_column();
                $join_clause .= ' LEFT OUTER JOIN `' . $fk2_table->getName() . '` AS ff' . $this->alias_count
                        . ' ON (f' . $this->alias_count . '.`' . $fk1_title_column->getName() . '` '
                        . ' = ff' . $this->alias_count . '.`' . $fk1_table->get_pk_column()->getName() . '`)';
                $column_alias = "`ff$this->alias_count`.`" . $fk2_title_column->getName() . "`";
            }
            $this->alias_count++;
        }
        return array('join_clause' => $join_clause, 'column_alias' => $column_alias);
    }

    /**
     * Get rows, optionally with pagination.
     * @param boolean $with_pagination Whether to only return the top N results.
     * @param boolean $save_sql Whether to store the SQL for later use.
     * @return \WordPress\Tabulate\DB\Record[]
     */
    public function get_records($with_pagination = true, $save_sql = false) {
        // Build basic SELECT statement.
        $sql = 'SELECT ' . $this->columns_sql_select() . ' FROM `' . $this->getName() . '`';

        // Ordering.
        if (false !== $this->get_order_by()) {
            $order_by = $this->get_column($this->get_order_by());
            if ($order_by) {
                $order_by_join = $this->join_on($order_by);
                $sql .= $order_by_join['join_clause'] . ' ORDER BY ' . $order_by_join['column_alias'] . ' ' . $this->get_order_dir();
            }
        }

        $params = $this->apply_filters($sql);

        // Then limit to the ones on the current page.
        if ($with_pagination) {
            $records_per_page = $this->get_records_per_page();
            $sql .= ' LIMIT ' . $records_per_page;
            if ($this->page() > 1) {
                $sql .= ' OFFSET ' . ($records_per_page * ($this->get_current_page_num() - 1));
            }
        }

        // Run query and save SQL
        $rows = $this->database->query($sql, $params)->fetchAll();

        $records = array();
        foreach ($rows as $row) {
            $records[] = new Record($this, $row);
        }

        if ($save_sql) {
            $this->saved_sql = $sql;
            $this->saved_parameters = $params;
        }

        return $records;
    }

    public function get_current_page_num() {
        return $this->current_page_num;
    }

    public function set_current_page_num($current_page_num) {
        $this->current_page_num = $current_page_num;
    }

    public function get_records_per_page() {
        return $this->records_per_page;
    }

    public function set_records_per_page($recordsPerPage) {
        $this->records_per_page = $recordsPerPage;
    }

    public function get_saved_query() {
        return array(
            'sql' => $this->saved_sql,
            'parameters' => $this->saved_parameters
        );
    }

    /**
     * Get the SQL for SELECTing all columns in this table.
     * @return string
     */
    private function columns_sql_select() {
        $select = array();
        $table_name = $this->getName();
        foreach ($this->get_columns() as $col_name => $col) {
            if ($col->get_type() == 'point') {
                $select[] = "AsText(`$table_name`.`$col_name`) AS `$col_name`";
            } else {
                $select[] = "`$table_name`.`$col_name`";
            }
        }
        return join(', ', $select);
    }

    /**
     * Get a single record as an associative array.
     *
     * @param string $pk_val The value of the PK of the record to get.
     * @return Record|false The record object, or false if it wasn't found.
     */
    public function getRecord($pk_val) {
        $pk_column = $this->get_pk_column();
        if (!$pk_column) {
            return false;
        }
        $sql = "SELECT " . $this->columns_sql_select() . " "
                . "FROM `" . $this->getName() . "` "
                . "WHERE `" . $pk_column->getName() . "` = %s "
                . "LIMIT 1";
        $params = array($pk_val);
        $row = $this->database->query($sql, $params)->fetch();
        return ( $row ) ? new Record($this, $row) : false;
    }

    /**
     * Get a bare record with only default values.
     *
     * @return Record
     */
    public function get_default_record() {
        $row = array();
        foreach ($this->get_columns() as $col) {
            $row[$col->getName()] = $col->get_default();
        }
        $record = new Record($this, $row);
        return $record;
    }

    public function has_changes_recorded() {
        return !in_array($this->getName(), ChangeTracker::table_names());
    }

    /**
     * Get this table's name.
     *
     * @return string The name of this table.
     */
    public function getName() {
        return $this->name;
    }

    /**
     * Whether this is a base table or a view.
     *
     * @return string Either `Table::TYPE_TABLE` or `Table::TYPE_VIEW`.
     */
    public function get_type() {
        return $this->type;
    }

    /**
     * Whether this table is a table (as opposed to a view).
     * @return boolean
     */
    public function is_table() {
        return $this->get_type() == self::TYPE_TABLE;
    }

    /**
     * Whether this table is a view.
     * @return boolean
     */
    public function is_view() {
        return $this->get_type() == self::TYPE_VIEW;
    }

    /**
     * Whether this view is updatable. Always true for base tables. Currently
     * always false for all views.
     * @todo Implement this.
     * @link https://dev.mysql.com/doc/refman/5.6/en/view-updatability.html
     */
    public function is_updatable() {
        if ($this->is_table()) {
            return true;
        }
        return false;
    }

    /**
     * Get this table's title. This is the title-cased name, if not otherwise
     * defined.
     *
     * @return string The title
     */
    public function getTitle() {
        return \Tabulate\Text::titlecase($this->getName());
    }

    /**
     * Get a list of permitted operators.
     *
     * @return string[] List of operators.
     */
    public function get_operators() {
        return $this->operators;
    }

    public function get_page_count() {
        return ceil($this->count_records() / $this->get_records_per_page());
    }

    /**
     * Get or set the current page.
     *
     * @param integer $page
     * @return integer Current page
     */
    public function page($page = false) {
        if ($page !== false) {
            $this->current_page_num = $page;
        } else {
            return $this->current_page_num;
        }
    }

    /**
     * Get the number of rows in the current filtered set.
     * @return integer
     */
    public function count_records() {
        return $this->record_counter->get_count();
    }

    /**
     * @return string Full filesystem path to resulting temporary file.
     */
    public function export() {

        $columns = array();
        $column_headers = array();
        $join_clause = '';
        foreach ($this->columns as $col_name => $col) {
            if ($col->is_foreign_key()) {
                $col_join = $this->join_on($col);
                $column_name = $col_join['column_alias'];
                $join_clause .= $col_join['join_clause'];
            } elseif ($col->get_type() === 'point') {
                $columns[] = "IF(`$this->name`.`$col_name` IS NOT NULL, AsText(`$this->name`.`$col_name`), '') AS `$col_name`";
            } else {
                $column_name = "`$this->name`.`$col_name`";
            }
            if ($col->get_type() !== 'point' && isset($column_name)) {
                $columns[] = "REPLACE(IFNULL($column_name, ''),CONCAT(CHAR(13),CHAR(10)),CHAR(10))"; // 13 = \r and 10 = \n
            }
            $column_headers[] = $col->getTitle();
        }

        // Build basic SELECT statement
        $sql = 'SELECT ' . join(',', $columns)
                . ' FROM `' . $this->getName() . '` ' . $join_clause;

        $params = $this->apply_filters($sql);

        $filename = get_temp_dir() . uniqid('tabulate_') . '.csv';
        if (DIRECTORY_SEPARATOR == '\\') {
            // Clean Windows slashes, for MySQL's benefit.
            $filename = str_replace('\\', '/', $filename);
        }
        // Clear out any old copy.
        if (file_exists($filename)) {
            unlink($filename);
        }
        // Build the final SQL, appending the column headers in a UNION.
        $sql = 'SELECT "' . join('", "', $column_headers) . '"'
                . ' UNION ' . $sql
                . ' INTO OUTFILE "' . $filename . '" '
                . ' FIELDS TERMINATED BY ","'
                . ' ENCLOSED BY \'"\''
                . ' ESCAPED BY \'"\''
                . ' LINES TERMINATED BY "\r\n"';
        // Execute the SQL (hiding errors for now).
        $wpdb = $this->database->get_wpdb();
        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }
        $wpdb->hide_errors();
        $wpdb->query($sql);
        // Make sure it exported.
        if (!file_exists($filename)) {
            $msg = "Unable to create temporary export file:<br /><code>$filename</code>";
            Exception::wp_die($msg, 'Export failed', $wpdb->last_error, $sql); // WPCS: XSS OK.
        }
        $wpdb->show_errors();
        // Give the filename back to the controller, to send to the client.
        return $filename;
    }

    /**
     * Get one of this table's columns.
     * @param string $name The column name.
     * @return \WordPress\Tabulate\DB\Column|false The column, or false if it's not found.
     */
    public function get_column($name) {
        return ( isset($this->columns[$name]) ) ? $this->columns[$name] : false;
    }

    /**
     * Get a list of this table's columns, optionally constrained by their type.
     *
     * @param string $type Only return columns of this type.
     * @return \WordPress\Tabulate\DB\Column[] This table's columns.
     */
    public function get_columns($type = null) {
        if (is_null($type)) {
            return $this->columns;
        } else {
            $out = array();
            foreach ($this->get_columns() as $col) {
                if ($col->get_type() === $type) {
                    $out[$col->getName()] = $col;
                }
            }
            return $out;
        }
    }

    /**
     * Get the table comment text; for views, this returns '(View)'.
     * @return string
     */
    public function get_comment() {
        if (!$this->comment) {
            $sql = $this->get_defining_sql();
            $comment_pattern = '/.*\)(?:.*COMMENT[\w=]*\'(.*)\')?/si';
            preg_match($comment_pattern, $sql, $matches);
            $this->comment = ( isset($matches[1]) ) ? $matches[1] : '';
            $this->comment = str_replace("''", "'", $this->comment);
        }
        if (empty($this->comment) && $this->is_view()) {
            $this->comment = '(View)';
        }
        return $this->comment;
    }

    /**
     * Get a list of all the unique columns in this table.
     * @return \WordPress\Tabulate\DB\Column[]
     */
    public function get_unique_columns() {
        $cols = array();
        foreach ($this->get_columns() as $column) {
            if ($column->is_unique()) {
                $cols[] = $column;
            }
        }
        return $cols;
    }

    /**
     * Get the first unique-keyed column.
     * If there is no unique non-PK column then just use the PK.
     *
     * @return \WordPress\Tabulate\DB\Column
     */
    public function get_title_column() {
        // Try to get the first non-PK unique key.
        foreach ($this->get_columns() as $column) {
            if ($column->is_unique() && !$column->is_primary_key()) {
                return $column;
            }
        }
        // But if that fails, just use the primary key.
        return $this->get_pk_column();
    }

    /**
     * Get the SQL statement used to create this table, as given by the 'SHOW
     * CREATE TABLE' command.
     *
     * @return string The SQL statement used to create this table.
     * @throws Exception If the table or view is not found.
     */
    public function get_defining_sql() {
        if (!isset($this->defining_sql)) {
            $defining_sql = $this->database->query("SHOW CREATE TABLE `$this->name`")->fetch();
            if (isset($defining_sql->{'Create Table'})) {
                $defining_sql = $defining_sql->{'Create Table'};
                $this->type = self::TYPE_TABLE;
            } elseif (isset($defining_sql->{'Create View'})) {
                $defining_sql = $defining_sql->{'Create View'};
                $this->type = self::TYPE_VIEW;
            } else {
                throw new Exception('Table or view not found: ' . $this->name);
            }
            $this->defining_sql = $defining_sql;
        }
        return $this->defining_sql;
    }

    /**
     * Get this table's Primary Key column.
     *
     * @return \WordPress\Tabulate\DB\Column|false The PK column or false if there isn't one.
     */
    public function get_pk_column() {
        foreach ($this->get_columns() as $column) {
            if ($column->is_primary_key()) {
                return $column;
            }
        }
        return false;
    }

    /**
     * Get a list of this table's foreign keys and the tables to which they refer.
     * This does <em>not</em> take into account a user's permissions (i.e. the
     * name of a table which the user is not allowed to read may be returned).
     * @param boolean $instantiate Whether to instantiate the Table objects (or just return their names).
     * @return string[]|Table[] The list of <code>column_name => table_name|Table</code> pairs.
     */
    public function get_referenced_tables($instantiate = false) {

        // Extract the FK info from the CREATE TABLE statement.
        if (!is_array($this->referenced_tables)) {
            $this->referenced_table_names = array();
            $definingSql = $this->get_defining_sql();
            $foreignKeyPattern = '|FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)`|';
            preg_match_all($foreignKeyPattern, $definingSql, $matches);
            if (isset($matches[1]) && count($matches[1]) > 0) {
                foreach (array_combine($matches[1], $matches[2]) as $colName => $tabName) {
                    $this->referenced_table_names[$colName] = $tabName;
                }
            }
        }

        if ($instantiate) {
            $this->referenced_tables = array();
            foreach ($this->referenced_table_names as $refCol => $ref_tab) {
                $this->referenced_tables[$refCol] = new Table($this->get_database(), $ref_tab); // $this->get_database()->get_table( $ref_tab );
            }
        }

        return ($instantiate) ? $this->referenced_tables : $this->referenced_table_names;
    }

    /**
     * Get a list of tables with foreign keys referring here, and which of their columns are the FKs.
     *
     * @return array With keys 'table' and 'column'.
     */
    public function get_referencing_tables() {
        $out = array();
        // For all tables in the Database...
        foreach ($this->get_database()->get_tables() as $table) {
            // ...get a list of the tables they reference.
            $foreign_tables = $table->get_referenced_tables();
            foreach ($foreign_tables as $foreign_column => $referenced_table_name) {
                // If this table is a referenced table, collect the table from which it's referenced.
                if ($referenced_table_name === $this->getName()) {
                    $out[$table->getName() . '.' . $foreign_column] = array(
                        'table' => $table,
                        'column' => $foreign_column,
                    );
                }
            }
        }
        return $out;
    }

    /**
     * Get a list of the names of the foreign keys in this table.
     *
     * @return string[] Names of foreign key columns in this table.
     */
    public function get_foreign_key_names() {
        return array_keys($this->get_referenced_tables(false));
    }

    /**
     * Get the database to which this table belongs.
     *
     * @return Database The database object.
     */
    public function get_database() {
        return $this->database;
    }

    /**
     * Get a string representation of this table; a succinct summary of its
     * columns and their types, keys, etc.
     *
     * @return string A summary of this table.
     */
    public function __toString() {
        $col_count = count($this->get_columns());
        $out = "\n";
        $out .= '+-----------------------------------------+' . "\n";
        $out .= '| ' . $this->getName() . ' (' . $col_count . ' columns)' . "\n";
        $out .= '+-----------------------------------------+' . "\n";
        foreach ($this->get_columns() as $column) {
            $out .= "| $column \n";
        }
        $out .= '+-----------------------------------------+' . "\n\n";
        return $out;
    }

    /**
     * Get an XML representation of the structure of this table.
     *
     * @return DOMElement The XML 'table' node.
     */
    public function to_xml() {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $table = $dom->createElement('table');
        $dom->appendChild($table);
        $name = $dom->createElement('name');
        $name->appendChild($dom->createTextNode($this->name));
        $table->appendChild($name);
        foreach ($this->get_columns() as $column) {
            $table->appendChild($dom->importNode($column->toXml(), true));
        }
        return $table;
    }

    /**
     * Get a JSON representation of the structure of this table.
     *
     * @return string
     */
    public function to_json() {
        $json = new Services_JSON();
        $metadata = array();
        foreach ($this->get_columns() as $column) {
            $metadata[] = array(
                'name' => $column->getName(),
            );
        }
        return $json->encode($metadata);
    }

    /**
     * Remove all filters.
     *
     * @return void
     */
    public function reset_filters() {
        $this->filters = array();
    }

    /**
     * Delete a record and its associated change-tracker records.
     * @param string $pk_value The value of the primary key of the record to delete.
     * @return void
     * @throws Exception When the user doesn't have permission, or any error occurs deleting the record.
     */
    public function delete_record($pk_value) {
        // Check permission.
        if (!Grants::current_user_can(Grants::DELETE, $this->getName())) {
            throw new Exception('You do not have permission to delete data from this table.');
        }
        $rec = $this->getRecord($pk_value);
        $wpdb = $this->database->get_wpdb();
        $wpdb->hide_errors();
        $del = $wpdb->delete($this->getName(), array($this->get_pk_column()->getName() => $pk_value));
        if (!$del) {
            throw new Exception($wpdb->last_error);
        }
        foreach ($rec->get_changes() as $change) {
            $where_1 = array('table_name' => $this->getName(), 'record_ident' => $pk_value);
            $wpdb->delete(ChangeTracker::changes_name(), $where_1);
            $where_2 = array('id' => $change->changeset_id);
            $wpdb->delete(ChangeTracker::changesets_name(), $where_2);
        }
        $this->record_counter->clear();
    }

    /**
     * Save data to this table. If a primary key value is given, that row will be
     * updated; otherwise, a new row will be inserted.
     *
     * @param array  $data The data to insert.
     * @param string $pk_value The value of the record's PK. Null if the record doesn't exist.
     * @return \WordPress\Tabulate\DB\Record The updated or inserted record.
     * @throws Exception If the user doesn't have permission, or something else has gone wrong.
     */
    public function save_record($data, $pk_value = null) {
        // Changeset only created here if not already in progress.
        $changeset_comment = isset($data['changeset_comment']) ? $data['changeset_comment'] : null;
        $change_tracker = new ChangeTracker($this->get_database()->get_wpdb(), $changeset_comment);

        $columns = $this->get_columns();

        /*
         * Go through all data and clean it up before saving.
         */
        $sql_values = array();
        foreach ($data as $field => $value) {
            // Make sure this column exists in the DB.
            if (!isset($columns[$field])) {
                unset($data[$field]);
                continue;
            }
            $column = $this->get_column($field);

            if ($column->is_auto_increment()) {
                // Auto-incrementing columns.
                ; // Do nothing (don't set $sql_values item).
            } elseif ($column->is_boolean()) {
                // Boolean values.
                $zeroValues = array(0, '0', false, 'false', 'FALSE', 'off', 'OFF', 'no', 'NO');
                if (( null === $value || '' === $value ) && $column->nullable()) {
                    $data[$field] = null;
                    $sql_values[$field] = 'NULL';
                } elseif (in_array($value, $zeroValues, true)) {
                    $data[$field] = false;
                    $sql_values[$field] = '0';
                } else {
                    $data[$field] = true;
                    $sql_values[$field] = '1';
                }
            } elseif (!$column->allows_empty_string() && '' === $value && $column->nullable()) {
                // Empty strings.
                $data[$field] = null;
                $sql_values[$field] = 'NULL';
            } elseif (is_null($value) && $column->nullable()) {
                // Nulls.
                $data[$field] = null;
                $sql_values[$field] = 'NULL';
            } elseif ($column->get_type() === 'point') {
                // POINT columns.
                $sql_values[$field] = "GeomFromText('" . esc_sql($value) . "')";
            } elseif ($column->is_numeric()) {
                // Numeric values.
                $sql_values[$field] = $value;
            } else {
                // Everything else.
                $sql_values[$field] = "'" . esc_sql($value) . "'";
            }
        }

        // Find the PK, and hide errors (for now).
        $pk_name = $this->get_pk_column()->getName();
        $this->database->get_wpdb()->hide_errors();

        // Compile SQL for insert and update statements.
        // This is a workaround for NULL support in $wpdb->update() and $wpdb->insert().
        // Can probably be removed when https://core.trac.wordpress.org/ticket/15158 is resolved.
        $set_items = array();
        foreach ($sql_values as $field => $escd_datum) {
            $set_items[] = "`$field` = $escd_datum";
        }
        $set_clause = 'SET ' . join(', ', $set_items);

        // Prevent PK from being set to empty.
        if (isset($data[$pk_name]) && empty($data[$pk_name])) {
            unset($data[$pk_name]);
        }

        $change_tracker->before_save($this, $data, $pk_value);
        if (!empty($pk_value)) { // Update?
            // Check permission.
            if (!Grants::current_user_can(Grants::UPDATE, $this->getName())) {
                throw new Exception('You do not have permission to update data in this table.');
            }
            $where_clause = $this->database->get_wpdb()->prepare("WHERE `$pk_name` = %s", $pk_value);
            $this->database->get_wpdb()->query('UPDATE ' . $this->getName() . " $set_clause $where_clause;");
            $new_pk_value = (isset($data[$pk_name]) ) ? $data[$pk_name] : $pk_value;
        } else { // Or insert?
            // Check permission.
            if (!Grants::current_user_can(Grants::CREATE, $this->getName())) {
                throw new Exception('You do not have permission to insert records into this table.');
            }
            $sql = 'INSERT INTO ' . $this->getName() . ' ' . $set_clause . ';';
            $this->database->get_wpdb()->query($sql);
            if (!empty($this->database->get_wpdb()->last_error)) {
                Exception::wp_die('The record was not created.', 'Unable to create record', $this->database->get_wpdb()->last_error, $sql); // WPCS: XSS OK.
            }
            if ($this->get_pk_column()->is_auto_increment()) {
                // Use the last insert ID.
                $new_pk_value = $this->database->get_wpdb()->insert_id;
            } elseif (isset($data[$pk_name])) {
                // Or the PK value provided in the data.
                $new_pk_value = $data[$pk_name];
            } else {
                // If neither of those work, how can we find out the new PK value?
                throw new Exception("Unable to determine the value of the new record's prmary key.");
            }
        }
        $new_record = $this->getRecord($new_pk_value);
        if (!$new_record instanceof Record) {
            throw new Exception("Unable to fetch record with PK of: <code>" . var_export($new_pk_value, true) . '</code>');
        }

        // Save the changes.
        $change_tracker->after_save($this, $new_record);

        // Show errors again, reset the record count,
        // and return the new or updated record.
        $this->database->get_wpdb()->show_errors();
        $this->record_counter->clear();
        return $new_record;
    }

    /**
     * Get a fully-qualified URL to a Back End page for this table.
     * @param string   $action Which action to use ('index', 'import', etc.).
     * @param string[] $extra_params Other query string parameters to add.
     * @param string   $controller Which controller to use ('table', 'record', etc.).
     * @return string  The full URL.
     */
    public function get_url($action = 'index', $extra_params = false, $controller = 'table') {
        $params = array(
            'page' => 'tabulate',
            'controller' => $controller,
            'action' => $action,
            'table' => $this->getName(),
        );
        if (is_array($extra_params)) {
            $params = array_merge($_GET, $params, $extra_params);
        }
        return \Tabulate\Config::baseUrl() . '/table/' . $this->getName() . '?' . http_build_query($params);
    }

    /**
     * Rename this table and all of its change-tracker entries.
     * @param string $new_name
     */
    public function rename($new_name) {
        if ($this->get_database()->get_table($new_name)) {
            throw new Exception("Table '$new_name' already exists");
        }
        $wpdb = $this->get_database()->get_wpdb();
        $old_name = $this->getName();
        $wpdb->query("RENAME TABLE `$old_name` TO `$new_name`;");
        $this->get_database()->reset();
        $new = $this->get_database()->get_table($new_name, false);
        if (!$new) {
            throw new Exception("Table '$old_name' was not renamed to '$new_name'");
        }
        $this->name = $new->getName();
        $wpdb->query("UPDATE `" . ChangeTracker::changes_name() . "`"
                . " SET `table_name` = '$new_name' "
                . " WHERE `table_name` = '$old_name';");
    }

}
