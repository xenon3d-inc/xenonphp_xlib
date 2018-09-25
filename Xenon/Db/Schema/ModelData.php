<?php

namespace Xenon\Db\Schema;

class ModelData
{
    protected $table;
    protected $fields;
    protected $columns;
    
    protected static $instances = [];
    
    public static function get($className) {
        if (!in_array($className, self::$instances)) {
            self::$instances[$className] = (new ModelData)->fromModel($className);
        }
        return self::$instances[$className];
    }
    
    public function getTable()
    {
        return $this->table;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function getColumn($columnName)
    {
        if (isset($this->columns[$columnName])) {
            return $this->columns[$columnName];
        }
        return false;
    }

    public function getField($fieldName)
    {
        if (isset($this->fields[$fieldName])) {
            return $this->fields[$fieldName];
        }
        return false;
    }

    public function fromModel($className)
    {
        $this->table = ModelParser::getTable($className);
        $this->fields = ModelParser::getFields($className);
        $this->columns = array();
        foreach ($this->fields as $fieldName => $fieldData) {
            if ($fieldData->column) {
                $this->columns[$fieldName] = $fieldData;
            }
        }
        return $this;
    }

    public function toModel($className)
    {
        //TODO Implement Write To Model
        return $this;
    }

    public function fromDb($mysqli_link, $tableName)
    {
        $dbParser = new DbParser($mysqli_link, $tableName);
        $this->table = $dbParser->getTable();
        $this->columns = $dbParser->getColumns();
        return $this;
    }

    // Returns the query instead of $this
    public function getCreateOrAlterQuery($mysqli_link)
    {
        if (!$this->getTable()) {
            trigger_error("ModelData Not Initialised", E_USER_ERROR);
            return;
        }

        $fromDB = (new ModelData)->fromDb($mysqli_link, $this->getTable()->table);

        if (!$fromDB->getTable()) {
            // CREATE TABLE
            $query = DbParser::TableToSql($this->getTable(), $this->getColumns());
        } else {
            // ALTER TABLE
            $query = DbParser::AlterTableToSql($fromDB, $this);
        }
        return $query;
    }

}
