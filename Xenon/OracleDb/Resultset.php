<?php

namespace Xenon\OracleDb;

class Resultset
{
    protected $statementID = null;

    public function __construct($statementID) {
        $this->statementID = $statementID;
    }

    /**
     * Fetches all rows into a two dimentional Array
     * @return array All Rows with their respective columns
     */
    public function fetchAll() {
        oci_fetch_all($this->statementID, $output, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + OCI_ASSOC);
        return $output;
    }

    /**
     * Fetches all rows into a two dimentional Associative Array in which the key is a specific column
     * @return array All Rows with their respective columns
     */
    public function fetchRows($primaryKey = 0) {
        oci_fetch_all($this->statementID, $output, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + (is_int($primaryKey)? OCI_NUM:OCI_ASSOC));
        $rows = [];
        foreach ($output as $row) {
            $rows[$row[$primaryKey]] = $row;
        }
        return $rows;
    }

    /**
     * Fetches all rows into a two dimentional Associative Array in which both the key and the value are specific columns
     * @return array All Rows with key => value
     */
    public function fetchRowsVal($primaryKey = 0, $valueField = 1) {
        oci_fetch_all($this->statementID, $output, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + (is_int($primaryKey) && is_int($valueField)? OCI_NUM:OCI_ASSOC));
        $rows = [];
        foreach ($output as $row) {
            $rows[$row[$primaryKey]] = (isset($row[$valueField]))? $row[$valueField] : $row[$primaryKey];
        }
        return $rows;
    }

    /**
     * Fetches all rows into a one dimentional indexed Array in which the value is a specific column
     * @return array
     */
    public function fetchArray($valueField = 0) {
        oci_fetch_all($this->statementID, $output, 0, -1, OCI_FETCHSTATEMENT_BY_ROW + (is_int($valueField)? OCI_NUM:OCI_ASSOC));
        $rows = [];
        foreach ($output as $row) {
            $rows[] = $row[$valueField];
        }
        return $rows;
    }

    /**
     * Fetches the next Row
     * @param bool $assoc
     * @return array
     */
    public function fetchRow($assoc = true) {
        if ($assoc) {
            return oci_fetch_assoc($this->statementID);
        } else {
            return oci_fetch_row($this->statementID);
        }
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
        return oci_num_rows($this->statementID);
    }

    public function getStatement() {
        return $this->statementID;
    }

}
