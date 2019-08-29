<?php
namespace Xenon\CMS;

class InlineTableEdit {
    public $source;
    public $data; // [ 'properties' => [...], 'rows' => [...] ]

    public $canCreate = true;
    public $canDelete = true;
    public $canEdit = [true]; // Associative Array of field=>bool that can be edited or not. First bool element is default when field not specified.
    public $canView = [true]; // Associative Array of field=>bool that can be viewed or not. First bool element is default when field not specified.

    public function __construct($source = null) {
        $this->source = $source;
        return $this;
    }

    public function ajaxAutoSave(array $customFunctions = []/* array of field => function(value, row, prop, &data) */, $validateSave = null /* Function(&$row, &$error, $values) that returns true to validate row before saving */) {
        if (!AJAX) return $this;
        if (($upload = X_upload())) die($upload);
        $this->saveData($_POST, $error, false, $customFunctions, $validateSave);
        die($error?$error:"OK");
    }

    public function loadProperties($source = false) {
        if ($source === false) $source = $this->source;
        if ($source === null) return $this;
        switch (gettype($source)) {
            case "string":
                if (class_exists($source)) {
                    switch ($source) {
                        case 'Xenon\Db\Model':
                            $source = $this->source;
                            $this->data = [
                                'query' => null,
                                'properties' => $source::getProperties(true),
                                'rows' => [],
                            ];
                            return $this;
                        //TODO handle more class types
                        default:
                            return $this->loadProperties(get_parent_class($source));
                    }
                } else {
                    //TODO handle more string source types
                }
            break;
            //TODO handle more source types
        }
    }

    public function containsData() {
        return !empty($this->data['rows']);
    }

    public function loadData($source = false, $orderBy = 'id ASC', $filters = null, $limit = 0, $offset = null) {
        if ($source === false) $source = $this->source;
        if ($source === null) return $this;
        switch (gettype($source)) {
            case "string":
                if (class_exists($source)) {
                    switch ($source) {
                        case 'Xenon\Db\Model':
                            $source = $this->source;
                            $query = $source::select();
                            if ($filters) {
                                if (!($filters instanceof \Xenon\Db\Query\Helper\Where)) {
                                    $filters = \Xenon\Db\Query\Helper\Where::fromArray($source, $filters);
                                }
                                $query->where($filters);
                            }
                            $query->orderBy($orderBy);
                            $this->data = [
                                'query' => "$query",
                                'properties' => $source::getProperties(true),
                                'rows' => $query->fetchAllTableArray(),
                            ];
                            if ($limit || $offset) {
                                $query->limit($limit, $offset);
                            }
                            return $this;
                        //TODO handle more class types
                        default:
                            return $this->loadData(get_parent_class($source), $orderBy, $filters, $limit, $offset);
                    }
                } else {
                    //TODO handle more string source types
                }
            break;
            //TODO handle more source types
        }
        return $this;
    }

