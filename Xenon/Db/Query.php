<?php

namespace Xenon\Db;

use \Xenon\Db\Query\Helper\Where;
use \Xenon\Db\Schema\ModelData;
use \Xenon\Db\Database;

class Query
{
    public $model = "";
    public $modelData = null;
    public $table = "";

    protected $query;

    protected $resultset = null;
    protected $resultindex = 0;

    public function __construct($query = "", $model = null) {
        if ($query instanceof Query\Helper\Expr && $model == null) {
            $model = $query->model;
            $query .= "";
        }
        if ($model != null) {
            $this->model = $model;
            $this->modelData = ModelData::get($model);
            $this->table = $this->modelData->getTable();
        }
        $this->query = $query;
    }

    public function __toString() {
        return $this->query;
    }

    public function expr($expression, ...$args) {
        return new Query\Helper\Expr($expression, $this->model, ...$args);
    }

    public function execute(Database $database = null) {
        $this->reset();
        if ($database === null) {
            if (!$this->model) {
                trigger_error("Model or database not set for this query", E_USER_ERROR);
                return;
            }
            $database = Database::getInstanceForModel($this->model);
        }
        $query = (string)$this;
        Database::$queries[] = $query;
        $this->resultset = mysqli_query($database->db, $query);
        if ($this->resultset === false) {
            trigger_error($database->db->error . ", Query: ".$this, E_USER_ERROR);
        }
        return $this;
    }

    public function fetch($field = 0) {
        if (!$this->resultset) {
            $this->execute();
        }
        if (($result = mysqli_fetch_array($this->resultset))) {
            return $result[$field];
        }
        return null;
    }

    public function fetchCount($field = 'COUNT') {
        if (!$this->resultset) {
            $this->execute();
        }
        if (($result = mysqli_fetch_assoc($this->resultset))) {
            return $result[$field];
        }
        return 0;
    }

    public function fetchRow() {
        if (!$this->resultset) {
            $this->execute();
        }
        $result = mysqli_fetch_assoc($this->resultset);
        if ($this->model && $result) {
            $result = new $this->model($result, true);
            $result->initFromQuery($this->__toString(), $this->resultindex);
        }
        $this->resultindex++;
        if (!$result) {
            $this->reset();
            return null;
        }
        return $result;
    }

    /*
        $keyfield can either be null (for indexed array of rows) or string (for associative array of rows based on a specific field key)
        $valuefield can be one of : 
            null
                returns the row model object as is
            "field1" (string)
                returns the value of the specified field
            function($row){...} (callable)
                returns the result of that function which is called for each row
            []  [null]   (array with single null value or empty array)
                returns an array with all model objects sharing the same key
            ['field']  (array with single string value)
                returns an array with all values (from specified field) grouped by rows sharing the same key
            [function($row){...}]  (array with single callable)
                returns an array with all values (calculated using callable) grouped by rows sharing the same key
            ['field1', 'field2'] 
                (array of strings)
                returns an associative array with specified fields and their values
            ['key1' => 'field1', 'key2' => 'field2'] 
                (associative array of strings)
                returns an associative array with custom keys pointing to specified fields and their values
            [['field1', 'field2']]
                (array of arrays of strings)
                returns an associative array of associative arrays with specified fields and their values, grouped by a shared key value
            [['key1' => 'field1', 'key2' => 'field2'] ]
                (array of associative arrays of strings)
                returns an associative array of associative arrays with custom keys pointing to specified fields and their values, grouped by a shared key value
            ['key1' => function($row){...}, 'key2' => function($row){...}]  (array of callables)
                returns an associative array with custom keys and their calculated values using the provided callable
            [['key1' => function($row){...}, 'key2' => function($row){...}]]  (array of callables)
                returns an associative array of associative arrays with custom keys and their calculated values using the provided callable, grouped by a shared key value
            
            when $valuefield is an associative array, we can mix strings and callables
    */
    public function fetchAll($keyfield = 'id', $valuefield = null) {
        $this->execute();
        
        // Handle Array of array
        $is_array_of_array = false;
        if (is_array($valuefield) && count($valuefield) == 1 && isset($valuefield[0])) {
            $is_array_of_array = true;
            $valuefield = $valuefield[0];
        }
        if ($keyfield === null && $is_array_of_array) {
            trigger_error("Must set a keyfield when using grouped associative arrays");
        }

        $results = [];
        while($row = $this->fetchRow()) {
            if (is_null($valuefield)) {
                $val = $row;
            } else if (is_string($valuefield)) {
                $val = $row->$valuefield;
            } else if (is_callable($valuefield)) {
                $val = $valuefield($row);
            } else if (is_array($valuefield)) {
                $val = [];
                if (is_array($valuefield)) {
                    foreach ($valuefield as $key => $field) {
                        if (is_int($key)) $key = $field;
                        if (is_string($field)) {
                            $val[$key] = $row->$field;
                        } else if (is_callable($field)) {
                            $val[$key] = $field($row);
                        }
                    }
                } else {
                    if (is_string($field)) {
                        $val = $row->$field;
                    } else if (is_callable($field)) {
                        $val = $field($row);
                    }
                }
            } else {
                trigger_error("Invalid type for valuefield");
            }
            if ($keyfield === null) {
                $results[] = $val;
            } else {
                if ($is_array_of_array) {
                    $results[$row->$keyfield][] = $val;
                } else {
                    $results[$row->$keyfield] = $val;
                }
            }
        }
        return $results;
    }

    public function fetchAllTableArray($extended = false, $keyfield = 'id') {
        $this->execute();
        $results = [];
        while($row = $this->fetchRow()) {
            $results[$row->$keyfield] = $row->toTableArray($extended);
        }
        return $results;
    }

    public function fetchArray($valuefield = null) {
        $this->execute();
        $results = [];
        while($row = $this->fetchRow()) {
            $results[] = $valuefield===null? $row : $row->$valuefield;
        }
        return $results;
    }

    public function fetchArrayTableArray($extended = false) {
        $this->execute();
        $results = [];
        while($row = $this->fetchRow()) {
            $results[] = $row->toTableArray($extended);
        }
        return $results;
    }

    public function reset() {
        if ($this->resultset) mysqli_free_result($this->resultset);
        $this->resultset = null;
        $this->resultindex = 0;
        return $this;
    }


    // Raw methods from mysqli
    public function fetch_assoc() {
        if (!$this->resultset) {
            $this->execute();
        }
        $result = mysqli_fetch_assoc($this->resultset);
        $this->resultindex++;
        if (!$result) {
            $this->reset();
            return null;
        }
        return $result;
    }
    public function fetch_array(...$args) {
        if (!$this->resultset) {
            $this->execute();
        }
        $result = mysqli_fetch_array($this->resultset, ...$args);
        $this->resultindex++;
        if (!$result) {
            $this->reset();
            return null;
        }
        return $result;
    }
    public function fetch_all(...$args) {
        $this->execute();
        $results = mysqli_fetch_all($this->resultset, ...$args);
        $this->reset();
        return $results;
    }


}
