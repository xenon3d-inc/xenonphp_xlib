<?php

namespace Xenon\OracleDb;

class SimpleDatabaseConnection
{
    public $db = null;

    protected static $instances = [];

    public static $queries = [];

    public function __construct() {
        self::$instances[] = $this;
    }

    /**
     * Connects to the database
     * @param string $connectionstring
     * @param string $username
     * @param string $password
     * @return bool TRUE on success, FALSE on failure
     */
    public function connect($connectionstring, $username, $password, $charset = 'utf8') {
        if (empty($connectionstring) || empty($username) || empty($password)) {
            return false;
        }
        $this->db = @\oci_connect($username, $password, $connectionstring, $charset);
        return !!$this->db;
    }

    /**
     * Creates a query object
     * @param string $sql SQL Statement
     * @param array $params Escaped Parameters/Variables (optional)
     * @return Query
     */
    public function query($sql, array $params = []){
        self::$queries[] = $sql;
        return new Query($this->db, $sql, $params);
    }

    public static function getFirstInstance() {
        return count(self::$instances) > 0 ? self::$instances[0] : null;
    }

    public static function debugQueries(){
        die("\n<br>\n".implode("\n<br>\n", self::$queries)."\n<br>\n");
    }

    public static function debugLastQuery(){
        die(count(self::$queries) ? self::$queries[count(self::$queries) - 1] : "[NO QUERIES]");
    }

}
