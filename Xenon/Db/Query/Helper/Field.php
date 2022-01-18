<?php

namespace Xenon\Db\Query\Helper;

use \Xenon\Db\Query\Select;
use \Xenon\Db\Schema\ModelData;
use \Xenon\Db\Database;
use \Xenon\Db\Schema\Column;

class Field {
    public $model = "";
    public $modelData = null;
    public $table = "";
    public $field = "";
    public $column = "";
    public $alias = "";
    public $expression = "";

    public function __construct($model, $field = "*", $as = "") {

        if ($model) {
            $this->modelData = ModelData::get($model);
            if ($this->modelData) {
                $this->table = $this->modelData->getTable();
            } else {
                trigger_error("Invalid model '$model'", E_USER_ERROR);
                $model = "";
            }
            $this->model = $model;
        }

        // AS
        if (is_numeric($as)) {
            $as = "";
        }
        if ($as != "") {
            $this->alias = $as;
            $as = " AS '".$this->alias."'";
        }

        // Subquery
        if ($field instanceof Select) {
            $this->expression = "(".$field.")".$as;
            return;
        }

        // Column object
        if ($field instanceof Column) {
            if ($model) {
                $this->expression = "`".$this->table."`.`".$field."`".$as;
            } else {
                $this->expression = "`".$field."`".$as;
            }
            $this->column = $field->column;
            return;
        }

        // Wildcard
        if ($field === "*") {
            if ($model) {
                $this->expression = "`".$this->table."`.*";
            } else {
                $this->expression = "*";
            }
            return;
        }

        // other expressions
        if (!$model || $field instanceof Expr || !preg_match("#^[a-z_][a-z0-9_]*$#i", $field)) {
            $this->expression = "".$field.$as;
            return;
        }

        // Field name
        if ($this->modelData) {
            $f = $this->modelData->getFieldOrColumn($field);
            if (!$f) {
                trigger_error("Error in order close : Field $field does not exist in model ".$this->model, E_USER_ERROR);
                return;
            }
            $field = $f;
        }
        $this->field = $this->model? Database::getInstanceForModel($this->model)->db->real_escape_string($field) : $field;
        $this->expression = "`".$this->table."`.`".$this->field."`".$as;
    }

    public function __toString() {
        return $this->expression;
    }
}
