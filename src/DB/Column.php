<?php

namespace Tabulate\DB;

class Column
{

    /**
     * @var Table The table to which this column belongs.
     */
    private $table;

    /** @var string The name of this column. */
    private $name;

    /** @var string The type of this column. */
    private $type;

    /** @var integer The size, or length, of this column. */
    private $size;

    /** @var string This column's collation. */
    private $collation;

    /**
     * @var boolean Whether or not this column is required, i.e. is NULL = not
     * required = false; and NOT NULL = required = true.
     */
    private $required = false;

    /** @var boolean Whether or not this column is the Primary Key. */
    private $is_primary_key = false;

    /** @var boolean Whether or not this column is a Unique Key. */
    private $is_unique = false;

    /** @var mixed The default value for this column. */
    private $default_value;

    /** @var boolean Whether or not this column is auto-incrementing. */
    private $is_auto_increment = false;

    /** @var boolean Whether NULL values are allowed for this column. */
    private $nullable;

    /** @var boolean Is this an unsigned number? */
    private $unsigned = false;

    /** @var string[] ENUM options. */
    private $options;

    /** @var string The comment attached to this column. */
    private $comment;

    /**
     * @var Table|false The table that this column refers to, or
     * false if it is not a foreign key.
     */
    private $references = false;

    public function __construct(Database $database, Table $table, $info)
    {

        // Table
        $this->table = $table;

        // Name
        $this->name = $info->Field;

        // Type
        $this->parse_type($info->Type);

        // Default
        $this->default_value = $info->Default;

        // Primary key
        if (strtoupper($info->Key) == 'PRI') {
            $this->is_primary_key = true;
            if ($info->Extra == 'auto_increment') {
                $this->is_auto_increment = true;
            }
        }

        // Unique key
        if (strtoupper($info->Key) == 'UNI') {
            $this->is_unique = true;
        }

        // Comment
        $this->comment = $info->Comment;

        // Collation
        $this->collation = $info->Collation;

        // NULL?
        $this->nullable = ($info->Null == 'YES');

        // Is this a foreign key?
        if (in_array($this->name, $table->get_foreign_key_names())) {
            $referencedTables = $table->getReferencedTables(false);
            $this->references = $referencedTables[$this->name];
        }
    }

    /**
     * Get this column's name.
     *
     * @return string The name of this column.
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get the valid options for this column; only applies to ENUM and SET.
     *
     * @return array The available options.
     */
    public function get_options()
    {
        return $this->options;
    }

    /**
     * Get the human-readable title of this column.
     */
    public function getTitle()
    {
        return \Tabulate\Text::titlecase($this->getName());
    }

    /**
     * Get this column's type.
     *
     * @return string The type of this column.
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Get the column's comment.
     *
     * @return string
     */
    public function get_comment()
    {
        return $this->comment;
    }

    /**
     * Get the default value for this column.
     *
     * @return mixed
     */
    public function getDefault()
    {
        if ($this->default_value == 'CURRENT_TIMESTAMP') {
            return date('Y-m-d h:i:s');
        }
        return $this->default_value;
    }

    /**
     * Get this column's size.
     *
     * @return integer The size of this column.
     */
    public function get_size()
    {
        return $this->size;
    }

    /**
     * Whether or not a not-NULL value needs to be supplied for this column.
     *
     * Not-NULL columns that have default values are *not* considered to be
     * required.
     *
     * @return boolean
     */
    public function isRequired()
    {
        $has_default = ( $this->getDefault() != null || $this->isAutoIncrement() );
        return (!$this->nullable() && !$has_default );
    }

    /**
     * Whether or not this column is the Primary Key for its table.
     *
     * @return boolean True if this is the PK, false otherwise.
     */
    public function isPrimaryKey()
    {
        return $this->is_primary_key;
    }

    /**
     * Whether or not this column is a unique key.
     *
     * @return boolean True if this is a Unique Key, false otherwise.
     */
    public function isUnique()
    {
        return $this->is_unique;
    }

    /**
     * Whether or not this column is an auto-incrementing integer.
     *
     * @return boolean True if this column has AUTO_INCREMENT set, false otherwise.
     */
    public function isAutoIncrement()
    {
        return $this->is_auto_increment;
    }

