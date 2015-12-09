<?php

namespace Tabulate\DB;

use Tabulate\Config;

class Database {

    /** @var \PDO */
    static protected $pdo;

    /** @var array|string */
    protected $table_names;

    /** @var Table|array */
    protected $tables;

    /** @var array|string */
    static protected $queries;

    public function __construct() {
        if (self::$pdo) {
            return;
        }
        $host = Config::databaseHost();
        $dsn = "mysql:host=$host;dbname=" . Config::databaseName();
        $attr = array(\PDO::ATTR_TIMEOUT => 10);
        self::$pdo = new \PDO($dsn, Config::databaseUser(), Config::databasePassword(), $attr);
        self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->setFetchMode(\PDO::FETCH_OBJ);
    }

    public static function getQueries() {
        return self::$queries;
    }

    public function setFetchMode($fetchMode) {
        return self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, $fetchMode);
    }

    /**
     * Get a result statement for a given query. Handles errors.
     *
     * @param string $sql The SQL statement to execute.
     * @param array $params Array of param => value pairs.
     * @return \PDOStatement Resulting PDOStatement.
     */
    public function query($sql, $params = false, $class = false, $classArgs = false) {
        if (!empty($class) && !class_exists($class)) {
            throw new \Exception("Class not found: $class");
        }
        if (is_array($params) && count($params) > 0) {
            $stmt = self::$pdo->prepare($sql);
            foreach ($params as $placeholder => $value) {
                if (is_bool($value)) {
                    $type = \PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $type = \PDO::PARAM_NULL;
                } elseif (is_int($value)) {
                    $type = \PDO::PARAM_INT;
                } else {
                    $type = \PDO::PARAM_STR;
                }
                //echo '<li>';var_dump($value, $type);
                $stmt->bindValue($placeholder, $value, $type);
            }
            if ($class) {
                $stmt->setFetchMode(\PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $class, $classArgs);
            } else {
                $stmt->setFetchMode(\PDO::FETCH_OBJ);
            }
            $result = $stmt->execute();
            if (!$result) {
                throw new \PDOException('Unable to execute parameterised SQL: <code>' . $sql . '</code>');
            } else {
                //echo '<p>Executed: '.$sql.'<br />with '.  print_r($params, true).'</p>';
            }
            //exit();
        } else {
            try {
                if ($class) {
                    $stmt = self::$pdo->query($sql, \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE, $class, $classArgs);
                } else {
                    $stmt = self::$pdo->query($sql);
                }
            } catch (\PDOException $e) {
                throw new \Exception($e->getMessage() . ' -- Unable to execute SQL: <code>' . $sql . '</code>');
            }
        }

        self::$queries[] = $sql;
        return $stmt;
    }

    /**
     * Get a list of tables that the current user can read.
     * @return string[] The table names.
     */
    public function get_table_names() {
        if (!$this->table_names) {
            $this->table_names = array();
            foreach ($this->query('SHOW TABLES')->fetchAll() as $row) {
                $tableName = $row->{'Tables_in_'.Config::databaseName()};
                $this->table_names[] = $tableName;
//                if (Grants::current_user_can(Grants::READ, $table_name)) {
//                    $this->table_names[] = $table_name;
//                }
            }
        }
        return $this->table_names;
    }

    /**
     * Get a table from the database.
     *
     * @param string $name
     * @return \WordPress\Tabulate\DB\Table|false The table, or false if it's not available.
     */
    public function get_table($name) {
        if (!in_array($name, $this->get_table_names())) {
            return false;
        }
        if (!isset($this->tables[$name])) {
            $this->tables[$name] = new Table($this, $name);
        }
        return $this->tables[$name];
    }

    /**
     * Forget all table information, forcing it to be re-read from the database
     * when next required. Used after schema changes.
     */
    public function reset() {
        $this->table_names = false;
        $this->tables = false;
    }

    /**
     * Get all tables in this database.
     *
     * @return Table[] An array of all Tables.
     */
    public function get_tables($exclude_views = true) {
        $out = array();
        foreach ($this->get_table_names() as $name) {
            $table = $this->get_table($name);
            // If this table is not available, skip it.
            if (!$table) {
                continue;
            }
            if ($exclude_views && $table->get_type() == Table::TYPE_VIEW) {
                continue;
            }
            $out[] = $table;
        }
        return $out;
    }

    /**
     * Get all views in this database.
     *
     * @return Table|array An array of all Tables that are views.
     */
    public function get_views() {
        $out = array();
        foreach ($this->get_tables(false) as $table) {
            if ($table->get_type() == Table::TYPE_VIEW) {
                $out[] = $table;
            }
        }
        return $out;
    }

}
