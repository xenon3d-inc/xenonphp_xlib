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
        foreach ($models as $model) {
            if (array_key_exists($model, self::$instancePerModel)) {
                //TODO Throw error "model already initialized for another database"
                return;
            }
            (new \Xenon\Db\Schema\ModelData())->fromModel($model)->toDb($this->db);
            self::$instancePerModel[$model] = $this;
        }
        self::$instances[] = $this;
    }
    
    public static function getInstanceForModel($model) {
        if (!array_key_exists($model, self::$instancePerModel)) {
            //TODO Throw error "model not initialized"
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
