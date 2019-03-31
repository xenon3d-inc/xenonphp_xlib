<?php

namespace Xenon\Db\Query\Helper;

class DateTime {
    public $isnull = false;
    public $timestamp = 0.0;

    public function __construct($datetime = 'current_timestamp') {
        if ($datetime === 'current_timestamp') {
            $datetime = time();
        } else if (preg_match("/^current_timestamp\(\d\)$/", $datetime)) {
            $datetime = microtime(true);
        }
        if (!$datetime || $datetime == '0000-00-00 00:00:00' || $datetime == '0000-00-00 00:00:00.0000') {
            $this->isnull = true;
            $this->timestamp = 0;
        } else {
            if (is_numeric($datetime)) {
                $this->timestamp = $datetime;
            } else {
                if (preg_match("/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(\.\d+)$/", $datetime, $matches)) {
                    $this->timestamp = (double)(strtotime($matches[1]).$matches[2]);
                } else {
                    $this->timestamp = strtotime($datetime);
                }
            }
        }
    }

    public function format($format = "Y-m-d H:i:s") {
        return $this->isnull ? '0000-00-00 00:00:00' : date($format, $this->timestamp);
    }

    public function format_u($format = "Y-m-d H:i:s.u") {
        return $this->isnull ? '0000-00-00 00:00:00' : \DateTime::createFromFormat('U.u', $this->timestamp)->format($format);
    }

    public function __toString() {
        return $this->format();
    }
}
