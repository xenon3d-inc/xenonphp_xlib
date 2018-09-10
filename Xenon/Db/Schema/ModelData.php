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

    public function toDb($mysqli_link)
    {
        if (!$this->getTable()) {
            //TODO Throw notice "ModelData Not Initialised"
            return;
        }

        $fromDB = (new ModelData)->fromDb($mysqli_link, $this->getTable()->table);

        if (!$fromDB->getTable()) {
            // CREATE TABLE
            $query = DbParser::TableToSql($this->getTable(), $this->getColumns());
            if ($query) {
                //TODO Log Query
                $success = mysqli_query($mysqli_link, $query); // EXECUTE CREATE TABLE
                if ($success === false) {
                    //TODO Throw "Error On DbParser::TableToSql" mysqli_error($mysqli_link)
                }
            }
        } else {
            // ALTER TABLE
            $query = DbParser::AlterTableToSql($fromDB, $this);
            if ($query) {
                //TODO Log Query
//                echo $query;

                $success = mysqli_query($mysqli_link, $query); // EXECUTE ALTER TABLE
                if ($success === false) {
//                    echo mysqli_error($mysqli_link);
                    //TODO Throw "Error On DbParser::AlterTableToSql" mysqli_error($mysqli_link)
                }
            }
        }
        return $this;
    }

}
