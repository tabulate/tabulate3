<?php

namespace Tabulate\DB;

use Tabulate\Config;
use Tabulate\DB\Tables\Grants;

class Table
{

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
    protected $definingSql;

    /** @var string The SQL statement most recently saved by $this->get_records() */
    protected $savedSql;

    /** @var string[] The statement parameters most recently saved by $this->get_records() */
    protected $savedParameters;

    /** @var \Tabulate\DB\Table[] Array of tables referred to by columns in this one. */
    protected $referencedTables;

    /** @var string[] The names (only) of tables referenced by columns in this one. */
    protected $referencedTableNames;

    /** @var int Each joined table gets a unique alias, based on this. */
    protected $aliasCount = 1;

    /**
     * @var \Tabulate\DB\Column[] Array of column names and objects for all of the
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
    protected $orderBy = false;

    /** @var string The direction in which results should be ordered. */
    protected $orderDir = 'ASC';

    /** @var RecordCounter */
    protected $recordCounter;

    /** @var integer The current page number. */
    protected $currentPageNum = 1;

    /** @var integer The number of records to show on each page. */
    protected $recordsPerPage = 30;

    /**
     * Create a new database table object.
     *
     * @param \Tabulate\DB\Database $database The database to which this table belongs.
     * @param string $name The name of the table.
     */
    public function __construct($database, $name)
    {
        $this->database = $database;
        $this->name = $name;
        $this->columns = array();
        $columns = $this->database->query("SHOW FULL COLUMNS FROM `$name`");
        foreach ($columns as $column_info) {
            $column = new Column($this->database, $this, $column_info);
            $this->columns[$column->getName()] = $column;
        }
        $this->recordCounter = new RecordCounter($this);
    }

