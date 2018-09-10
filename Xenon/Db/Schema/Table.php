<?php

namespace Xenon\Db\Schema;

class Table
{
    public $model;
    //
    protected $table = null;
    protected $engine = 'InnoDB';
    protected $charset = 'utf8';
    protected $collate = 'utf8_general_ci';

    public function getTableName()
    {
        return $this->table;
    }

    protected function set_table($value)
    {
        $value = strtolower($value);
        if (preg_match("#^[a-z_][a-z0-9_-]*$#", $value)) {
            $this->table = $value;
        } else {
            //TODO Throw notice "Invalid Table Name '$value' in $this->model"
        }
    }

    protected function set_engine($value)
    {
        switch (strtolower($value)) {
            case 'innodb' :
                $this->engine = 'InnoDB';
                break;
            case 'myisam' :
                $this->engine = 'MyISAM';
                break;

            default :
            //TODO Throw notice "Invalid Value '$value' for Engine in $this->model"
        }
    }

    protected function set_charset($value)
    {
        switch (strtolower($value)) {
            case 'utf8' : case 'utf-8' :
                $this->charset = 'utf8';
                break;

            default :
                $this->charset = $value;
        }
    }

    protected function set_collate($value)
    {
        switch (strtolower($value)) {
            case 'utf8_general_ci' : case 'utf8' : case 'utf-8' :
                $this->collate = 'utf8_general_ci';
                break;

            default :
                $this->collate = $value;
        }
    }

    /////////////////////////////////////////////////////////////////////////////////

    public function __get($key)
    {
        $func = "get_" . $key;
        if (method_exists($this, $func)) {
            return $this->$func();
        } else {
            return $this->$key;
        }
    }

    public function __construct($model, array $array = array())
    {
        $this->model = $model;
        foreach ($array as $key => $value) {
            $func = "set_" . $key;
            if (method_exists($this, $func)) {
                $this->$func($value);
            } else {
                if (property_exists($this, $key)) {
                    $this->$key = $value;
                } else {
                    //TODO Throw notice "Invalid Meta Option '$key' for $this->model"
                }
            }
        }

        if ($this->table == null) {
            $this->set_table($this->model);
        }
    }

    public function __toString() {
        return $this->table;
    }
    
}
