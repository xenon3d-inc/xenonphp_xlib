<?php

namespace Xenon\Db\Schema;

class Column
{
    public $model, $field;
    public $handler = 'string'; // string | number | date | time | bool | json | id | enum | onetomany
    public $enum = array();
    //
    protected $column = null; // STRING
    protected $id = false; // BOOL
    protected $auto_increment = false; // false | NUMBER
    protected $type = 'varchar'; // 'varchar' | 'text' | ...
    protected $length = 255; // NUMBER | "N,N"
    protected $null = true; // BOOL
    protected $default = null; // any value
    protected $index = false; // BOOL
    protected $encrypted = false; // BOOL
    protected $foreign_key = null; // ['model'=>'ModelClassName', 'field'=>'propertyName', 'table'=>'tableName', 'column'=>'columnName']
    protected $onupdate = null; // 'cascade' for foreign keys | ON UPDATE 'CURRENT_TIMESTAMP'
    protected $ondelete = null; // 'cascade' | ... ONLY FOR FOREIGN KEYS
    
    protected $onetomany = null; // ['model'=>'ModelClassName', 'field'=>'propertyName']
    protected $onetoone = null; // ['model'=>'ModelClassName', 'field'=>'propertyName']
    protected $manytoone = null; // ['model'=>'ModelClassName', 'field'=>'propertyName']
    protected $manytomany = null; // ['model'=>'ModelClassName', 'field'=>'propertyName']
    protected $lazy = true; // BOOL
    protected $join = false; // BOOL
    protected $sort = null; // STRING (field ASC)
    protected $filter = null; // STRING expression

    public function getColumnName()
    {
        return $this->column;
    }
    
    public function __toString() {
        return $this->getColumnName();
    }

    protected function set_column($value)
    {
        if ($value == '') {
            $this->column = true;
            return;
        }
        $value = strtolower($value);
        if (preg_match("#^[a-z_][a-z0-9_-]*$#", $value)) {
            $this->column = $value;
        } else {
            //TODO Throw notice "Invalid Column Name '$value' for $this->field in $this->model"
        }
    }

    protected function set_id($value)
    {
        if ($value !== false) {
            $this->id = true;
            $this->auto_increment = 1;
            $this->type = 'int';
            $this->length = 11;
            $this->null = false;
            $this->handler = 'id';
        }
    }

    protected function set_auto_increment($value)
    {
        if ($value === null || $value === '') {
            $value = 1;
        }
        $this->auto_increment = (int) $value;
    }