    /**
     * Whether or not this column is allowed to have NULL values.
     * @return boolean
     */
    public function nullable()
    {
        return $this->nullable;
    }

    /**
     * Only NOT NULL text fields are allowed to have empty strings.
     * @return boolean
     */
    public function allowsEmptyString()
    {
        $textTypes = array('text', 'varchar', 'char');
        return (!$this->nullable() ) && in_array($this->getType(), $textTypes);
    }

    /**
     * Is this a boolean field?
     *
     * This method deals with the silliness that is MySQL's boolean datatype. Or, rather, it will do when it's finished.
     * For now, it just reports true when this is a TINYINT(1) column.
     *
     * @return boolean
     */
    public function isBoolean()
    {
        return $this->getType() == 'tinyint' && $this->get_size() === 1;
    }

    /**
     * Whether or not this column is an integer, float, or decimal column.
     */
    public function is_numeric()
    {
        $isInt = substr($this->getType(), 0, 3) == 'int';
        $isDecimal = substr($this->getType(), 0, 7) == 'decimal';
        $isFloat = substr($this->getType(), 0, 5) == 'float';
        return $isInt || $isDecimal || $isFloat;
    }

    /**
     * Whether or not this column is a foreign key.
     *
     * @return boolean True if $this->_references is not empty, otherwise false.
     */
    public function isForeignKey()
    {
        return !empty($this->references);
    }

    /**
     * Get the table object of the referenced table, if this column is a foreign
     * key.
     *
     * @return Table The referenced table.
     */
    public function getReferencedTable()
    {
        return $this->table->getDatabase()->getTable($this->references);
    }

    /**
     * Get the table that this column belongs to.
     *
     * @return Table The table object.
     */
    public function getTable()
    {
        return $this->table;
    }

    /**
     *
     * @param <type> $type_string
     */
    private function parse_type($type_string)
    {

        if (preg_match('/unsigned/', $type_string)) {
            $this->unsigned = true;
        }

        $varchar_pattern = '/^((?:var)?char)\((\d+)\)/';
        $decimal_pattern = '/^decimal\((\d+),(\d+)\)/';
        $float_pattern = '/^float\((\d+),(\d+)\)/';
        $integer_pattern = '/^((?:big|medium|small|tiny)?int|year)\(?(\d+)\)?/';
        //$integer_pattern = '/.*?(int|year)\(+(\d+)\)/';
        $enum_pattern = '/^(enum|set)\(\'(.*?)\'\)/';

        if (preg_match($varchar_pattern, $type_string, $matches)) {
            $this->type = $matches[1];
            $this->size = (int) $matches[2];
        } elseif (preg_match($decimal_pattern, $type_string, $matches)) {
            $this->type = 'decimal';
            //$colData['precision'] = $matches[1];
            //$colData['scale'] = $matches[2];
        } elseif (preg_match($float_pattern, $type_string, $matches)) {
            $this->type = 'float';
            //$colData['precision'] = $matches[1];
            //$colData['scale'] = $matches[2];
        } elseif (preg_match($integer_pattern, $type_string, $matches)) {
            $this->type = $matches[1];
            $this->size = (int) $matches[2];
        } elseif (preg_match($enum_pattern, $type_string, $matches)) {
            $this->type = $matches[1];
            $values = explode("','", $matches[2]);
            $this->options = array_combine($values, $values);
        } else {
            $this->type = $type_string;
        }
    }

    public function __toString()
    {
        $pk = ($this->is_primary_key) ? ' PK' : '';
        $auto = ($this->is_auto_increment) ? ' AI' : '';
        if ($this->references) {
            $ref = ' References ' . $this->references . '.';
        } else {
            $ref = '';
        }
        $size = ($this->size > 0) ? "($this->size)" : '';
        return $this->name . ' ' . strtoupper($this->type) . $size . $pk . $auto . $ref;
    }

    public function toXml()
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $colElement = $dom->createElement('column');
        $dom->appendChild($colElement);
        $name = $dom->createElement('name');
        $name->appendChild($dom->createTextNode($this->name));
        $colElement->appendChild($name);
        return $colElement;
    }
}
