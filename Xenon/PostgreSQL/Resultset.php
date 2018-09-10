<?php

namespace Xenon\PostgreSQL;

class Resultset
{
    protected $result = null;

    public function __construct($result) {
        $this->result = $result;
    }

    /**
     * Fetches all rows into a two dimentional Array
     * @return array All Rows with their respective columns
     */
    public function fetchAll() {
        return pg_fetch_all($this->result, PGSQL_ASSOC);
    }

    /**
     * Fetches all rows into a two dimentional Associative Array in which the key is a specific column
     * @return array All Rows with their respective columns
     */
    public function fetchRows($primaryKey = 0) {
        $rows = [];
        $key = is_int($primaryKey)? null : $primaryKey;
        while ($row = pg_fetch_array($this->result, null, PGSQL_ASSOC)) {
            if ($key == null) $key = array_keys($row)[$primaryKey];
            $rows[$row[$key]] = $row;
        }
        return $rows;
    }

    /**
     * Fetches all rows into a two dimentional Associative Array in which both the key and the value are specific columns
     * @return array All Rows with key => value
     */
    public function fetchRowsVal($primaryKey = 0, $valueField = 1) {
        $rows = [];
        $flag = PGSQL_BOTH;
        if (is_int($primaryKey) && is_int($valueField)) $flag = PGSQL_NUM;
        else if (is_string($primaryKey) && is_string($valueField)) $flag = PGSQL_ASSOC;
        while ($row = pg_fetch_array($this->result, null, $flag)) {
            $rows[$row[$primaryKey]] = $row[$valueField];
        }
        return $rows;
    }

    /**
     * Fetches all rows into a one dimentional indexed Array in which the value is a specific column
     * @return array
     */
    public function fetchArray($columnIndex = 0) {
        return pg_fetch_all_columns($this->result, $columnIndex);
    }

    /**
     * Fetches the next Row
     * @param bool $assoc
     * @return array
     */
    public function fetchRow($assoc = true) {
        return pg_fetch_array($this->result, null, $assoc? PGSQL_ASSOC : PGSQL_NUM);
    }

    /**
     * Fetches a value from the next row
     * @param int|string $field The column's index or key
     * @return mixed value
     */
    public function fetch($field = 0) {
        $row = $this->fetchRow(!is_int($field));
        if ($row && isset($row[$field])) {
            return $row[$field];
        }
        return null;
    }

    public function nbRows() {
        return pg_num_rows($this->result);
    }

    public function getResult() {
        return $this->result;
    }

}