    protected function set_type($value)
    {
        // String
        if (preg_match("#^varchar *\(?([0-9]+)\)?$#i", $value, $matches)) {
            $this->type = 'varchar';
            $this->length = $matches[1];
            $this->handler = 'string';
            return;
        }
        if (preg_match("#^(varchar|string)$#i", $value)) {
            $this->type = 'varchar';
            $this->length = 255;
            $this->handler = 'string';
            return;
        }
        if (preg_match("#^(text)$#i", $value)) {
            $this->type = 'text';
            $this->length = null;
            $this->handler = 'string';
            return;
        }
        // Number
        if (preg_match("#^(int|tinyint|smallint|mediumint|bigint) *\(?([0-9]+)\)?$#i", $value, $matches)) {
            $this->type = $matches[1];
            $this->length = $matches[2];
            $this->handler = 'number';
            return;
        }
        if (preg_match("#^(tinyint|byte)$#i", $value)) {
            $this->type = 'tinyint';
            $this->length = 3;
            $this->handler = 'number';
            return;
        }
        if (preg_match("#^(smallint|mediumint|short)$#i", $value)) {
            $this->type = 'smallint';
            $this->length = 6;
            $this->handler = 'number';
            return;
        }
        if (preg_match("#^(int|number)$#i", $value)) {
            $this->type = 'int';
            $this->length = 11;
            $this->handler = 'number';
            return;
        }
        if (preg_match("#^(bigint|long)$#i", $value)) {
            $this->type = 'bigint';
            $this->length = 20;
            $this->handler = 'number';
            return;
        }
        // Decimal
        if (preg_match("#^decimal *\(?([0-9]+(, ?[0-9]+)?)\)?$#i", $value, $matches)) {
            $this->type = 'decimal';
            $this->length = $matches[1];
            $this->handler = 'number';
            return;
        }
        if (preg_match("#^(decimal)$#i", $value)) {
            $this->type = 'decimal';
            $this->length = '10,2';
            $this->handler = 'number';
            return;
        }
        if (preg_match("#^(float)$#i", $value)) {
            $this->type = 'decimal';
            $this->length = '6,5';
            $this->handler = 'number';
            return;
        }
        if (preg_match("#^(double)$#i", $value)) {
            $this->type = 'decimal';
            $this->length = '11,10';
            $this->handler = 'number';
            return;
        }
        // Date
        if (preg_match("#^(timestamp)$#i", $value)) {
            $this->type = 'timestamp';
            $this->length = null;
            $this->handler = 'date';
            return;
        }
        if (preg_match("#^(timestamp ?\(?(auto|create_update)\)?|auto_timestamp)$#i", $value)) {
            $this->type = 'timestamp';
            $this->length = null;
            $this->default = 'current_timestamp';
            $this->onupdate = 'current_timestamp';
            $this->handler = 'date';
            return;
        }
        if (preg_match("#^(timestamp ?\(?(create|current_timestamp)\)?|current_timestamp|create_timestamp)$#i", $value)) {
            $this->type = 'timestamp';
            $this->length = null;
            $this->default = 'current_timestamp';
            $this->handler = 'date';
            return;
        }
        if (preg_match("#^(timestamp ?\(?(update|update_timestamp)\)?|update_timestamp)$#i", $value)) {
            $this->type = 'timestamp';
            $this->length = null;
            $this->default = null;
            $this->null = true;
            $this->onupdate = 'current_timestamp';
            $this->handler = 'date';
            return;
        }
        if (preg_match("#^(datetime)$#i", $value)) {
            $this->type = 'datetime';
            $this->length = null;
            $this->handler = 'date';
            return;
        }
        if (preg_match("#^(date)$#i", $value)) {
            $this->type = 'date';
            $this->length = null;
            $this->handler = 'date';
            return;
        }
        if (preg_match("#^(time)$#i", $value)) {
            $this->type = 'time';
            $this->length = null;
            $this->handler = 'time';
            return;
        }
        // Bool
        if (preg_match("#^(bool|boolean|bit)$#i", $value)) {
            $this->type = 'tinyint';
            $this->length = 1;
            $this->handler = 'bool';
            return;
        }
        // Array, Object, Json
        if (preg_match("#^(array|object|json)$#i", $value)) {
            $this->type = 'text';
            $this->length = null;
            $this->handler = 'json';
            return;
        }
        // ENUM
        if (preg_match("#^(enum|list|set) ?\(([a-z0-9_'\"`,;\|:\. -]+)\)$#i", $value, $matches)) {
            $this->type = 'varchar';
            $this->length = 15;
            $this->handler = 'enum';
            $this->enum = array_map(function($val) {
                return preg_replace("#^['\"`]*([a-z0-9_:\. -]+)['\"`]*$#i", "$1", trim($val));
            }, preg_split("# *[,;\|]+ *#i", $matches[2]));
            return;
        }
        //TODO Throw notice "Invalid Column Type '$value' for $this->field in $this->model"
    }

    protected function set_length($value)
    {
        $this->length = $value;
    }

    protected function set_null($value)
    {
        if ($value === null || $value === '') {
            $this->null = true;
            return;
        }
        switch ($value) {
            case 'true' : case 1 :
                $this->null = true;
                break;
            case 'false' : case 0 :
                $this->null = false;
                break;
            default :
                $this->null = (bool) $value;
        }
        if ($this->null === false) {
            // TIMESTAMP NOT NULL : DEFAULT CURRENT_TIMESTAMP
            if ($this->type == 'timestamp' && $this->default == null) {
                $this->default = 'current_timestamp';
            }
        }
    }

    protected function set_notnull()
    {
        $this->set_null(false);
    }

    protected function set_default($value)
    {
        $this->default = preg_replace('#["`]#', "'", $value);
    }

    protected function set_index()
    {
        $this->index = true;
    }

    protected function set_encrypted($value)
    {
        if ($value !== false) {
            $this->encrypted = true;
        }
    }

