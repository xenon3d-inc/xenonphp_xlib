<?php

namespace Xenon\Db\Schema;

class ModelParser
{
    protected static $cached_classes = array();

    public static function getCache()
    {
        return self::$cached_classes;
    }

    public static function setCache(array $cache)
    {
        self::$cached_classes = $cache;
    }

    protected static function metaCommentToArray($meta)
    {
        $meta = preg_replace("#^/\*\*(.*)\*/$#ismx", "$1", $meta);
        $meta = explode('@', $meta);
        $result = array();
        foreach ($meta as $meta) {
            $meta = trim(strstr($meta . "\n", "\n", true));
            if (!empty($meta)) {
                $meta = preg_split('#[=: >-]+#', $meta, 2, PREG_SPLIT_NO_EMPTY);
                $key = strtolower(strstr(trim($meta[0]) . ' ', ' ', true));
                $result[$key] = (isset($meta[1]) ? trim($meta[1]) : null);
            }
        }
        return $result;
    }

    public static function getModelMeta($className)
    {
        if (!isset(self::$cached_classes[$className])) {
            $obj = new \stdClass();
            $rc = new \ReflectionClass($className);

            $obj->table = self::metaCommentToArray($rc->getDocComment());
            $obj->fields = array();
            foreach ($rc->getProperties() as $column) {
                $columnMetaData = self::metaCommentToArray($column->getDocComment());
                if (count($columnMetaData)) {
                    $obj->fields[$column->getName()] = $columnMetaData;
                }
            }

            if ($rc->getParentClass()) {
                $parent = self::getModelMeta($rc->getParentClass()->getName());
                $obj->table += $parent->table;
                $obj->fields += $parent->fields;
            }

            self::$cached_classes[$className] = $obj;
        }

        return self::$cached_classes[$className];
    }

    public static function getTable($className)
    {
        $modelMeta = self::getModelMeta($className);
        if (empty($modelMeta->modelTableData)) {
            $modelMeta->modelTableData = new Table($className, $modelMeta->table);
        }
        return $modelMeta->modelTableData;
    }

    public static function getColumns($className)
    {
        return self::getFields($className);
    }

    public static function getFields($className)
    {
        $modelMeta = self::getModelMeta($className);
        if (empty($modelMeta->modelColumnsData)) {
            $modelMeta->modelColumnsData = array();
            foreach ($modelMeta->fields as $fieldName => $data) {
                $modelMeta->modelColumnsData[$fieldName] = new Column($className, $fieldName, $data);
            }
        }
        return (array) $modelMeta->modelColumnsData;
    }

}
