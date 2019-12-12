<?php 

namespace Xenon\Db\Query;

use \Xenon\Db\Query\Helper\Where;
use \Xenon\Db\Schema\ModelData;
use \Xenon\Db\Database;

class Select extends \Xenon\Db\Query
{
    protected $fields = [];

    protected $where = null;
    protected $having = "";
    protected $groupby = "";
    protected $orderby = "";
    protected $limit = "";
    protected $offset = "";
    
    /**
     * @param string $model
     * @param mixed $fields
     */
    public function __construct($model, $fields = "*") {
        parent::__construct("SELECT", $model);
        $this->addFields($fields);
    }
    
    public function __toString() {
        return $this->query." ".implode(', ', $this->fields)
                ." FROM `".$this->table."`"
                .($this->where ? " WHERE ".$this->where : '')
                .($this->having ? " HAVING ".$this->having : '')
                .($this->groupby ? " GROUP BY ".$this->groupby : '')
                .($this->orderby ? " ORDER BY ".$this->orderby : '')
                .($this->limit || $this->offset ? " LIMIT ".((int)$this->limit).($this->offset? " OFFSET ".$this->offset:'') : '')
                ;
    }
    
    public function __clone() {
        parent::__clone();
        if (is_object($this->where)) $this->where = clone $this->where;
    }

    public function addFields($fields) {
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as $as => $field) {
            if (!($field instanceof Helper\Field)) {
                $field = new Helper\Field($this->model, $field, $as);
            }
            array_push($this->fields, $field);
        }
        return $this;
    }

    public function setFields($fields) {
        $this->fields = [];
        return $this->addFields($fields);
    }
    
    public function having($expr, ...$args) {
        $this->having .= (($this->having? " AND " : "").(new Helper\Expr($expr, $this->model, $args)));
        return $this;
    }

    public function groupBy($expr) {
        $this->groupby = "".$expr;
        return $this;
    }

    public function orderBy($fields) {
        if (!empty($fields)) {
            if (!is_array($fields)) {
                if ($fields instanceof Helper\Expr) {
                    $fields = [$fields];
                } else if (preg_match("#^\s*(\w+)\s+((a|de)sc)?\s*$#i", $fields, $matches)) {
                    $fields = [$matches[1] => strtoupper(!empty($matches[2])?$matches[2]:'ASC')];
                } else {
                    $fields = [new Helper\Expr($fields)];
                }
            }
            $this->orderby = "";
            foreach ($fields as $key => $value) {
                $this->orderby .= ($this->orderby? ", ":"");
                if ($value instanceof Helper\Expr) {
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
                    if (preg_match("#^([a-z_][a-z0-9_]*)\s+((A|DE)SC)$#i", $key, $matches)) {
                        $key = $matches[1];
                        $value = $matches[2];
                    }
                    $this->orderby .= (new Helper\Field($this->model, $key)) . " " . strtoupper($value);
                }
            }
        } else {
            trigger_error("Error: Fields param cannot be empty in orderBy clause", E_USER_ERROR);
        }
        return $this;
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
        if ($this->where) $this->where->where(...$args);
        else $this->where = new Where($this->model, ...$args);
        return $this;
    }

    public function andWhere(...$args) {
        if ($this->where) $this->where->andWhere(...$args);
        else $this->where = new Where($this->model, ...$args);
        return $this;
    }

    public function orWhere(...$args) {
        if ($this->where) $this->where->orWhere(...$args);
        else $this->where = new Where($this->model, ...$args);
        return $this;
    }

}
