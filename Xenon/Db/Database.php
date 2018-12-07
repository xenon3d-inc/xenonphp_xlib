<?php

namespace Xenon\Db;

class Database
{
    public $db = null;

    protected static $instances = [];
    protected static $instancePerModel = []; // {model => db, ...}

    public static $queries = [];

    /**
     * @param hash array $options {host, user, pass, db}
     * @param array of string $models
     */
    public function __construct(array $options, array $models = []) {
        $this->db = mysqli_connect($options['host'], $options['user'], $options['pass'], $options['db']);
        if (!empty($options['charset'])) {
            mysqli_query($this->db, "SET NAMES ".$options['charset']);
        }
        foreach ($models as $model) {
            if (array_key_exists($model, self::$instancePerModel)) {
                trigger_error("Model '$model' already initialized for another database", E_USER_WARNING);
                return;
            }

            if (DB_AUTO_UPDATE_STRUCTURE) {
                $updateTime = date('YmdHMs');
                $query = (new \Xenon\Db\Schema\ModelData())->fromModel($model)->getCreateOrAlterQuery($this->db);
                if ($query) {
                    $modelname = str_replace('\\', '.', strtolower($model));
                    if (mysqli_query($this->db, $query) === false) {
                        trigger_error("Error while trying to update database structure for model '$modelname'\n" . mysqli_error($this->db) . "\nQuery: $query", E_USER_ERROR);
                    } else {
                        if (!is_dir(DB_UPDATES_CACHE_DIRECTORY."/$updateTime")) {
                            mkdir(DB_UPDATES_CACHE_DIRECTORY."/$updateTime", 0770, true);
                        }
                        if (is_dir(DB_UPDATES_CACHE_DIRECTORY."/$updateTime")) {
                            file_put_contents(DB_UPDATES_CACHE_DIRECTORY."/$updateTime/$modelname.sql", $query);
                        }
                    }
                }
            }

            self::$instancePerModel[$model] = $this;
        }
        self::$instances[] = $this;
    }

    public static function getInstanceForModel($model) {
        if (!array_key_exists($model, self::$instancePerModel)) {
            trigger_error("Model '$model' not initialized", E_USER_ERROR);
            return;
        }
        return self::$instancePerModel[$model];
    }

    public static function debugQueries(){
        die("\n<br>\n".implode("\n<br>\n", Database::$queries)."\n<br>\n");
    }

    public static function debugLastQuery(){
        die(count(Database::$queries) ? Database::$queries[count(Database::$queries) - 1] : "[NO QUERIES]");
    }

}
