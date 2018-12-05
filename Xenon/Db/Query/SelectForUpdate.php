<?php

namespace Xenon\Db\Query;

class SelectForUpdate extends Select
{
    protected $fields = [];

    /**
     * @param string $model
     * @param mixed $fields
     */
    public function __construct($model, $fields = "*") {
        parent::__construct($model, $fields);
    }

    public function __toString() {
        return parent::__toString()." FOR UPDATE";
    }

}