    protected function set_foreign_key($value)
    {
        if (is_array($value)) {
            $this->foreign_key = $value;
        } else {
            if (preg_match(self::PREG_MODEL_FIELD, $value, $matches)) {
                $model = $matches[1];
                $field = $matches[2];

                // Find Foreign Key Table/Column
                $foreignTable = (new ModelData)->fromModel($model);
                if (!$foreignTable || !$foreignTable->getTable() || !$foreignTable->getTable()->table) {
                    //TODO Throw error "Foreign Key Reference '$model' does not exist for $this->field in $this->model"
                    return;
                }
                $foreignColumn = $foreignTable->getColumn($field);
                if (!$foreignColumn || !$foreignColumn->column) {
                    //TODO Throw error "Foreign Key Column Reference '$field' does not exist for $this->field in $this->model"
                    return;
                }

                $this->foreign_key = ['model' => $model, 'field' => $field, 'table' => $foreignTable->getTable()->table, 'column' => $foreignColumn->column];
            } else {
                //TODO Throw notice "Invalid Foreign Key format '$value' for $this->field in $this->model"
            }
        }
    }

    protected function set_ondelete($value)
    {
        switch (strtolower($value)) {
            case 'cascade' :
                $this->ondelete = 'CASCADE';
                break;

            default :
            //TODO Throw notice "Invalid OnDelete '$value' for $this->field in $this->model"
        }
    }

    protected function set_onupdate($value)
    {
        if ($this->foreign_key) {
            switch (strtolower($value)) {
                case 'cascade' :
                    $this->onupdate = 'CASCADE';
                    break;

                default :
                //TODO Throw notice "Invalid OnUpdate '$value' for $this->field in $this->model"
            }
        } else {
            $this->onupdate = preg_replace('#["`]#', "'", $value);
        }
    }

    protected function set_onetomany($value)
    {
        if (preg_match(self::PREG_MODEL_FIELD, $value, $matches)) {
            $this->onetomany = ['model' => $matches[1], 'field' => $matches[2]];
            $this->handler = "onetomany";
        } else {
            //TODO Throw notice "Invalid OneToMany format '$value' for $this->field in $this->model"
        }
    }

    protected function set_onetoone($value)
    {
        if (preg_match(self::PREG_MODEL_FIELD, $value, $matches)) {
            $this->onetoone = ['model' => $matches[1], 'field' => $matches[2]];
        } else {
            //TODO Throw notice "Invalid OneToOne format '$value' for $this->field in $this->model"
        }
    }

    protected function set_manytoone($value)
    {
        if (preg_match(self::PREG_MODEL_FIELD, $value, $matches)) {
            $this->manytoone = ['model' => $matches[1], 'field' => $matches[2]];
        } else {
            //TODO Throw notice "Invalid ManyToOne format '$value' for $this->field in $this->model"
        }
    }

    protected function set_manytomany($value)
    {
        if (preg_match(self::PREG_MODEL_FIELD, $value, $matches)) {
            $this->manytomany = ['model' => $matches[1], 'field' => $matches[2]];
        } else {
            //TODO Throw notice "Invalid ManyToMany format '$value' for $this->field in $this->model"
        }
    }
    
    protected function set_lazy($value) {
        $this->lazy = true;
        $this->join = false;
    }

    protected function set_join($value) {
        $this->lazy = false;
        $this->join = true;
    }
    
    protected function set_sort($value) {
        $this->sort = $value;
    }
    
    protected function set_filter($value) {
        $this->filter = $value;
    }
    
    ///////////////////////////////////////////////////////////////////////

    const PREG_MODEL_FIELD = "#^\(?([a-z_]+[a-z0-9_]*)[:\.\$\#, >-]*\(?([a-z_]+[a-z0-9_]*)\)?$#i";

    public function __get($key)
    {
        $func = "get_" . $key;
        if (method_exists($this, $func)) {
            return $this->$func();
        } else {
            return $this->$key;
        }
    }

    public function __construct($model, $fieldName, array $array = array())
    {
        $this->model = $model;
        $this->field = $fieldName;
        foreach ($array as $key => $value) {
            $func = "set_" . $key;
            if (method_exists($this, $func)) {
                $this->$func($value);
            } else {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                } else {
                    //TODO Throw notice "Invalid Meta Option '$key' for $this->field in $this->model"
                }
            }
        }
        if ($this->column === true) {
            $this->set_column($this->field);
        }
    }

}
