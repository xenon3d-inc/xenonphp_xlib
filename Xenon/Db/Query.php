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

    public function fetchAll($keyfield = 'id', $valuefield = null) {
        $this->execute();
        $results = [];
        while($row = $this->fetchRow()) {
            $results[$row->$keyfield] = $valuefield===null? $row : $row->$valuefield;
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
