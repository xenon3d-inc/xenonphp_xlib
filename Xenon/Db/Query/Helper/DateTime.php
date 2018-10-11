<?php

namespace Xenon\Db\Query\Helper;

class DateTime {
    public $isnull = false;
    public $timestamp = 0;

    public function __construct($datetime = 'current_timestamp') {
        if ($datetime === 'current_timestamp') {
            $datetime = time();
        }
        if (!$datetime || $datetime == '0000-00-00 00:00:00') {
            $this->isnull = true;
            $this->timestamp = 0;
        } else {
            if (is_numeric($datetime)) {
                $this->timestamp = $datetime;
            } else {
                $this->timestamp = strtotime($datetime);
            }
        }
    }

    public function format($format = "Y-m-d H:i:s") {
        return date($format, $this->timestamp);
    }

    public function __toString() {
        return $this->format();
    }
}