    public function saveData($values, &$error = null, $source = false, array $customFunctions = []/* array of field => function(value, row, prop, &data) */, $validateSave = null /* Function(&$row, &$error, $values) that returns true to validate row before saving */) {
        if ($source === false) $source = $this->source;
        if ($source === null) {
            $error = "Source Error";
            return $this;
        }
        switch (gettype($source)) {
            case "string":
                if (class_exists($source)) {
                    switch ($source) {
                        case 'Xenon\Db\Model':
                            $source = $this->source;
                            $properties = $source::getProperties();
                            if (isset($values['id'])) {
                                if ($values['id'] === '_NEW_') {
                                    if ($this->canCreate) {
                                        // Create New Entry
                                        unset($values['id']);
                                        $data = [];
                                        $returnedData = [];
                                        foreach ($values as $key => $value) {
                                            $prop = $properties['fields'][$key];
                                            //TODO validate some things like attributes from $prop...
                                            if (isset($customFunctions[$key])) {
                                                if (is_callable($customFunctions[$key])) {
                                                    $returnedData[$key] = $customFunctions[$key]($value, $values, $prop, $data);
                                                } else {
                                                    $returnedData[$key] = $customFunctions[$key];
                                                }
                                            } else {
                                                if (isset($prop['attributes']['strip_tags'])) {
                                                    $value = strip_tags($value);
                                                }
                                                $returnedData[$key] = $value;
                                            }
                                        }
                                        $data += $returnedData;
                                        try {
                                            $row = new $source($data);
                                            //TODO other stuff ?
                                            if (!is_callable($validateSave) || $validateSave($row, $error, $values) === true) {
                                                $row->save();
                                            } else {
                                                $error = "Error saving data. $error";
                                            }
                                        } catch(Exception $e) {
                                            $error = "Error while saving new entry: ".$e->getMessage();
                                        }
                                    }
                                } else {
                                    $row = $source::fetchById($values['id']);
                                    if ($row) {
                                        unset($values['id']);
                                        if (count($values) == 1 && isset($values['_ACTION_'])) {
                                            switch ($values['_ACTION_']) {
                                                case 'DELETE': 
                                                    if ($this->canDelete) {
                                                        try {
                                                            $row->delete();
                                                        } catch (Exception $e) {
                                                            $error = "Error while trying to delete entry: ".$e->getMessage();
                                                        }
                                                    } else {
                                                        $error = "Permission denied to delete items";
                                                    }
                                                    break;
                                                default: 
                                                    $error = "Invalid Action";
                                                    break;
                                            }
                                        } else {
                                            // Edit Entry
                                            $canEdit = (array)$this->canEdit;
                                            if ($canEdit !== [false]) {
                                                $defaultCanEdit = (@$canEdit[0])? true:false;
                                                try {
                                                    $data = [];
                                                    foreach ($values as $key => $value) {
                                                        $prop = $properties['fields'][$key];
                                                        // Make sure we have the right to edit this field
                                                        if (((!isset($canEdit[$key]) && $defaultCanEdit) || !empty($canEdit[$key])) && empty($prop['attributes']['readonly']) && empty($prop['attributes']['createonly'])) {
                                                            //TODO validate some things like attributes from $prop...
                                                            if (isset($customFunctions[$key]) && is_callable($customFunctions[$key])) {
                                                                $row->set($key, $customFunctions[$key]($value, $row, $prop, $data), false);
                                                            } else {
                                                                $row->set($key, $value, false);
                                                            }
                                                        } else {
                                                            throw new \Exception("Permission denied to edit the field $key");
                                                        }
                                                    }
                                                    foreach ($data as $key => $val) {
                                                        $row->set($key, $val);
                                                    }
                                                    if (!is_callable($validateSave) || $validateSave($row, $error) === true) {
                                                        $row->save();
                                                    } else {
                                                        $error = "Error saving data. $error";
                                                    }
                                                } catch(\Exception $e) {
                                                    $error = "Error while saving data: ".$e->getMessage();
                                                }
                                            } else {
                                                $error = "Permission to edit is denied";
                                            }
                                        }
                                    } else {
                                        $error = "Entry Not Found";
                                    }
                                }
                            }
                            break;
                        //TODO handle more class types
                        default:
                            return $this->saveData($values, $error, get_parent_class($source), $customFunctions, $validateSave);
                    }
                } else {
                    //TODO handle more string source types
                }
            break;
            //TODO handle more source types
        }
        return $this;
    }

    public function setFieldSelectOptions($fieldName, $options = ["" => ""], $options_label = null) {
        $this->data['properties']['fields'][$fieldName]['attributes']['type'] = 'select';
        $this->data['properties']['fields'][$fieldName]['attributes']['options_label'] = $options_label;
        $this->data['properties']['fields'][$fieldName]['options'] = $options;
    }

    public static function outputObjectArrayField($name, $structure, $val, $nbFields = 1) {
        switch (gettype($structure)) {
            case 'string':
                switch ($structure) {
                    default:
                        ?>
                        <input type="<?=$structure!==''?$structure:'text'?>" name="<?=$name?>" value="<?=$val?>" autocomplete="false_<?=$name?>" data-nbfields="<?=$nbFields?>">
                        <?php
                    break;
                    case 'textarea':
                        ?>
                        <textarea name="<?=$name?>" autocomplete="false_<?=$name?>" data-nbfields="<?=$nbFields?>"><?=$val?></textarea>
                        <?php
                    break;
                }
            break;
            case 'NULL':
                self::outputObjectArrayField($name, '', $val);
            break;
            case 'array':
                foreach ($structure as $key=>$type) {
                    self::outputObjectArrayField($name."[$key]", $type, $val[$key], count($structure));
                }
            break;
        }
    }

