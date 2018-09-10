<?php

namespace Xenon\PostgreSQL;

class Query
{
    protected $db = null;
    protected $sql = null;
    protected $params = [];
    protected $query = "";
    protected $dirty = true;

    const TMP_REPLACEMENT_STR = '/%_VALUE_%/';

    /**
     * Creates a query object
     * @param resource $db Oracle connection resource
     * @param string $sql SQL Statement
     * @param array $params Escaped Parameters/Variables (optional)
     */
    public function __construct($db, $sql, array $params = []) {
        $this->db = $db;
        $this->sql = $sql;
        $this->params = $params;
    }

    public function setParams(array $params = []) {
        $this->params = $params;
        $this->dirty = true;
    }

    /**
     * Executes the query and returns the resultset
     * @return Resultset|bool Resultset on Success or FALSE on Failure
     */
    public function execute() {
        if ($this->dirty) {
            if(!$this->prepareQuery()) return false;
        }
        $result = pg_query($this->db, $this->query);
        return $result === false? false : new Resultset($result);
    }

    protected function prepareQuery() {
        $params = $this->params;
        $tmp_query = $this->sql;

        if (count($params) > 0) {
            // Replace all '?' with values
            if (substr_count($tmp_query, '?') <= count($params)) {
                $tmp_query = preg_replace("#\(\?\)|'\?'|\?#", Query::TMP_REPLACEMENT_STR, $tmp_query);
                while (strpos($tmp_query, Query::TMP_REPLACEMENT_STR) !== false && count($params) > 0) {
                    $value = array_shift($params);
                    if (is_array($value)) {
                        if (empty($value)) $value = [NULL];
                        $value = "(".implode(",",array_map([$this, 'escapeWithQuotes'], $value)).")";
                    } else {
                        $value = $this->escapeWithQuotes($value);
                    }
                    $tmp_query = preg_replace("#".preg_quote(Query::TMP_REPLACEMENT_STR)."#", str_replace('$','\$',$value), $tmp_query, 1);
                }
            } else {
                // Missing replacement values for '?'
                return false;
            }
        }

        $this->query = $tmp_query;
        $this->dirty = false;
        return true;
    }

    public function escapeWithQuotes($value) {
        if ($value === null) return "NULL";
        return "'".pg_escape_string($this->db, $value)."'";
    }

    public function escape($value) {
        return pg_escape_string($this->db, $value);
    }

    public function __toString() {
        if ($this->dirty) {
            if(!$this->prepareQuery()) return $this->sql;
        }
        return $this->query;
    }
}
