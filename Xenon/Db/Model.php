<?php 

namespace Xenon\Db;

use \Xenon\Db\Query\Helper\Expr;
use \Xenon\Db\Query\Helper\Field;

class Model
{
    
    public $_isnew = null;
    public $_query = null;
    public $_index = null;
    public $_modelData = null;
    public $_original_values = [];
    public $_lazyLoad = false;
    
    public $_children = [];
    public $_dirty = false;
    
    // DEFAULT FIELDS
    /** @Column @id */
    public $id;

    
    public function __construct(array $values = [], $rawData = false) {
        $this->_isnew = true;
        $this->_modelData = Schema\ModelData::get(get_called_class());
        if ($rawData) {
            foreach ($values as $key => $value) {
                $this->$key = $value;
            }
        } else {
            foreach ($this->_modelData->getColumns() as $column => $columnData) {
                if ($columnData->type == 'timestamp' && $columnData->default == 'current_timestamp') {
                    $this->{$columnData->column} = (new Query\Helper\DateTime)->format();
                } else {
                    $this->__set($column, isset($values[$column]) ? $values[$column] : ($columnData->id && $columnData->null ? NULL : $columnData->default));
                }
            }
        }
    }
    
    public function initFromQuery($query, $index) {
        $this->_isnew = false;
        $this->_query = $query;
        $this->_index = $index;
        $this->_dirty = false;
        
        foreach ($this->_modelData->getColumns() as $column => $columnData) {
            $columnName = $columnData->column;
            $this->_original_values[$column] = $this->$columnName;
        }
    }
    
    public function reset() {
        $this->_dirty = false;
        $this->_children = [];
        foreach ($this->_modelData->getColumns() as $column => $columnData) {
            $columnName = $columnData->column;
            if (isset($this->_original_values[$column])) {
                $this->$columnName = $this->_original_values[$column];
            } else {
                $this->__set($column, $columnData->default);
            }
        }
    }
    
    public static function fetchAll() {
        $query = new Query\Select(get_called_class());
        
        $query->execute();
        $results = [];
        while($row = $query->fetchRow()) {
            $results[$row->id] = $row;
        }
        return $results;
    }
    
    public static function fetchById($id) {
        return (new Query\Select(get_called_class()))->where("id", $id)->fetchRow();
    }
    
    public static function select(...$args) {
        return new Query\Select(get_called_class(), ...$args);
    }
    
    public function count($columnName = null, $where = null, $distinct = null) {
        if ($columnName) {
            $column = $this->_modelData->getField($columnName);
            // OneToMany
            if ($column && $column->onetomany && ($foreighColumn = (new Schema\ModelData)->get($column->onetomany['model'])->getField($column->onetomany['field'])) && $foreighColumn->manytoone) {
                $query = (new Query\Select($column->onetomany['model'], ['COUNT' => new Expr("COUNT(".($distinct? "DISTINCT($distinct)" : "*").")")]))
                    ->where($column->onetomany['field'], $this->{$foreighColumn->manytoone['field']});
                if ($where !== null) $query->andWhere($where);
                return $query->fetchCount();
            }
            
        }
    }
    
    public function save() {
        $model = get_called_class();
        $table = $this->_modelData->getTable();
        $link = Database::getInstanceForModel($model)->db;
        
        // INSERT
        if ($this->_isnew || !$this->id) {
            $self = $this;
            $values = [];
            $columns = implode(', ', 
                array_map(
                    function($columnData) use(&$values, $model, $self) {
                        $values[] = $self->$columnData;
                        return new Field($model, $columnData);
                    }, 
                    $this->_modelData->getColumns()
                )
            );
            $query = new Query(new Expr("INSERT INTO `$table`($columns) VALUES(?)", $model, $values));
            $query->execute();
            $this->id = mysqli_insert_id($link);
            $this->_lazyLoad = true;
            
        // UPDATE
        } else {
            $values = "";
            foreach ($this->_modelData->getColumns() as $columnData) {
                if ($columnData->onupdate == 'current_timestamp') {
                    $this->$columnData = new Query\Helper\DateTime();
                }
                if (array_key_exists($columnData->column, $this->_original_values) && $this->$columnData != $this->_original_values[$columnData->column]) {
                    if ($values != "") $values .= ", ";
                    $values .= new Field($model, $columnData) . " = '" . mysqli_real_escape_string($link, $this->$columnData) . "'";
                }
            }
            if ($values != "") {
                $query = new Query(new Expr("UPDATE `$table` SET $values WHERE `id` = ?", $model, $this->id));
                $query->execute();
                $this->_lazyLoad = true;
            }
        }
        
        $this->_dirty = false;
        
        foreach ($this->_children as $child) {
            $child->save();
        }
        
        return $this;
    }
    
    public function delete() {
        $model = get_called_class();
        $table = $this->_modelData->getTable();
        $query = new Query(new Expr("DELETE FROM `$table` WHERE `id` = ?", $model, $this->id));
        $query->execute();
    }
    
    public function reload() {
        $this->_lazyLoad = false;
        $query = (new Query\Select(get_called_class()))->where("id", $this->id);
        $tmp = $query->fetchRow();
        $this->_original_values = $tmp->_original_values;
        $this->reset();
        $this->_isnew = false;
        $this->_query = $query;
        $this->_index = 0;
    }
    
