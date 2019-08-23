<?php 

namespace Xenon\Db\Query\Helper;

use \Xenon\Db\Database;

class Where {
    public $model = "";
    protected $where = "";
    public $orderByPriority = [];
    
    /**
     * Typical args : 
     *      $where
     *      $model, $expression
     *      $model, $expression, ...$values
     *      $model, $field
     *      $model, $field, $value
     *      $model, $field, $operator, $value
     */
    public function __construct(...$args) {
        if (count($args) > 0) {
            if ($args[0] instanceof Where || $args[0] instanceof Expr) {
                $this->model = $args[0]->model;
                $this->where = $args[0]->__toString();
                return;
            }
            $this->model = array_shift($args);
            if (count($args) > 0) {
                $this->where = array_shift($args);
                if (!($this->where instanceof Field || $this->where instanceof Expr)) {
                    $field = null;
                    $operator = '=';
                    
                    // If match a field name
                    if (preg_match("#^[a-z_]+\w*$#i", $this->where)) {
                        $this->where = new Field($this->model, $this->where);
                        if ($this->where->modelData) {
                            $field = $this->where->modelData->getFieldOrColumn($this->where->field);
                        }
                        if (count($args) > 0) {
                            if (count($args) == 2) {
                                $operator = array_shift($args);
                            } else if (is_array($args[0])) {
                                $operator = "in";
                            }
                            $this->where .= " $operator ?";
                        }
                    }
                    
                    if (count($args) > 0) {
                        if (count($args) == 1) {
                            if ($field && $field->type == 'tinyint' && $operator == '=') {
                                if ($args[0] === null) {
                                    $this->where = "$field IS NULL";
                                    $args = [];
                                } else if ($args[0] === true) {
                                    $this->where = "($field IS NOT NULL AND $field = 1)";
                                    $args = [];
                                } else if ($args[0] === false) {
                                    $this->where = "($field IS NULL OR $field = 0)";
                                    $args = [];
                                }
                            } else {
                                // Handle special case with empty array IN () / NOT IN ()
                                if (is_array($args[0]) && empty($args[0])) {
                                    $expression = "".$this->where;
                                    if (preg_match("#^\s*[a-z_]+\w*\s*in\s*\(?\?\)?\s*$#i", $expression)) {
                                        // WHERE field IN ()
                                        $this->where = "0";
                                        $args = [];
                                        return;
                                    } else if (preg_match("#^\s*[a-z_]+\w*\s*not\s*in\s*\(?\?\)?\s*$#i", $expression)) {
                                        // WHERE field NOT IN ()
                                        $this->where = "1";
                                        $args = [];
                                        return;
                                    }
                                }
                            }
                        }

                        $this->where = new Expr($this->where, $this->model, ...$args);
                    }
                }
            }
        }
    }
    
    public function where(...$args) {
        $this->addWhere(new Where(...$args));
    }
    
    public function andWhere(...$args) {
        $this->addWhere(new Where(...$args), 'AND');
    }
    
    public function orWhere(...$args) {
        $this->addWhere(new Where(...$args), 'OR');
    }
    
    protected function addWhere(Where $where, $op = 'AND') {
        if ($this->where !== '') $this->where .= " $op ";
        $this->where .= $where;
        $this->orderByPriority["$where"] = "DESC";
    }

    public static function fromArray($model, array $arr) {
        $where = new \Xenon\Db\Query\Helper\Where($model);
        foreach ($arr as $key => $value) {
            if (is_numeric($key)) $where->andWhere($model, $value);
            else $where->andWhere($model, $key, $value);
        }
        return $where;
    }
    
    public function __toString() {
        return "(".($this->where !== '' ? $this->where : '1').")";
    }
}