<?php

namespace Xenon\Db\Query\Helper;
use \Xenon\Db\Database;

class Expr {
    public $model = "";
    public $expression = "";

    const TMP_REPLACEMENT_STR = '/%_VALUE_%/';

    public function __construct($expression, $model = "", ...$args) {
        $this->expression = $expression;
        $this->model = $model;

        if (count($args) > 0) {
            // Replace all '?' with values
            if (substr_count($this->expression, '?') <= count($args)) {
                $this->expression = preg_replace("#\(\?\)|'\?'|\?#", Expr::TMP_REPLACEMENT_STR, $this->expression);
                while (strpos($this->expression, Expr::TMP_REPLACEMENT_STR) !== false && count($args) > 0) {
                    $value = array_shift($args);
                    if (!($value instanceof Field || $value instanceof Expr)) {
                        if (is_array($value)) {
                            if (empty($value)) $value = [NULL];
                            $value = "(".implode(",",array_map([$this, 'escapeWithQuotes'], $value)).")";
                        } elseif ($value === null) {
                            $value = "NULL";
                        } else {
                            $value = "'".$this->escape($value)."'";
                        }
                    }
                    $this->expression = preg_replace("#".preg_quote(Expr::TMP_REPLACEMENT_STR)."#", str_replace('$','\$',$value), $this->expression, 1);
                }
            } else {
                trigger_error("Missing replacement values for '?' in expression", E_USER_ERROR);
            }
        }
    }

    public function escapeWithQuotes($value) {
        if ($value === null) return "NULL";
        return "'".Database::getInstanceForModel($this->model)->db->real_escape_string($value)."'";
    }

    public function escape($value) {
        return Database::getInstanceForModel($this->model)->db->real_escape_string($value);
    }

    public function __toString() {
        return $this->expression;
    }
}