    public function __get($name) {
        if ($this->_lazyLoad) $this->reload();
        $func = "get_" . $name;
        if (method_exists($this, $func)) {
            return $this->$func();
        } else {
            $column = $this->_modelData->getField($name);
            if ($column) {
                $columnName = $column->getColumnName();
                $handler_func = "handler_get_".$column->handler;
                if (method_exists($this, $handler_func)) {
                    $value = $this->$columnName;
                    if ($column->encrypted) $value = static::decrypt($value, $columnName);
                    return static::$handler_func($value, $column, $this);
                } else {
                    //TODO Throw error Handler method '$handler_func' not found in model
                }
            } else {
                //TODO Throw error Column '$columnName' not found
            }
        }
    }
    
    public function __set($name, $value) {
        if ($this->_lazyLoad) $this->reload();
        $this->_dirty = true;
        $func = "set_" . $name;
        $column = $this->_modelData->getField($name);
        if ($column) {
            $columnName = $column->getColumnName();
            if (method_exists($this, $func)) {
                $this->$columnName = $this->$func($value);
            } else {
                $handler_func = "handler_set_".$column->handler;
                if (method_exists($this, $handler_func)) {
                    $value = static::$handler_func($value, $column);
                    if ($column->encrypted) $value = static::encrypt($value, $columnName);
                    $this->$columnName = $value;
                } else {
                    //TODO Throw error Handler static method '$handler_func' not defined in model
                }
            }
        } else {
            //TODO Throw error Column '$columnName' not found
        }
    }
    
    public static function encrypt($value, $columnName) {
        $iv = bin2hex(openssl_random_pseudo_bytes(8));
        return $iv.openssl_encrypt($value, \Xenon\Config\Security::$DB_ENCRYPTION_METHOD, "~!#&ff".get_called_class().$columnName.\Xenon\Config\Security::$DB_ENCRYPTION_KEY, false, $iv);
    }
    
    public static function decrypt($value, $columnName) {
        $iv = substr($value, 0, 16);
        if (strlen($iv) != 16) return null;
        return openssl_decrypt(substr($value, 16), \Xenon\Config\Security::$DB_ENCRYPTION_METHOD, "~!#&ff".get_called_class().$columnName.\Xenon\Config\Security::$DB_ENCRYPTION_KEY, false, $iv);
    }
    

    /////////////////////////////////////////////////////////////////////////////
    // Handlers
    
    // string
    public static function handler_get_string($value, Schema\Column $column) {
        return $value;
    }
    public static function handler_set_string($value, Schema\Column $column) {
        return $value;
    }
    
    // number
    public static function handler_get_number($value, Schema\Column $column) {
        return $value;
    }
    public static function handler_set_number($value, Schema\Column $column) {
        return $value;
    }
    
    // date
    public static function handler_get_date($value, Schema\Column $column) {
        return new Query\Helper\DateTime($value);
    }
    public static function handler_set_date($value, Schema\Column $column) {
        return (new Query\Helper\DateTime($value))->format();
    }
    
    // time
    public static function handler_get_time($value, Schema\Column $column) {
        return $value;
    }
    public static function handler_set_time($value, Schema\Column $column) {
        return $value;
    }
    
    // bool
    public static function handler_get_bool($value, Schema\Column $column) {
        return !!$value;
    }
    public static function handler_set_bool($value, Schema\Column $column) {
        return $value ? '1':'0';
    }
    
    // json
    public static function handler_get_json($value, Schema\Column $column) {
        return json_decode($value, true);
    }
    public static function handler_set_json($value, Schema\Column $column) {
        return json_encode($value);
    }
    
    // id
    public static function handler_get_id($value, Schema\Column $column) {
        return $value;
    }
    public static function handler_set_id($value, Schema\Column $column) {
        return $value;
    }
    
    // enum
    public static function handler_get_enum($value, Schema\Column $column) {
        return $value;
    }
    public static function handler_set_enum($value, Schema\Column $column) {
        return $value;
    }
    
    // onetomany
    public static function handler_get_onetomany($value, Schema\Column $column, $modelRow) {
        if ($value) return $value;
        
        if ($column->onetomany && ($foreighColumn = (new Schema\ModelData)->get($column->onetomany['model'])->getField($column->onetomany['field'])) && $foreighColumn->manytoone) {
            $query = (new Query\Select($column->onetomany['model']))
                    ->where($column->onetomany['field'], $modelRow->{$foreighColumn->manytoone['field']});
            if ($column->filter) $query->where(new Expr($column->filter));
            if ($column->sort) $query->orderBy($column->sort);
            $values = $column->{$column->field} = $query->fetchAll();
            $modelRow->_children = array_merge($modelRow->_children, $values);
            return $values;
        } else {
            //TODO Throw error : OneToMany not configured properly on both sides
        }
    }
    public static function handler_set_onetomany($value, Schema\Column $column) {
        if ($value === null) return;
        if ($column->onetomany && ($foreighColumn = (new Schema\ModelData)->get($column->onetomany['model'])->getField($column->onetomany['field'])) && $foreighColumn->manytoone) {
            //TODO
        } else {
            //TODO Throw error : OneToMany not configured properly on both sides
        }
    }
    
    
}