    public function generateField($row, $fieldName, $prop = null, $isAddForm = false) {
        if ($prop === null) $prop = $this->data['properties']['fields'][$fieldName];
        $type = isset($prop['attributes']['type'])? $prop['attributes']['type'] : $prop['dataType'];
        $value = @$row[$fieldName];
        if (isset($prop['attributes']['strip_tags'])) {
            $value = strip_tags($value);
        }
        $canEdit = (array)$this->canEdit;
        $defaultCanEdit = (@$canEdit[0])? true:false;
        $canEdit = (!isset($canEdit[$fieldName]) && $defaultCanEdit) || !empty($canEdit[$fieldName]);
        $readonly = ((!$isAddForm && !$canEdit) || (isset($prop['attributes']['readonly']) || (!$isAddForm && isset($prop['attributes']['createonly']))))? ' readonly ':'';
        $required = ((isset($prop['attributes']['required']) || @$prop['null'] === false) && !$readonly)? ' required ':'';
        if ($type == 'enum') {
            $type = 'select';
            if (empty($prop['options'])) {
                $prop['options'] = [];
                foreach ($prop['enum'] as $v) {
                    $prop['options'][$v] = $v;
                }
            }
        } else if (@$prop['handler'] == 'manytoone' || @$prop['handler'] == 'onetoone') {
            $type = 'select';
        }
        if (isset($prop['attributes']['checkbox'])) {
            ?>
            <input type="checkbox" class="checkboxToActivateField" name="<?=$fieldName?>" value="" onchange="$(this).next().find('input,textarea').prop('disabled', !$(this).prop('checked'));" <?=$value?'checked':''?>>
            <div>
            <?php
        }
        if (!isset($prop['length'])) {
            $prop['length'] = 255;
        }
        $autocomplete_list = "";
        if (!empty($prop['attributes']['autocomplete'])) {
            $datalistID = "datalist_".$row['id']."_$fieldName";
            if ($type != "select") {
                echo '<datalist id="'.$datalistID.'">';
                if (@$prop['options']) foreach ($prop['options'] as $option_value => $option_row) {
                    $option_labelField = @$prop['attributes']['options_label'];
                    $option_label = $option_labelField? $option_row[$option_labelField] : $option_row;
                    echo '<option value="'.htmlspecialchars($option_value).'" label="'.htmlspecialchars($option_label).'" />';
                }
                echo '</datalist>';
                $autocomplete_list = " list=\"$datalistID\" ";
            } else {
                $autocomplete_list=" autocomplete_list ";
                include_once(XLIB_PATH.'helpers/select_autocomplete_list.phtml');
            }
        }
        switch ($type) {
            case 'varchar':
            case 'string':
                //TODO implement translatable
                echo '<input type="text" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="20" maxlength="'.$prop['length'].'" '.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'email':
                echo '<input type="email" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="30" maxlength="'.$prop['length'].'" '.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'phone':
                echo '<input type="phone" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="30" maxlength="'.$prop['length'].'" '.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'decimal':
                echo '<input type="number" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" step="'.(1.0/pow(10, (int)preg_replace("#\d+,\s*(\d+)#","$1",$prop['length']))).'" size="'.(ceil((int)$prop['length']/2)+1).'" '.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'int':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
            case 'number':
                echo '<input type="number" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="'.(ceil((int)$prop['length']/2)+1).'" '.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'password':
                echo '<input type="password" name="'.$fieldName.'" value="" placeholder="New Password" autocomplete="new-password" '.$readonly.$required.' />';
            break;
            case 'date':
                echo '<input type="date" name="'.$fieldName.'" value="'.htmlspecialchars($value?$value->format('Y-m-d'):'').'" '.$readonly.$required.$autocomplete_list.' />';
            break;
            // case 'create_timestamp':
            // case 'current_timestamp':
            case 'timestamp':
                echo '<input type="datetime-local" name="'.$fieldName.'" value="'.htmlspecialchars($value?$value->format('Y-m-d\TH:i:s'):'').'" '.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'bool':
                echo '<input type="checkbox" name="'.$fieldName.'" value="1" '.($value && $value !== '0' ? 'checked':'').' '.$readonly.$required.' />';
            break;
            case 'image_upload':
                if ($readonly) {
                    echo '<img src="'.($value?($value.'?size=50x50&margins'):'//placehold.it/50x50&text='.$fieldName).'" alt="'.$fieldName.'" />';
                } else {
                    X_simpleImageUpload($fieldName, $value, "//placehold.it/50x50&text=$fieldName", "?size=50x50&margins");
                }
            break;
            case 'select':
                if ($readonly) {
                    $readonly = "$readonly disabled ";
                    echo '<input type="hidden" name="'.$fieldName.'" value="'.htmlspecialchars($value).'">';
                }
                echo '<select name="'.$fieldName.'" '.$readonly.$required.$autocomplete_list.'>';
                if (($value == '' && ($row['id'] != '_NEW_' || $readonly)) || $prop['null']) echo '<option></option>';
                if (@$prop['options']) foreach ($prop['options'] as $option_value => $option_row) {
                    $option_labelField = @$prop['attributes']['options_label'];
                    $option_label = $option_labelField? $option_row[$option_labelField] : $option_row;
                    echo '<option '.($value == $option_value ? 'selected':'').' value="'.$option_value.'">'.$option_label.'</option>';
                }
                echo '</select>';
            break;
            case 'lang':
                echo '<select name="'.$fieldName.'" '.$readonly.$required.'>';
                if ($value == '' && $row['id'] != '_NEW_') echo '<option></option>';
                foreach (explode('|', LANGS) as $lang) {
                    echo '<option '.($value == $lang ? 'selected':'').' value="'.$lang.'">'.$lang.'</option>';
                }
                echo '</select>';
            break;
            case 'text':
                //TODO implement translatable
                echo '<textarea name="'.$fieldName.'" '.$readonly.$required.$autocomplete_list.'>'.$value.'</textarea>';
            break;
            case 'array':
                ?>
                <input type="hidden" name="<?=$fieldName?>" value="">
                <?php
                if ($value) foreach ((array)$value as $i=>$val) {
                    ?>
                    <div data-i="<?=$i?>">
                        <?php
                        self::outputObjectArrayField($fieldName."[$i]", $prop['structure'], $val);
                        ?>
                        <i onclick="X_inlineTableEdit_removeArrayElement('<?=$fieldName?>', $(this))" class="fas fa-times"></i>
                    </div>
                    <?php
                }
                ?>
                <a class="add" onclick="X_inlineTableEdit_addArrayElement('<?=$fieldName?>', <?=str_replace('"', "'", json_encode($prop['structure']))?>, $(this))"><i class="fas fa-plus"></i></a>
                <?php
            break;
            case 'object':
                self::outputObjectArrayField($fieldName, $prop['structure'], $value);
            break;
            case 'wysiwyg':
                static $ckeditor_preloaded = false;
                if (!$ckeditor_preloaded) {
                    ?>
                    <script src="//cdn.ckeditor.com/4.5.7/full/ckeditor.js"></script>
                    <script>
                        function wysiwyg_CKEditor_inline_edit(elem, event) {
                            if (event !== undefined) {
                                event.stopPropagation();
                                event.preventDefault();
                            }
                            if (!$(elem).hasClass('editing')) {
                                $(elem).addClass('editing');
                                CKEDITOR.inline(elem, {
                                    customConfig: false,
                                    width: 'auto',
                                    height: 'auto',
                                    language: '<?=(LANG=='fr'?'fr-ca':LANG)?>',
                                    toolbarGroups: [
                                        { name: 'basicstyles', groups: [ 'basicstyles', 'cleanup' ] },
                                        { name: 'links' },
                                        { name: 'styles' },
                                        { name: 'colors' },
                                    ]
                                });
                                elem.setAttribute('contenteditable', true);
                                elem.focus();
                            }
                        }
                    </script>
                    <?php
                    $ckeditor_preloaded = true;
                }
                //TODO implement translatable
                echo '<div class="wysiwyg"
                    name="'.$fieldName.'"
                    style="display: inline-block; outline: dotted 2px grey; min-width: 200px; min-height: 30px; margin: 2px;"
                    onclick="wysiwyg_CKEditor_inline_edit(this, event);"
                    onblur="$(this).trigger(\'change\');"
                    >'.$value.'</div>';
            break;
            default:
                if (!empty($prop['attributes']['readonly'])) echo $value;
            break;
        }
        if (isset($prop['attributes']['checkbox'])) {
            echo "</div>";
        }
    }

