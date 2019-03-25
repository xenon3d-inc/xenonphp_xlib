<?php 

namespace Xenon\Db\Query;

class Select extends \Xenon\Db\Query
{
    protected $fields = [];
    
    /**
     * @param string $model
     * @param mixed $fields
     */
    public function __construct($model, $fields = "*") {
        parent::__construct("SELECT", $model);
        $this->addFields($fields);
    }
    
    public function addFields($fields) {
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        foreach ($fields as $as => $field) {
            if (!($field instanceof Helper\Field)) {
                $field = new Helper\Field($this->model, $field, $as);
            }
            array_push($this->fields, $field);
        }
    }
    
    public function __toString() {
        return $this->query." ".implode(', ', $this->fields)
                ." FROM `".$this->table."`"
                .($this->where ? " WHERE ".$this->where : '')
                .($this->orderby ? " ORDER BY ".$this->orderby : '')
                .($this->limit || $this->offset ? " LIMIT ".((int)$this->limit).($this->offset? " OFFSET ".$this->offset:'') : '')
                ;
    }
    
}
