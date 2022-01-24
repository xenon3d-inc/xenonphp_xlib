<?php

namespace Xenon\Db;

use \Xenon\Db\Query\Helper\Expr;
use \Xenon\Db\Query\Helper\Field;

class Model implements \ArrayAccess
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

    // OnSave event: $modifiedColumns will contain only the columns that have been modified with their values, and also, if inserted, the id field with all specified fields.
    public function onSave(array $modifiedColumns) {}

    // Constructor
    public function __construct(array $values = [], $rawData = false) {
        $this->_isnew = true;
        $this->_modelData = Schema\ModelData::get(get_called_class());
        $this->_original_values = $values;
        if ($rawData) {
            foreach ($values as $key => $value) {
                if (strpos($key, '.') === false) {
                    $columnData = @$this->_modelData->getColumn($key);
                    if ($columnData) {
                        $fieldName = $columnData->field;
                        $columnName = $columnData->column;
                        $this->$fieldName = $value;
                        if ($fieldName != $columnName) $this->$columnName = $value;
                    }
                } else {
                    //TODO implement xToOne Automatic JOIN
                }
            }
        } else {
            foreach ($this->_modelData->getFields() as $fieldName => $columnData) {
                $columnName = $columnData->column;
                if (isset($values[$fieldName])) {
                    $value = $values[$fieldName];
                    unset($values[$fieldName]);
                } else if ($fieldName != $columnName && isset($values[$columnName])) {
                    $value = $values[$columnName];
                    unset($values[$columnName]);
                } else {
                    // Value for this field is NOT passed in

                    // If NOTNULL or AUTO_INCREMENT, ignore it and let MySQL set its default value or NULL when inserted
                    if (!$columnData->null || $columnData->auto_increment) {
                        if (!$columnData->auto_increment && !$columnData->default) {
                            trigger_error("Value for column '$columnName' with no default cannot be NULL", E_USER_ERROR);
                        }
                        continue;
                    }

                    if ($columnData->default && $columnData->type != 'timestamp') {
                        // If there is a default value and the type is not a timestamp, set default value, otherwise ignore it
                        $value = $columnData->default;
                    } else {
                        continue;
                    }
                }
                $this->set($fieldName, $value);
                if ($fieldName != $columnName) {
                    $this->set($columnName, $value);
                }
            }
            foreach ($values as $key => $value) {
                $this->set($key, $value);
            }
        }
    }

    public function __toString() {
        return get_called_class()."#".$this->id;
    }

    public static function getProperties($fetchXToOneOptions = false) {
        $modelData = Schema\ModelData::get(get_called_class());
        $properties = [
            'table' => $modelData->getTable(),
        ];
        foreach ($modelData->getFields() as $fieldName => $columnData) {
            $properties['fields'][$fieldName] = [
                'field' => $fieldName,
                'column' => $columnData->column,
                'type' => $columnData->type,
                'dataType' => $columnData->dataType,
                'handler' => $columnData->handler,
                'structure' => $columnData->structure,
                'null' => $columnData->null,
                // 'columnData' => $columnData,
                'enum' => $columnData->enum,
                'length' => $columnData->length,
                'onetoone' => $columnData->onetoone,
                'onetomany' => $columnData->onetomany,
                'manytoone' => $columnData->manytoone,
                'encrypted' => $columnData->encrypted,
                'attributes' => $columnData->attributes,
                'primary_key' => $columnData->id,
                'translatable' => $columnData->translatable,
            ];
            $xToOne = $columnData->manytoone? $columnData->manytoone : $columnData->onetoone;
            if (((is_bool($fetchXToOneOptions) && $fetchXToOneOptions) || is_callable($fetchXToOneOptions)) && $xToOne) {
                $query = $xToOne['model']::select();
                if (!is_callable($fetchXToOneOptions) || $fetchXToOneOptions($fieldName, $columnData, $query)) {
                    if ($columnData->filter) $query->where(new Expr($columnData->filter));
                    if ($columnData->sort) $query->orderBy($columnData->sort);
                    $properties['fields'][$fieldName]['options'] = $query->fetchAll($xToOne['field']);
                }
            }
        }
        return $properties;
    }

    public function toTableArray($extended = false) {
        $rowValues = [];
        foreach ($this->_modelData->getFields() as $fieldName => $columnData) {
            $columnName = $columnData->column;
            if ($columnData->onetomany) {
                $value = [];
            } else if ($columnData->onetoone || $columnData->manytoone) {
                $value = @$this->_original_values[$columnName];
            } else {
                $value = $this->get($fieldName, false);
            }
            if ($extended) {
                $rowValues[$fieldName] = [
                    'value' => $value,
                    'rawvalue' => @$this->_original_values[$columnName],
                    'type' => $columnData->type,
                    'dataType' => $columnData->dataType,
                    'handler' => $columnData->handler,
                    'structure' => $columnData->structure,
                    'null' => $columnData->null,
                    // 'columnData' => $columnData,
                    'enum' => $columnData->enum,
                    'length' => $columnData->length,
                    'onetoone' => $columnData->onetoone,
                    'onetomany' => $columnData->onetomany,
                    'manytoone' => $columnData->manytoone,
                    'encrypted' => $columnData->encrypted,
                    'attributes' => $columnData->attributes,
                    'primary_key' => $columnData->id,
                    'translatable' => $columnData->translatable,
                ];
            } else {
                $rowValues[$fieldName] = $value;
            }
        }
        return $rowValues;
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

    public static function begin() {
        (new Query("begin"))->execute(Database::getInstanceForModel(get_called_class()));
    }

    public static function commit() {
        (new Query("commit"))->execute(Database::getInstanceForModel(get_called_class()));
    }

    public static function fetchAll(...$args) {
        $query = new Query\Select(get_called_class());
        return $query->fetchAll(...$args);
    }

    public static function fetchAllBy($field, $value = null) {
        $query = new Query\Select(get_called_class());
        if (is_array($field) && $value === null) {
            foreach ($field as $k => $v) {
                $query->andWhere($k, $v);
            }
        } else {
            $query->where($field, $value);
        }
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

    public static function selectForUpdate(...$args) {
        return new Query\SelectForUpdate(get_called_class(), ...$args);
    }

    public static function selectCount($fields = "*") {
        return new Query\Select(get_called_class(), "COUNT($fields) AS 'COUNT'");
    }

    public static function query($queryExpr, ...$args) {
        return new Query(new Query\Helper\Expr($queryExpr, get_called_class(), ...$args));
    }

    public static function exists($value, $field = 'id') {
        return !!((new Query\Select(get_called_class(), $field))->where($field, $value)->limit(1)->fetch());
    }

    public function count($fieldName = null, $where = null, $distinct = null) {
        if ($fieldName && ($column = $this->_modelData->getField($fieldName))) {
            // OneToMany
            if ($column->onetomany) {
                if (($foreignColumn = (new Schema\ModelData)->get($column->onetomany['model'])->getField($column->onetomany['field'])) && $foreignColumn->manytoone) {
                    $query = (new Query\Select($column->onetomany['model'], ['COUNT' => new Expr("COUNT(".($distinct? "DISTINCT($distinct)" : "*").")")]))
                        ->where($column->onetomany['field'], $this->{$foreignColumn->manytoone['field']});
                    if ($where !== null) $query->andWhere($where);
                    return $query->fetchCount();
                }
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
                array_filter(array_map(
                    function($columnData) use(&$values, &$insertedColumnValues, $model, $self) {
                        if (isset($self->{$columnData->column})) $val = $self->{$columnData->column};
                        else if (isset($self->{$columnData->field})) $val = $self->{$columnData->field};
                        else if (array_key_exists($columnData->column, $self->_original_values)) $val = $self->_original_values[$columnData->column];
                        else if (array_key_exists($columnData->field, $self->_original_values)) $val = $self->_original_values[$columnData->field];
                        else return false; // If value was not passed to the object in any way, do not put it in the insert clause
                        if ($val === '' && in_array($columnData->type, \Xenon\Db\Schema\Column::$AUTONULL_TYPES)) {
                            $val = null;
                        }
                        $values[$columnData->column] = $val;
                        return new Field($model, $columnData);
                    },
                    $this->_modelData->getColumns()
                ))
            );
            $query = new Query(new Expr("INSERT INTO `$table`($columns) VALUES(?)", $model, array_values($values)));
            $query->execute();
            $this->id = mysqli_insert_id($link);
            $this->_lazyLoad = true;

            $this->onSave(['id' => $this->id] + $self->_original_values + $values);

        // UPDATE
        } else {
            $values = "";
            $replacements = [];
            foreach ($this->_modelData->getColumns() as $columnData) {
                if ($columnData->onupdate == 'current_timestamp') {
                    $this->$columnData = new Query\Helper\DateTime();
                }
                $val = $this->$columnData;
                if (array_key_exists($columnData->column, $this->_original_values) && $val != $this->_original_values[$columnData->column]) {
                    $field = new Field($model, $columnData);
                    if ($values != "") $values .= ", ";
                    $values .= "$field = ?";
                    if ($val === '' && in_array($columnData->type, \Xenon\Db\Schema\Column::$AUTONULL_TYPES)) {
                        $val = null;
                    }
                    $replacements[$field->column] = $val;
                }
            }
            if ($values != "") {
                $query = new Query(new Expr("UPDATE `$table` SET $values WHERE `id` = ?", $model, ...array_values($replacements+['id'=>$this->id])));
                $query->execute();
                $this->_lazyLoad = true;

                $this->onSave($replacements);
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

    public function reloadForUpdate() {
        if (!$this->id) $this->save();
        $this->_lazyLoad = false;
        $query = (new Query\SelectForUpdate(get_called_class()))->where("id", $this->id);
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

    public function get($name, $lang = null) {
        if ($this->_lazyLoad) $this->reload();
        $func = "get_" . $name;
        if (method_exists($this, $func)) {
            return $this->$func($lang);
        } else {
            $columnData = $this->_modelData->getFieldOrColumn($name);
            if ($columnData) {
                $fieldName = $columnData->field;
                $columnName = $columnData->column;
                // If we are getting the column name and its different than the field name, return the raw unparsed value directly from the database
                if ($fieldName != $columnName && $name == $columnName) {
                    if (isset($this->_original_values[$columnName])) {
                        return $this->_original_values[$columnName];
                    } else if (isset($this->_original_values[$fieldName])) {
                        return $this->_original_values[$fieldName];
                    } else if (isset($this->$columnName)) {
                        return $this->$columnName;
                    } else if (isset($this->$fieldName)) {
                        return $this->$fieldName;
                    }
                    return null;
                }
                $handler_func = "handler_get_".$columnData->handler;
                if (method_exists($this, $handler_func)) {
                    $value = $this->$fieldName;
                    if ($columnData->translatable) $value = static::getTranslatable($value, $lang);
                    if ($columnData->encrypted) $value = static::getEncrypted($value, $fieldName);
                    return static::$handler_func($value, $columnData, $this);
                } else {
                    trigger_error("Handler static method '$handler_func' not defined in model ".get_called_class(), E_USER_ERROR);
                }
            } else if (array_key_exists($name, $this->_original_values)) {
                return $this->_original_values[$name];
            } else {
                trigger_error("Column '$name' not found in model ".get_called_class(), E_USER_ERROR);
            }
        }
    }

    public function set($name, $value, $lang = null) {
        if (!$name) return;
        if ($this->_lazyLoad) $this->reload();
        $this->_dirty = true;
        $func = "set_" . $name;
        if (method_exists($this, $func)) {
            $this->$func($value, $lang);
        } else {
            $columnData = $this->_modelData->getFieldOrColumn($name);
            if ($columnData) {
                $fieldName = $columnData->field;
                $columnName = $columnData->column;
                // If we are setting the column name and its different than the field name, set the raw value directly into the database
                if ($fieldName != $columnName && $name == $columnName) {
                    return $this->$columnName = $value;
                }
                $handler_func = "handler_set_".$columnData->handler;
                if (method_exists($this, $handler_func)) {
                    $value = static::$handler_func($value, $columnData, $this);
                    if ($columnData->encrypted) $value = static::setEncrypted($value, $fieldName);
                    if ($columnData->translatable) $value = static::setTranslatable($this->$fieldName, $value, $lang);
                    if (($columnData->handler === 'onetoone' || $columnData->handler === 'manytoone') && $fieldName != $columnName) {
                        $this->$columnName = $value;
                    } else {
                        $this->$fieldName = $value;
                    }
                } else {
                    trigger_error("Handler static method '$handler_func' not defined in model ".get_called_class(), E_USER_ERROR);
                }
            } else {
                trigger_error("Column '$name' not found in model ".get_called_class(), E_USER_ERROR);
            }
        }
        return $value;
    }

    // @encrypted
    public static function getEncrypted($dbValue, $columnName) {
        $iv = substr($dbValue, 0, 16);
        if (strlen($iv) != 16) return null;
        return openssl_decrypt(substr($dbValue, 16), \Xenon\Config\Security::$DB_ENCRYPTION_METHOD, \Xenon\Config\Security::$DB_ENCRYPTION_KEY.(get_called_class().$columnName)."~!#&ff", false, $iv);
    }
    public static function setEncrypted($newValue, $columnName) {
        $iv = bin2hex(openssl_random_pseudo_bytes(8));
        return $iv.openssl_encrypt($newValue, \Xenon\Config\Security::$DB_ENCRYPTION_METHOD, \Xenon\Config\Security::$DB_ENCRYPTION_KEY.(get_called_class().$columnName)."~!#&ff", false, $iv);
    }

    // @translatable
    public static function getTranslatable($dbValue, $lang = null) {
        if ($lang === false) return $dbValue;
        if ($lang === null) {
            if (defined('LANG')) $lang = LANG;
            else $lang = DEFAULT_LANG;
        }
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
    public static function setTranslatable($dbValue, $newValue, $lang = null) {
        if ($lang === false) {
            $dbValue = $newValue;
        } else {
            if ($lang === null) {
                if (defined('LANG')) $lang = LANG;
                else $lang = DEFAULT_LANG;
            }
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
        }
        return json_encode($dbValue, JSON_UNESCAPED_UNICODE);
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
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    // array
    public static function handler_get_array($value, Schema\Column $column) {
        if (!is_array($value) && !$value) return [];
        return json_decode($value, true);
    }
    public static function handler_set_array($value, Schema\Column $column) {
        if (!is_array($value) && !$value) $value = [];
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    // object
    public static function handler_get_object($value, Schema\Column $column) {
        if (!is_array($value) && !$value) return null;
        return json_decode($value, true);
    }
    public static function handler_set_object($value, Schema\Column $column) {
        if (!is_array($value) && !$value) return null;
        return json_encode($value, JSON_UNESCAPED_UNICODE);
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
        if (!in_array($value, $column->enum)) {
            trigger_error("Value not valid for ENUM field `$column->field` in model $column->model", E_USER_ERROR);
        }
        return $value;
    }

    // OneToOne (handle almost identical to ManyToOne...)
    public static function handler_get_onetoone($value, Schema\Column $column, &$modelRow) {
        if (is_object($value)) return $value;
        if ($column->onetoone) {
            $query = (new Query\Select($column->onetoone['model']))
                    ->where($column->onetoone['field'], $value);
            if ($column->filter) $query->where(new Expr($column->filter));
            if ($column->sort) $query->orderBy($column->sort);
            $row = $modelRow->{$column->field} = $query->fetchRow();
            $modelRow->_children = array_merge($modelRow->_children, [$row]);
            return $row;
        } else {
            trigger_error("OneToOne not configured properly on both sides", E_USER_ERROR);
        }
    }
    public static function handler_set_onetoone($value, Schema\Column $column, &$modelRow) {
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
    public static function handler_get_manytoone($value, Schema\Column $column, &$modelRow) {
        if (is_object($value)) return $value;
        if ($column->manytoone) {
            $query = (new Query\Select($column->manytoone['model']))
                    ->where($column->manytoone['field'], $value);
            if ($column->filter) $query->where(new Expr($column->filter));
            if ($column->sort) $query->orderBy($column->sort);
            $row = $modelRow->{$column->field} = $query->fetchRow();
            $modelRow->_children = array_merge($modelRow->_children, [$row]);
            return $row;
        } else {
            trigger_error("ManyToOne not configured properly on both sides for field `$column->field` in model $column->model", E_USER_ERROR);
        }
    }
    public static function handler_set_manytoone($value, Schema\Column $column, &$modelRow) {
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
    public static function handler_get_onetomany($value, Schema\Column $column, &$modelRow) {
        if (is_array($value)) return $value;
        if ($column->onetomany && ($foreignColumn = (new Schema\ModelData)->get($column->onetomany['model'])->getField($column->onetomany['field'])) && $foreignColumn->manytoone) {
            $query = (new Query\Select($column->onetomany['model']))
                    ->where($column->onetomany['field'], $modelRow->{$foreignColumn->manytoone['field']});
            if ($column->filter) $query->where(new Expr($column->filter));
            if ($column->sort) $query->orderBy($column->sort);
            $values = $modelRow->{$column->field} = $query->fetchAll($column->key, $column->value);
            $modelRow->_children = array_merge($modelRow->_children, $values);
            return $values;
        } else {
            trigger_error("OneToMany not configured properly on both sides for field `$column->field` in model $column->model", E_USER_ERROR);
        }
    }
    public static function handler_set_onetomany($values, Schema\Column $column, &$modelRow) {
        if ($values === null) return;
        if ($column->onetomany && ($foreignColumn = (new Schema\ModelData)->get($column->onetomany['model'])->getField($column->onetomany['field'])) && $foreignColumn->manytoone) {
            if (!is_array($values)) {
                trigger_error("Invalid data passed to OneToMany field `$column->field` in model $column->model", E_USER_ERROR);
            }
            if ($modelRow->_isnew) {
                $modelRow->save();
            }
            foreach ($values as $value) {
                if (is_array($value)) {
                    $className = $column->onetomany['model'];
                    $value = new $className($value);
                }
                if (is_object($value)) {
                    $value->{$column->onetomany['field']} = $modelRow->{$foreignColumn->manytoone['field']};
                    $value->save();
                } else {
                    trigger_error("Invalid data passed to OneToMany field `$column->field` in model $column->model", E_USER_ERROR);
                }
            }
        } else {
            trigger_error("OneToMany not configured properly on both sides for field `$column->field` in model $column->model", E_USER_ERROR);
        }
    }


    ///////////////////////////////////////////////////////////////////////////////
    // ArrayAccess stuff...

    public function offsetGet($offset) {
        return $this->get($offset);
    }

    public function offsetSet($offset, $value) {
        $this->set($offset, $value);
    }

    public function offsetExists($offset) {
        return !empty($this->_modelData->getFieldOrColumn($offset));
    }

    public function offsetUnset($offset) {
        $this->set($offset, null);
    }

}