    public function generateAddForm(array $customFunctions = []/* array of field => function(value, row, prop, isAddForm)  OR  field => false */, array $row = []) {
        if (!$this->canCreate) return $this;
        $row['id'] = '_NEW_';
        echo '<form class="inlineEditTable_add" autocomplete="off">';
        echo '<input type="hidden" name="id" value="_NEW_" />';
        foreach ($this->data['properties']['fields'] as $fieldName => $prop) {
            if (empty($prop['column']) || empty($prop['attributes'])) {
                continue;
            }
            if (!empty($prop['attributes']['readonly']) && (!empty($prop['onetomany']) || (@$prop['dataType'] == 'create_timestamp'))) {
                continue;
            }
            if (isset($customFunctions[$fieldName]) && $customFunctions[$fieldName] === false) {
                continue;
            }
            $hint = (isset($prop['attributes']['hint']))? 'title="'.htmlentities($prop['attributes']['hint']).'"' : '';
            echo '<label data-fieldname="'.$fieldName.'" '.$hint.'>';
            echo '<strong>';
            echo isset($prop['attributes']['label'])? $prop['attributes']['label'] : ucfirst(str_replace('_', ' ', $fieldName));
            echo '</strong>';
            if (isset($customFunctions[$fieldName]) && is_callable($customFunctions[$fieldName])) {
                $customFunctions[$fieldName](@$row[$fieldName], $row, $prop, true);
            } else {
                $this->generateField($row, $fieldName, $prop, true);
            }
            echo '</label>';
        }
        echo '<br>';
        echo '<input type="submit" /><br>';
        echo '</form>';
        ?>
        <?php X_css(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/addform.css", true);?>
        <?php X_js(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/addform.js", true);?>
        <?php 
        return $this;
    }

    public function generateTable(array $customFunctions = []/* array of field => function(value, row, prop, isAddForm) */) {
        $canView = (array)$this->canView;
        $defaultCanView = (@$canView[0])? true:false;
        X_js(XLIB_PATH.'helpers/ajaxUpload.js');
        echo '<table class="inlineTableEdit">';
        echo '<thead>';
            if ((@$canView['id']) !== false) {
                echo '<tr>';
                echo '<th>';
                echo 'ID'; if ($this->canDelete) echo ' / Delete';
                echo '</th>';
            }
            foreach ($this->data['properties']['fields'] as $fieldName => $prop) if ($prop['attributes']) {
                $canViewThis = (!isset($canView[$fieldName]) && $defaultCanView) || !empty($canView[$fieldName]);
                if ((isset($customFunctions[$fieldName]) && $customFunctions[$fieldName] === false) || !$canViewThis) {
                    continue;
                }
                $hint = (isset($prop['attributes']['hint']))? 'title="'.htmlentities($prop['attributes']['hint']).'"' : '';
                echo '<th data-fieldname="'.$fieldName.'" '.$hint.'>';
                echo isset($prop['attributes']['label'])? $prop['attributes']['label'] : ucfirst(str_replace('_', ' ', $fieldName));
                echo '</th>';
            }
            echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($this->data['rows'] as $id => $row) {
            echo '<tr>';
            if ((@$canView['id']) !== false) {
                echo '<td data-fieldname="id">';
                echo $id;
                if ($this->canDelete) {
                    echo '<i class="fas fa-times" data-delete-id="'.$id.'"></i>';
                }
            }
            echo '</td>';
            foreach ($this->data['properties']['fields'] as $fieldName => $prop) if ($prop['attributes']) {
                $canViewThis = (!isset($canView[$fieldName]) && $defaultCanView) || !empty($canView[$fieldName]);
                if ((isset($customFunctions[$fieldName]) && $customFunctions[$fieldName] === false) || !$canViewThis) {
                    continue;
                }
                $hint = (isset($prop['attributes']['hint']))? 'title="'.htmlentities($prop['attributes']['hint']).'"' : '';
                echo '<td data-id="'.$id.'" data-fieldname="'.$fieldName.'" '.$hint.'>';
                if (isset($customFunctions[$fieldName]) && is_callable($customFunctions[$fieldName])) {
                    $customFunctions[$fieldName](@$row[$fieldName], $row, $prop, false);
                } else {
                    $this->generateField($row, $fieldName, $prop, false);
                }
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody>';
        // echo '<tfoot>';
        // echo '</tfoot>';
        echo '</table>';
        ?>
        <?php X_css(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/table.css", true);?>
        <?php X_js(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/table.js", true);?>
        <?php
        return $this;
    }

}