    /**
     * Add a filter.
     * @param string|\Tabulate\DB\Column $column Column name or object.
     * @param string $operator
     * @param string $value
     * @param boolean $force Whether to transform the value, for FKs.
     */
    public function addFilter($column, $operator, $value, $force = false)
    {
        // Allow Columns to be passed in.
        if ($column instanceof Column) {
            $column = $column->getName();
        }
        // Validate the parts of the filter.
        $valid_columm = in_array($column, array_keys($this->getColumns()));
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
    public function addFilters($filters)
    {
        foreach ($filters as $filter) {
            $column = (isset($filter['column'])) ? $filter['column'] : false;
            $operator = (isset($filter['operator'])) ? $filter['operator'] : false;
            $value = (isset($filter['value'])) ? $filter['value'] : false;
            $this->addFilter($column, $operator, $value);
        }
    }

    public function get_filters()
    {
        return $this->filters;
    }

    protected function getForeignKeyJoinClause($table, $alias, $column)
    {
        return 'LEFT OUTER JOIN `' . $table->getName() . '` AS f' . $alias
                . ' ON (`' . $this->getName() . '`.`' . $column->getName() . '` '
                . ' = `f' . $alias . '`.`' . $table->getPkColumn()->getName() . '`)';
    }

    /**
     * Apply the stored filters to the supplied SQL.
     *
     * @param string $sql The SQL to modify
     * @return array Parameter values, in the order of their occurence in $sql
     */
    public function applyFilters(&$sql)
    {

        $params = array();
        $param_num = 1; // Incrementing parameter suffix, to permit duplicate columns.
        $whereClause = '';
        $join_clause = '';
        foreach ($this->filters as $filter) {
            $filterCol = $filter['column'];
            $paramName = $filter['column'] . $param_num;

            // Filters on foreign keys need to work on the FKs title column.
            $column = $this->columns[$filterCol];
            if ($column->isForeignKey() && !$filter['force']) {
                $join = $this->joinOn($column);
                $filterCol = $join['column_alias'];
                $join_clause .= $join['join_clause'];
            } else {
                // The result of join_on() above is quoted, so this must also be.
                $filterCol = "`" . $this->getName() . "`.`$filterCol`";
            }

            // LIKE or NOT LIKE
            if ($filter['operator'] == 'like' || $filter['operator'] == 'not like') {
                $whereClause .= " AND CONVERT($filterCol, CHAR) " . strtoupper($filter['operator']) . " :$paramName ";
                $params[$paramName] = '%' . trim($filter['value']) . '%';
            } // Equals or does-not-equal
            elseif ($filter['operator'] == '=' || $filter['operator'] == '!=') {
                $whereClause .= " AND $filterCol " . strtoupper($filter['operator']) . " :$paramName ";
                $params[$paramName] = trim($filter['value']);
            } // IS EMPTY
            elseif ($filter['operator'] == 'empty') {
                $whereClause .= " AND ($filterCol IS NULL OR $filterCol = '')";
            } // IS NOT EMPTY
            elseif ($filter['operator'] == 'not empty') {
                $whereClause .= " AND ($filterCol IS NOT NULL AND $filterCol != '')";
            } // IN or NOT IN
            elseif ($filter['operator'] == 'in' || $filter['operator'] == 'not in') {
                $values = explode("\n", $filter['value']);
                $placeholders = array();
                foreach ($values as $valId => $val) {
                    $placeholders[] = ':' . $paramName . '_' . $valId;
                    $params[$paramName . '_' . $valId] = trim($val);
                }
                $negate = ( $filter['operator'] == 'not in' ) ? 'NOT' : '';
                $whereClause .= " AND ($filterCol $negate IN (" . join(", ", $placeholders) . "))";
            } // Other operators. They're already validated in $this->addFilter()
            else {
                $whereClause .= " AND ($filterCol " . $filter['operator'] . " :$paramName)";
                $params[$paramName] = trim($filter['value']);
            }

            $param_num++;
        } // end foreach filter
        // Add clauses into SQL
        if (!empty($whereClause)) {
            $where_clause_pattern = '/^(.* FROM .*?)((?:GROUP|HAVING|ORDER|LIMIT|$).*)$/m';
            $whereClause = substr($whereClause, 5); // Strip leading ' AND'.
            $whereClause = "$1 $join_clause WHERE $whereClause $2";
            $sql = preg_replace($where_clause_pattern, $whereClause, $sql);
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
    public function get_order_by()
    {
        return $this->orderBy;
    }

    /**
     * Change the column by which this table is ordered.
     * @param string $order_by The name of the column to order by.
     */
    public function set_order_by($order_by)
    {
        if (in_array($order_by, array_keys($this->columns))) {
            $this->orderBy = $order_by;
        }
    }

    public function get_order_dir()
    {
        if (empty($this->orderDir)) {
            $this->orderDir = 'ASC';
        }
        return $this->orderDir;
    }

    /**
     * Set the direction of ordering.
     * @param string $order_dir Either 'ASC' or 'DESC' (case insensitive).
     */
    public function set_order_dir($order_dir)
    {
        if (in_array(strtoupper($order_dir), array('ASC', 'DESC'))) {
            $this->orderDir = $order_dir;
        }
    }

    /**
     * For a given foreign key column, get an alias and join clause for selecting
     * against that column's foreign values. If the column is not a foreign key,
     * the alias will just be the qualified column name, and the join clause will
     * be the empty string.
     *
     * @param \Tabulate\DB\Column $column The FK column
     * @return array Array with 'join_clause' and 'column_alias' keys
     */
    public function joinOn($column)
    {
        $joinClause = '';
        $columnAlias = '`' . $this->getName() . '`.`' . $column->getName() . '`';
        if ($column->isForeignKey()) {
            $fk1Table = $column->getReferencedTable();
            if (!$fk1Table) {
                throw new \Exception("Unable to get referenced table of " . $column->getTable()->getName() . '.' . $column->getName());
            }
            $fk1TitleColumn = $fk1Table->getTitleColumn();
            $joinClause .= ' LEFT OUTER JOIN `' . $fk1Table->getName() . '` AS f' . $this->aliasCount
                    . ' ON (`' . $this->getName() . '`.`' . $column->getName() . '` '
                    . ' = `f' . $this->aliasCount . '`.`' . $fk1Table->getPkColumn()->getName() . '`)';
            $columnAlias = "`f$this->aliasCount`.`" . $fk1TitleColumn->getName() . "`";
            // FK is also an FK?
            if ($fk1TitleColumn->isForeignKey()) {
                $fk2_table = $fk1TitleColumn->getReferencedTable();
                $fk2_title_column = $fk2_table->getTitleColumn();
                $joinClause .= ' LEFT OUTER JOIN `' . $fk2_table->getName() . '` AS ff' . $this->aliasCount
                        . ' ON (f' . $this->aliasCount . '.`' . $fk1TitleColumn->getName() . '` '
                        . ' = ff' . $this->aliasCount . '.`' . $fk1Table->getPkColumn()->getName() . '`)';
                $columnAlias = "`ff$this->aliasCount`.`" . $fk2_title_column->getName() . "`";
            }
            $this->aliasCount++;
        }
        return array('join_clause' => $joinClause, 'column_alias' => $columnAlias);
    }

    /**
     * Get rows, optionally with pagination.
     * @param boolean $with_pagination Whether to only return the top N results.
     * @param boolean $save_sql Whether to store the SQL for later use.
     * @return \Tabulate\DB\Record[]
     */
    public function getRecords($with_pagination = true, $save_sql = false)
    {
        // Build basic SELECT statement.
        $sql = 'SELECT ' . $this->columnsSqlSelect() . ' FROM `' . $this->getName() . '`';

        // Ordering.
        if (false !== $this->get_order_by()) {
            $order_by = $this->getColumn($this->get_order_by());
            if ($order_by) {
                $order_by_join = $this->joinOn($order_by);
                $sql .= $order_by_join['join_clause'] . ' ORDER BY ' . $order_by_join['column_alias'] . ' ' . $this->get_order_dir();
            }
        }

        $params = $this->applyFilters($sql);

        // Then limit to the ones on the current page.
        if ($with_pagination) {
            $records_per_page = $this->getRecordsPerPage();
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
            $this->savedSql = $sql;
            $this->savedParameters = $params;
        }

        return $records;
    }

    public function get_current_page_num()
    {
        return $this->currentPageNum;
    }

    public function set_current_page_num($current_page_num)
    {
        $this->currentPageNum = $current_page_num;
    }

    public function getRecordsPerPage()
    {
        return $this->recordsPerPage;
    }

    public function set_records_per_page($recordsPerPage)
    {
        $this->recordsPerPage = $recordsPerPage;
    }

    public function get_saved_query()
    {
        return array(
            'sql' => $this->savedSql,
            'parameters' => $this->savedParameters
        );
    }

    /**
     * Get the SQL for SELECTing all columns in this table.
     * @return string
     */
    private function columnsSqlSelect()
    {
        $select = array();
        $table_name = $this->getName();
        foreach ($this->getColumns() as $col_name => $col) {
            if ($col->getType() == 'point') {
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
    public function getRecord($pk_val)
    {
        $pk_column = $this->getPkColumn();
        if (!$pk_column) {
            return false;
        }
        $pkColName = $pk_column->getName();
        $sql = "SELECT " . $this->columnsSqlSelect() . " "
                . "FROM `" . $this->getName() . "` "
                . "WHERE `$pkColName` = :$pkColName "
                . "LIMIT 1";
        $params = array($pkColName => $pk_val);
        $row = $this->database->query($sql, $params)->fetch();
        return ( $row ) ? new Record($this, $row) : false;
    }

    /**
     * Get a bare record with only default values.
     *
     * @return Record
     */
    public function getDefaultRecord()
    {
        $row = array();
        foreach ($this->getColumns() as $col) {
            $row[$col->getName()] = $col->getDefault();
        }
        $record = new Record($this, $row);
        return $record;
    }

    public function has_changes_recorded()
    {
        return !in_array($this->getName(), ChangeTracker::tableNames());
    }

    /**
     * Get this table's name.
     *
     * @return string The name of this table.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Whether this is a base table or a view.
     *
     * @return string Either `Table::TYPE_TABLE` or `Table::TYPE_VIEW`.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Whether this table is a table (as opposed to a view).
     * @return boolean
     */
    public function isTable()
    {
        return $this->getType() == self::TYPE_TABLE;
    }

    /**
     * Whether this table is a view.
     * @return boolean
     */
    public function isView()
    {
        return $this->getType() == self::TYPE_VIEW;
    }

    /**
     * Whether this view is updatable. Always true for base tables. Currently
     * always false for all views.
     * @todo Implement this.
     * @link https://dev.mysql.com/doc/refman/5.6/en/view-updatability.html
     */
    public function isUpdatable()
    {
        if ($this->isTable()) {
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
    public function getTitle()
    {
        return \Tabulate\Text::titlecase($this->getName());
    }

    /**
     * Get a list of permitted operators.
     *
     * @return string[] List of operators.
     */
    public function get_operators()
    {
        return $this->operators;
    }

    public function getPageCount()
    {
        return ceil($this->getRecordCount() / $this->getRecordsPerPage());
    }

    /**
     * Get or set the current page.
     *
     * @param integer $page
     * @return integer Current page
     */
    public function page($page = false)
    {
        if ($page !== false) {
            $this->currentPageNum = $page;
        } else {
            return $this->currentPageNum;
        }
    }

    /**
     * Get the number of rows in the current filtered set.
     * @return integer
     */
    public function getRecordCount()
    {
        return $this->recordCounter->getCount();
    }

    /**
     * @return \Tabulate\DB\RecordCounter
     */
    public function getRecordCounter()
    {
        return $this->recordCounter;
    }

    /**
     * @return string Full filesystem path to resulting temporary file.
     */
    public function export()
    {

        $columns = array();
        $columnHeaders = array();
        $join_clause = '';
        foreach ($this->columns as $col_name => $col) {
            if ($col->isForeignKey()) {
                $colJoin = $this->joinOn($col);
                $columnName = $colJoin['column_alias'];
                $join_clause .= $colJoin['join_clause'];
            } elseif ($col->getType() === 'point') {
                $columns[] = "IF(`$this->name`.`$col_name` IS NOT NULL, AsText(`$this->name`.`$col_name`), '') AS `$col_name`";
            } else {
                $columnName = "`$this->name`.`$col_name`";
            }
            if ($col->getType() !== 'point' && isset($columnName)) {
                $columns[] = "REPLACE(IFNULL($columnName, ''),CONCAT(CHAR(13),CHAR(10)),CHAR(10))"; // 13 = \r and 10 = \n
            }
            $columnHeaders[] = $col->getTitle();
        }

        // Build basic SELECT statement
        $sql = 'SELECT ' . join(',', $columns)
                . ' FROM `' . $this->getName() . '` ' . $join_clause;

        $params = $this->applyFilters($sql);

        $tmpDir = Config::storageDirExport();
        $filename = $tmpDir . '/' . uniqid() . '.csv';
        if (DIRECTORY_SEPARATOR == '\\') {
            // Clean Windows slashes, for MySQL's benefit.
            $filename = str_replace('\\', '/', $filename);
        }
        // Clear out any old copy.
        if (file_exists($filename)) {
            unlink($filename);
        }
        // Build the final SQL, appending the column headers in a UNION.
        $sql = 'SELECT "' . join('", "', $columnHeaders) . '"'
                . ' UNION ' . $sql
                . ' INTO OUTFILE "' . $filename . '" '
                . ' FIELDS TERMINATED BY ","'
                . ' ENCLOSED BY \'"\''
                . ' ESCAPED BY \'"\''
                . ' LINES TERMINATED BY "\r\n"';
        // Execute the SQL.
        $this->database->query($sql, $params);
        // Make sure it exported.
        if (!file_exists($filename)) {
            $msg = "Unable to create temporary export file:<br /><code>$filename</code>";
            throw new \Exception($msg);
        }
        // Give the filename back to the controller, to send to the client.
        return $filename;
    }

    /**
     * Get one of this table's columns.
     * @param string $name The column name.
     * @return \Tabulate\DB\Column|false The column, or false if it's not found.
     */
    public function getColumn($name)
    {
        return (isset($this->columns[$name])) ? $this->columns[$name] : false;
    }

    /**
     * Get a list of this table's columns, optionally constrained by their type.
     *
     * @param string $type Only return columns of this type.
     * @return \Tabulate\DB\Column[] This table's columns.
     */
    public function getColumns($type = null)
    {
        if (is_null($type)) {
            return $this->columns;
        } else {
            $out = array();
            foreach ($this->getColumns() as $col) {
                if ($col->getType() === $type) {
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
    public function getComment()
    {
        if (!$this->comment) {
            $sql = $this->getDefiningSql();
            $commentPattern = '/.*\)(?:.*COMMENT[\w=]*\'(.*)\')?/si';
            preg_match($commentPattern, $sql, $matches);
            $this->comment = ( isset($matches[1]) ) ? $matches[1] : '';
            $this->comment = str_replace("''", "'", $this->comment);
        }
        if (empty($this->comment) && $this->isView()) {
            $this->comment = '(View)';
        }
        return $this->comment;
    }

    /**
     * Get a list of all the unique columns in this table.
     * @return \Tabulate\DB\Column[]
     */
    public function getUniqueColumns()
    {
        $cols = array();
        foreach ($this->getColumns() as $column) {
            if ($column->isUnique()) {
                $cols[] = $column;
            }
        }
        return $cols;
    }

    /**
     * Get the first unique-keyed column.
     * If there is no unique non-PK column then just use the PK.
     *
     * @return \Tabulate\DB\Column|false The PK column, of false if there isn't one (e.g. a multi-column PK).
     */
    public function getTitleColumn()
    {
        // Try to get the first non-PK unique key.
        foreach ($this->getColumns() as $column) {
            if ($column->isUnique() && !$column->isPrimaryKey()) {
                return $column;
            }
        }
        // But if that fails, just use the primary key.
        return $this->getPkColumn();
    }

    /**
     * Get the SQL statement used to create this table, as given by the 'SHOW
     * CREATE TABLE' command.
     *
     * @return string The SQL statement used to create this table.
     * @throws Exception If the table or view is not found.
     */
    public function getDefiningSql()
    {
        if (!isset($this->definingSql)) {
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
            $this->definingSql = $defining_sql;
        }
        return $this->definingSql;
    }

    /**
     * Get this table's Primary Key column.
     *
     * @return \Tabulate\DB\Column|false The PK column or false if there isn't one.
     */
    public function getPkColumn()
    {
        foreach ($this->getColumns() as $column) {
            if ($column->isPrimaryKey()) {
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
    public function getReferencedTables($instantiate = false)
    {

        // Extract the FK info from the CREATE TABLE statement.
        if (!is_array($this->referencedTables)) {
            $this->referencedTableNames = array();
            $definingSql = $this->getDefiningSql();
            $foreignKeyPattern = '|FOREIGN KEY \(`(.*?)`\) REFERENCES `(.*?)`|';
            preg_match_all($foreignKeyPattern, $definingSql, $matches);
            if (isset($matches[1]) && count($matches[1]) > 0) {
                foreach (array_combine($matches[1], $matches[2]) as $colName => $tabName) {
                    $this->referencedTableNames[$colName] = $tabName;
                }
            }
        }

        if ($instantiate) {
            $this->referencedTables = array();
            foreach ($this->referencedTableNames as $refCol => $refTab) {
                $this->referencedTables[$refCol] = new Table($this->getDatabase(), $refTab);
            }
        }

        return ($instantiate) ? $this->referencedTables : $this->referencedTableNames;
    }

    /**
     * Get a list of tables with foreign keys referring here, and which of their columns are the FKs.
     *
     * @return array With keys 'table' and 'column'.
     */
    public function getReferencingTables()
    {
        $out = array();
        // For all tables in the Database...
        foreach ($this->getDatabase()->getTables() as $table) {
            // ...get a list of the tables they reference.
            $foreign_tables = $table->getReferencedTables();
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
    public function get_foreign_key_names()
    {
        return array_keys($this->getReferencedTables(false));
    }

    /**
     * Get the database to which this table belongs.
     *
     * @return \Tabulate\DB\Database The database object.
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * Get a string representation of this table; a succinct summary of its
     * columns and their types, keys, etc.
     *
     * @return string A summary of this table.
     */
    public function __toString()
    {
        $col_count = count($this->getColumns());
        $out = "\n";
        $out .= '+-----------------------------------------+' . "\n";
        $out .= '| ' . $this->getName() . ' (' . $col_count . ' columns)' . "\n";
        $out .= '+-----------------------------------------+' . "\n";
        foreach ($this->getColumns() as $column) {
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
    public function toXml()
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $table = $dom->createElement('table');
        $dom->appendChild($table);
        $name = $dom->createElement('name');
        $name->appendChild($dom->createTextNode($this->name));
        $table->appendChild($name);
        foreach ($this->getColumns() as $column) {
            $table->appendChild($dom->importNode($column->toXml(), true));
        }
        return $table;
    }

    /**
     * Get a JSON representation of the structure of this table.
     *
     * @return string
     */
    public function to_json()
    {
        $json = new Services_JSON();
        $metadata = array();
        foreach ($this->getColumns() as $column) {
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
    public function resetFilters()
    {
        $this->filters = array();
    }

    /**
     * Delete a record and its associated change-tracker records.
     * @param string $pkValue The value of the primary key of the record to delete.
     * @return void
     * @throws Exception When the user doesn't have permission, or any error occurs deleting the record.
     */
    public function deleteRecord($pkValue)
    {
        // Check permission.
        if (!$this->getDatabase()->checkGrant(Grants::DELETE, $this->getName())) {
            throw new \Exception('You do not have permission to delete data from this table.');
        }
        $rec = $this->getRecord($pkValue);
        $pkCol = $this->getPkColumn()->getName();
        $sql = "DELETE FROM `" . $this->getName() . "` WHERE `$pkCol` = :$pkCol";
        $del = $this->getDatabase()->query($sql, array($pkCol => $pkValue));
        if (!$del) {
            throw new \Exception("Unable to delete " . $this->getName() . " record $pkValue");
        }
        foreach ($rec->getChanges() as $change) {
            $deleteChangesSql = "DELETE FROM `changes` WHERE `id` = :id";
            $this->getDatabase()->query($deleteChangesSql, ['id' => $change->change_id]);
            $deleteChangesetsSql = "DELETE FROM `changesets` WHERE `id` = :id";
            $this->getDatabase()->query($deleteChangesetsSql, ['id' => $change->changeset_id]);
        }
        $this->recordCounter->clear();
        $this->getDatabase()->getTable('changes')->getRecordCounter()->clear();
        $this->getDatabase()->getTable('changesets')->getRecordCounter()->clear();
    }

    /**
     * Save data to this table. If a primary key value is given, that row will be
     * updated; otherwise, a new row will be inserted.
     *
     * @param array  $data The data to insert.
     * @param string $pkValue The value of the record's PK. Null if the record doesn't exist.
     * @return \Tabulate\DB\Record The updated or inserted record.
     * @throws Exception If the user doesn't have permission, or something else has gone wrong.
     */
    public function saveRecord($data, $pkValue = null, $trackChanges = true)
    {
        // Changeset only created here if not already in progress.
        if ($trackChanges) {
            $changesetComment = isset($data['changeset_comment']) ? $data['changeset_comment'] : null;
            $changeTracker = new ChangeTracker($this->getDatabase(), $changesetComment);
        }

        $columns = $this->getColumns();

        /*
         * Go through all data and clean it up before saving.
         */
        $sqlValues = array();
        foreach ($data as $field => $value) {
            // Make sure this column exists in the DB.
            if (!isset($columns[$field])) {
                unset($data[$field]);
                continue;
            }
            $column = $this->getColumn($field);

            if ($column->isBoolean()) {
                // Boolean values.
                $zeroValues = array(0, '0', false, 'false', 'FALSE', 'off', 'OFF', 'no', 'NO');
                if (( null === $value || '' === $value ) && $column->nullable()) {
                    $data[$field] = null;
                } elseif (in_array($value, $zeroValues, true)) {
                    $data[$field] = false;
                } else {
                    $data[$field] = true;
                }
                $sqlValues[$field] = ":$field";
            } elseif (!$column->allowsEmptyString() && '' === $value && $column->nullable()) {
                // Empty strings.
                $data[$field] = null;
                $sqlValues[$field] = ":$field";
            } elseif (is_null($value) && $column->nullable()) {
                // Nulls.
                $data[$field] = null;
                $sqlValues[$field] = ":$field";
            } elseif ($column->getType() === 'point') {
                // POINT columns.
                $sqlValues[$field] = "GeomFromText(:$field)";
            } else {
                // Everything else.
                $sqlValues[$field] = ":$field";
            }
        }

        // Find the PK.
        $pkName = $this->getPkColumn()->getName();

        // Compile SQL for insert and update statements.
        $itemsForSetClause = array();
        foreach ($sqlValues as $field => $escd_datum) {
            $itemsForSetClause[] = "`$field` = $escd_datum";
        }
        $setClause = 'SET ' . join(', ', $itemsForSetClause);

        // Prevent PK from being set to empty.
        if (isset($data[$pkName]) && empty($data[$pkName])) {
            unset($data[$pkName]);
        }

        if (isset($changeTracker)) {
            $changeTracker->beforeSave($this, $data, $pkValue);
        }
        if (!empty($pkValue)) { // Update?
            // Check permission.
            if (!$this->getDatabase()->checkGrant(Grants::UPDATE, $this->getName())) {
                throw new \Exception('You do not have permission to update data in this table ' . $this->getName());
            }
            $data[$pkName] = $pkValue;
            $sql = 'UPDATE ' . $this->getName() . " $setClause WHERE `$pkName` = :$pkName;";
            $this->database->query($sql, $data);
            $newPkValue = (isset($data[$pkName]) ) ? $data[$pkName] : $pkValue;
        } else { // Or insert?
            // Check permission.
            if (!$this->getDatabase()->checkGrant(Grants::CREATE, $this->getName())) {
                throw new \Exception('You do not have permission to insert records into table ' . $this->getName());
            }
            $sql = 'INSERT INTO ' . $this->getName() . ' ' . $setClause . ';';
            $this->database->query($sql, $data);
            if ($this->getPkColumn()->isAutoIncrement()) {
                // Use the last insert ID.
                $newPkValue = $this->database->lastInsertId();
            } elseif (isset($data[$pkName])) {
                // Or the PK value provided in the data.
                $newPkValue = $data[$pkName];
            } else {
                // If neither of those work, how can we find out the new PK value?
                throw new \Exception("Unable to determine the value of the new record's prmary key.");
            }
        }
        $newRecord = $this->getRecord($newPkValue);
        if (!$newRecord instanceof Record) {
            throw new Exception("Unable to fetch record with PK of: <code>" . var_export($newPkValue, true) . '</code>');
        }

        // Save the changes.
        if (isset($changeTracker)) {
            $changeTracker->after_save($this, $newRecord);
        }

        // Show errors again, reset the record count,
        // and return the new or updated record.
        $this->recordCounter->clear();
        return $newRecord;
    }

    /**
     * Get a fully-qualified URL to a Back End page for this table.
     * @param string   $action Which action to use ('index', 'import', etc.).
     * @param string[] $extra_params Other query string parameters to add.
     * @param string   $controller Which controller to use ('table', 'record', etc.).
     * @return string  The full URL.
     */
    public function getUrl($action = 'index', $extra_params = false, $controller = 'table')
    {
        $params = array(
            'page' => 'tabulate',
            'controller' => $controller,
            'action' => $action,
            'table' => $this->getName(),
        );
        if (is_array($extra_params)) {
            $params = array_merge($_GET, $params, $extra_params);
        }
        return Config::baseUrl() . '/table/' . $this->getName() . '?' . http_build_query($params);
    }

    /**
     * Rename this table and all of its change-tracker entries.
     * @param string $new_name
     */
    public function rename($new_name)
    {
        if ($this->getDatabase()->getTable($new_name)) {
            throw new \Exception("Table '$new_name' already exists");
        }
        $old_name = $this->getName();
        $this->getDatabase()->query("RENAME TABLE `$old_name` TO `$new_name`;");
        $this->getDatabase()->reset();
        $new = $this->getDatabase()->getTable($new_name, false);
        if (!$new) {
            throw new \Exception("Table '$old_name' was not renamed to '$new_name'");
        }
        $this->name = $new->getName();
        $this->getDatabase()->query("UPDATE `" . ChangeTracker::changes_name() . "`"
                . " SET `table_name` = '$new_name' "
                . " WHERE `table_name` = '$old_name';");
    }
}
