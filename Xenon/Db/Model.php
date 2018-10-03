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
                $fieldName = @$this->_modelData->getColumn($key)->field;
                if ($fieldName) $this->$fieldName = $value;
            }
        } else {
            foreach ($this->_modelData->getFields() as $field => $columnData) {
                if ($columnData->type == 'timestamp' && $columnData->default == 'current_timestamp') {
                    $this->{$columnData->column} = (new Query\Helper\DateTime)->format();
                } else {
                    $this->__set($field, isset($values[$field]) ? $values[$field] : ($columnData->id && $columnData->null ? NULL : $columnData->default));
                }
            }
        }
    }

    public function initFromQuery($query, $index) {
        $this->_isnew = false;
        $this->_query = $query;
        $this->_index = $index;
        $this->_dirty = false;

        foreach ($this->_modelData->getFields() as $field => $columnData) {
            $this->_original_values[$field] = $this->$field;
        }
    }

    public function reset() {
        $this->_dirty = false;
        $this->_children = [];
        foreach ($this->_modelData->getFields() as $field => $columnData) {
            if (isset($this->_original_values[$field])) {
                $this->$field = $this->_original_values[$field];
            } else {
                $this->__set($field, $columnData->default);
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

    public static function fetchBy($field, $value = null) {
        $query = new Query\Select(get_called_class());
        if (is_array($field) && $value === null) {
            foreach ($field as $k => $v) {
                $query->andWhere($k, $v);
            }
        } else {
            $query->where($field, $value);
        }
        return $query->fetchRow();
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
            if ($column && $column->onetomany && ($foreignColumn = (new Schema\ModelData)->get($column->onetomany['model'])->getField($column->onetomany['field'])) && $foreignColumn->manytoone) {
                $query = (new Query\Select($column->onetomany['model'], ['COUNT' => new Expr("COUNT(".($distinct? "DISTINCT($distinct)" : "*").")")]))
                    ->where($column->onetomany['field'], $this->{$foreignColumn->manytoone['field']});
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
                        $values[] = $self->{$columnData->field};
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
            if ($child) $child->save();
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
        return $this->get($name);
    }

    public function __set($name, $value) {
        return $this->set($name, $value);
    }

    public function get($name, $lang = LANG) {
        if ($this->_lazyLoad) $this->reload();
        $func = "get_" . $name;
        if (method_exists($this, $func)) {
            return $this->$func($lang);
        } else {
            $column = $this->_modelData->getFieldOrColumn($name);
            if ($column) {
                $columnName = $column->field;
                $handler_func = "handler_get_".$column->handler;
                if (method_exists($this, $handler_func)) {
                    $value = $this->$columnName;
                    if ($column->translatable) $value = static::getTranslatable($value, $lang);
                    if ($column->encrypted) $value = static::getEncrypted($value, $columnName);
                    return static::$handler_func($value, $column, $this);
                } else {
                    trigger_error("Handler static method '$handler_func' not defined in model ".get_called_class(), E_USER_ERROR);
                }
            } else {
                trigger_error("Column '$name' not found in model ".get_called_class(), E_USER_ERROR);
            }
        }
    }

    public function set($name, $value, $lang = LANG) {
        if ($this->_lazyLoad) $this->reload();
        $this->_dirty = true;
        $func = "set_" . $name;
        $column = $this->_modelData->getFieldOrColumn($name);
        if ($column) {
            $columnName = $column->field;
            if (method_exists($this, $func)) {
                $this->$columnName = $this->$func($value, $lang);
            } else {
                $handler_func = "handler_set_".$column->handler;
                if (method_exists($this, $handler_func)) {
                    $value = static::$handler_func($value, $column, $this);
                    if ($column->encrypted) $value = static::setEncrypted($value, $columnName);
                    if ($column->translatable) $value = static::setTranslatable($this->$columnName, $value, $lang);
                    $this->$columnName = $value;
                } else {
                    trigger_error("Handler static method '$handler_func' not defined in model ".get_called_class(), E_USER_ERROR);
                }
            }
        } else {
            trigger_error("Column '$name' not found in model ".get_called_class(), E_USER_ERROR);
        }
    }

    // @encrypted
    public static function getEncrypted($dbValue, $columnName) {
        $iv = substr($dbValue, 0, 16);
        if (strlen($iv) != 16) return null;
        return openssl_decrypt(substr($dbValue, 16), \Xenon\Config\Security::$DB_ENCRYPTION_METHOD, "~!#&ff".get_called_class().$columnName.\Xenon\Config\Security::$DB_ENCRYPTION_KEY, false, $iv);
    }
    public static function setEncrypted($newValue, $columnName) {
        $iv = bin2hex(openssl_random_pseudo_bytes(8));
        return $iv.openssl_encrypt($newValue, \Xenon\Config\Security::$DB_ENCRYPTION_METHOD, "~!#&ff".get_called_class().$columnName.\Xenon\Config\Security::$DB_ENCRYPTION_KEY, false, $iv);
    }

    // @translatable
    public static function getTranslatable($dbValue, $lang = LANG) {
        if (is_string($dbValue)) {
            if ($dbValue === "") return "";
            try {
                $jsonValue = json_decode($dbValue, true);
                if (gettype($jsonValue) == "NULL") {
                    return $dbValue;
                }
                $dbValue = $jsonValue;
            } catch (\Exception $e) {
                return $dbValue;
            }
        }
        if (!is_array($dbValue)) return $dbValue;
        if (isset($dbValue[$lang])) return $dbValue[$lang];
        if (isset($dbValue[DEFAULT_LANG])) return $dbValue[DEFAULT_LANG];
        if (count($dbValue) == 0) return "";
        return reset($dbValue);
    }
    public static function setTranslatable($dbValue, $newValue, $lang = LANG) {
        if (!is_array($dbValue)) {
            if (gettype($dbValue) == "NULL") {
                $dbValue = [];
            } else if (is_string($dbValue)) {
                if ($dbValue === "") {
                    $dbValue = [];
                } else {
                    try {
                        $jsonValue = json_decode($dbValue, true);
                        if (gettype($jsonValue) == "NULL") {
                            $jsonValue = [DEFAULT_LANG => $dbValue];
                        }
                        $dbValue = $jsonValue;
                    } catch (\Exception $e) {
                        $dbValue = [DEFAULT_LANG => $dbValue];
                    }
                }
            }
        }
        if (is_array($newValue)) {
            $dbValue = array_merge($dbValue, $newValue);
        } else {
            $dbValue[$lang] = $newValue;
        }
        foreach ($dbValue as $key => $value) {
            if (is_int($key)) {
                unset($dbValue[$key]);
            }
        }
        return json_encode($dbValue);
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

    // OneToOne
    public static function handler_get_onetoone($value, Schema\Column $column, $modelRow) {
        if ($column->onetoone) {
            $query = (new Query\Select($column->onetoone['model']))
                    ->where($column->onetoone['field'], $value);
            if ($column->filter) $query->where(new Expr($column->filter));
            if ($column->sort) $query->orderBy($column->sort);
            $row = $column->{$column->field} = $query->fetchRow();
            $modelRow->_children = array_merge($modelRow->_children, [$row]);
            return $row;
        } else {
            trigger_error("OneToOne not configured properly on both sides", E_USER_ERROR);
        }
    }
    public static function handler_set_onetoone($value, Schema\Column $column, $modelRow) {
        if ($value === null) return;
        if ($column->onetoone) {
            if (is_array($value)) {
                $className = $column->onetoone['model'];
                $value = new $className($value);
            }
            if (is_object($value)) {
                if ($value->_isnew) {
                    $value->save();
                }
                $value = $value->{$column->onetoone['field']};
            }
            return $value;
        } else {
            trigger_error("OneToOne not configured properly on both sides", E_USER_ERROR);
        }
    }

    // ManyToOne (handle almost identical to OneToOne...)
    public static function handler_get_manytoone($value, Schema\Column $column, $modelRow) {
        if ($column->manytoone) {
            $query = (new Query\Select($column->manytoone['model']))
                    ->where($column->manytoone['field'], $value);
            if ($column->filter) $query->where(new Expr($column->filter));
            if ($column->sort) $query->orderBy($column->sort);
            $row = $column->{$column->field} = $query->fetchRow();
            $modelRow->_children = array_merge($modelRow->_children, [$row]);
            return $row;
        } else {
            trigger_error("ManyToOne not configured properly on both sides for field `$column->field` in model $column->model", E_USER_ERROR);
        }
    }
    public static function handler_set_manytoone($value, Schema\Column $column, $modelRow) {
        if ($value === null) return;
        if ($column->manytoone) {
            if (is_array($value)) {
                $className = $column->manytoone['model'];
                $value = new $className($value);
            }
            if (is_object($value)) {
                if ($value->_isnew) {
                    $value->save();
                }
                $value = $value->{$column->manytoone['field']};
            }
            return $value;
        } else {
            trigger_error("ManyToOne not configured properly on both sides for field `$column->field` in model $column->model", E_USER_ERROR);
        }
    }

    // OneToMany
    public static function handler_get_onetomany($value, Schema\Column $column, $modelRow) {
        if ($value) return $value;
        if ($column->onetomany && ($foreignColumn = (new Schema\ModelData)->get($column->onetomany['model'])->getField($column->onetomany['field'])) && $foreignColumn->manytoone) {
            $query = (new Query\Select($column->onetomany['model']))
                    ->where($column->onetomany['field'], $modelRow->{$foreignColumn->manytoone['field']});
            if ($column->filter) $query->where(new Expr($column->filter));
            if ($column->sort) $query->orderBy($column->sort);
            $values = $column->{$column->field} = $query->fetchAll();
            $modelRow->_children = array_merge($modelRow->_children, $values);
            return $values;
        } else {
            trigger_error("OneToMany not configured properly on both sides for field `$column->field` in model $column->model", E_USER_ERROR);
        }
    }
    public static function handler_set_onetomany($values, Schema\Column $column, $modelRow) {
        if ($values === null) return;
        if ($column->onetomany && ($foreignColumn = (new Schema\ModelData)->get($column->onetomany['model'])->getField($column->onetomany['field'])) && $foreignColumn->manytoone) {
            if (!is_array($values)) {
                trigger_error("Invalid data passed to OneToMany field `$column->field` in model $column->model", E_USER_ERROR);
            }
            if ($this->_isnew) {
                $this->save();
            }
            foreach ($values as $value) {
                if (is_array($value)) {
                    $className = $column->onetomany['model'];
                    $value = new $className($value);
                }
                if (is_object($value)) {
                    $value->{$column->onetomany['field']} = $this->{$foreignColumn->manytoone['field']};
                    $value->save();
                } else {
                    trigger_error("Invalid data passed to OneToMany field `$column->field` in model $column->model", E_USER_ERROR);
                }
            }
        } else {
            trigger_error("OneToMany not configured properly on both sides for field `$column->field` in model $column->model", E_USER_ERROR);
        }
    }


}
