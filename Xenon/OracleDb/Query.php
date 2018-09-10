<?php

namespace Xenon\OracleDb;

class Query
{
    protected $db = null;
    protected $sql = null;
    protected $params = [];
    protected $statementID = null;

    protected $error;
    protected $errorCode;
    protected $errorMessage;
    protected $errorOffset;
    protected $errorSqlText;

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
        $this->parse();
    }

    public function bind($key, $value, $maxLength = -1, $dataType = SQLT_CHR) {
        if (is_array($value)) {
            return $this->bindArray($key, $value, count($value), $maxLength, $dataType);
        }
        oci_bind_by_name($this->statementID, $key, $value, $maxLength, $dataType) || $this->error();
        return $this;
    }

    public function bindArray($key, array $value, $maxTableLenfth, $maxItemLength = -1, $dataType = SQLT_CHR) {
        oci_bind_array_by_name($this->statementID, $key, $value, $maxTableLenfth, $maxItemLength, $dataType) || $this->error();
        return $this;
    }

    /**
     * Executes the query and returns the resultset
     * @param $mode flag (Default: OCI_COMMIT_ON_SUCCESS)
     * @return Resultset|bool Resultset on Success or FALSE on Failure
     */
    public function execute($mode = OCI_COMMIT_ON_SUCCESS) {
        if ($this->error) return false;
        oci_execute($this->statementID, $mode) || $this->error();
        return $this->error ? false : new Resultset($this->statementID);
    }

    public function getErrorCode() {
        return $this->errorCode || false;
    }

    public function getErrorMessage() {
        return $this->errorMessage || false;
    }

    public function getStatement() {
        return $this->statementID;
    }

    protected function parse() {
        $this->error = false;
        $this->errorCode = null;
        $this->errorMessage = null;
        $this->errorOffset = null;
        $this->errorSqlText = null;
        $this->statementID = oci_parse($this->db, $this->sql);
        if (count($this->params) > 0) {
            preg_match_all("#(:[a-z]+)#i", $this->sql, $matches);
            foreach ($this->params as $key => $value) {
                if (is_int($key)) {
                    $this->bind($matches[1][$key], $value);
                } else {
                    $this->bind($key, $value);
                }
            }
        }
        return !$this->error;
    }

    protected function error() {
        $error = oci_error($this->db);
        if ($error === false) return false;
        $this->error = true;
        $this->errorCode = $error['code'];
        $this->errorMessage = $error['message'];
        $this->errorOffset = $error['offset'];
        $this->errorSqlText = $error['sqltext'];
        return true;
    }

}
