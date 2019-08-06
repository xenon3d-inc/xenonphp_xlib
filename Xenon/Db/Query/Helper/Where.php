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
                    
                    // If match a field name
                    if (preg_match("#^[a-z_]+\w*$#i", $this->where)) {
                        $this->where = new Field($this->model, $this->where);
                        if (count($args) > 0) {
                            $operator = '=';
                            if (count($args) == 2) {
                                $operator = array_shift($args);
                            } else if (is_array($args[0])) {
                                $operator = "in";
                            }
                            $this->where .= " $operator ?";
                        }
                    }
                    
                    if (count($args) > 0) {

                        // Handle special case with empty array IN () / NOT IN ()
                        if (count($args) == 1 && is_array($args[0]) && empty($args[0])) {
                            $expression = "".$this->where;
                            if (preg_match("#^\s*[a-z_]+\w*\s*in\s*\(?\?\)?\s*$#i", $expression)) {
                                // WHERE field IN ()
                                $this->where = "0";
                                return;
                            } else if (preg_match("#^\s*[a-z_]+\w*\s*not\s*in\s*\(?\?\)?\s*$#i", $expression)) {
                                // WHERE field NOT IN ()
                                $this->where = "1";
                                return;
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
    
    public function __toString() {
        return "(".($this->where !== '' ? $this->where : '1').")";
    }
}