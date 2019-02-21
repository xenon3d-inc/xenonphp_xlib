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
    protected $where = null;
    protected $orderby = "";
    protected $limit = "";
    protected $offset = "";

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

    public function orderBy($fields) {
        if (!empty($fields)) {
            if (!is_array($fields)) {
                if (preg_match("#^\s*(\w+)\s+((a|de)sc)?\s*$#i", $fields, $matches)) {
                    $fields = [$matches[1] => strtoupper(!empty($matches[2])?$matches[2]:'ASC')];
                } else {
                    $fields = [new Query\Helper\Expr($fields)];
                }
            }
            $this->orderby = "";
            foreach ($fields as $key => $value) {
                $this->orderby .= ($this->orderby? ", ":"");
                if ($value instanceof Query\Helper\Expr) {
                    $this->orderby .= $value;
                } else {
                    if (is_numeric($key)) {
                        $key = $value;
                        $value = "ASC";
                    }
                    if (!preg_match("#^(A|DE)SC$#i", $value)) {
                        trigger_error("Error: Field values need to be either ASC or DESC in orderBy clause", E_USER_ERROR);
                        return;
                    }
                    $this->orderby .= (new Query\Helper\Field($this->model, $key)) . " " . strtoupper($value);
                }
            }
        } else {
            trigger_error("Error: Fields param cannot be empty in orderBy clause", E_USER_ERROR);
        }
        return $this;
    }

    public function expr($expression, ...$args) {
        return new Query\Helper\Expr($expression, $this->model, ...$args);
    }

    public function offset($offset) {
        if (is_numeric($offset)) {
            $this->offset = $offset;
        } else {
            trigger_error("Error: Param need to be integer for offset sql clause", E_USER_ERROR);
        }
        return $this;
    }

    public function limit($limit, $arg2 = null) {
        if ($arg2) {
            return $this->limit($arg2)->offset($limit);
        }
        if (is_numeric($limit)) {
            $this->limit = $limit;
        } else {
            trigger_error("Error: Param need to be integer for limit sql clause", E_USER_ERROR);
        }
        return $this;
    }

    public function where(...$args) {
        if ($this->where) $this->where->where($this->model, ...$args);
        else $this->where = new Where($this->model, ...$args);
        return $this;
    }

    public function andWhere(...$args) {
        if ($this->where) $this->where->andWhere($this->model, ...$args);
        else $this->where = new Where($this->model, ...$args);
        return $this;
    }

    public function orWhere(...$args) {
        if ($this->where) $this->where->orWhere($this->model, ...$args);
        else $this->where = new Where($this->model, ...$args);
        return $this;
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

    public function reset() {
        if ($this->resultset) mysqli_free_result($this->resultset);
        $this->resultset = null;
        $this->resultindex = 0;
        return $this;
    }
}
