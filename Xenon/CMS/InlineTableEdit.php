<?php
namespace Xenon\CMS;

class InlineTableEdit {
    public $source;
    public $data; // [ 'properties' => [...], 'rows' => [...] ]

    public $canCreate = true;
    public $canDelete = true;
    public $canEdit = [true]; // Associative Array of field=>bool that can be edited or not. First bool element is default when field not specified.
    public $canView = [true]; // Associative Array of field=>bool that can be viewed or not. First bool element is default when field not specified.

    protected static $uniqueDomIDs = [];

    public function __construct($source = null) {
        $this->source = $source;
        return $this;
    }

    public function ajaxAutoSave(array $customFunctions = []/* array of field => function($value, $row, $prop, &$data, $isCreate) */, $validateSave = null /* Function(&$row, &$error, $values, $isCreate) that returns true to validate row before saving */) {
        if (!AJAX) return $this;
        if (($upload = X_upload())) die($upload);
        $this->saveData($_POST, $error, false, $customFunctions, $validateSave);
        die($error?$error:"OK");
    }

    public function loadProperties($source = false, $fetchXToOneOptions = true) {
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
                                'properties' => $source::getProperties($fetchXToOneOptions),
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

    public function ajaxAutoCompleteSearch($searchFunction = null/* function($fieldName, $search) */) {
        if (!empty($_GET['X_GET_INLINE_EDIT_AUTOCOMPLETE_AJAX_FIELD'])) {
            $field = $_GET['X_GET_INLINE_EDIT_AUTOCOMPLETE_AJAX_FIELD'];
            $search = trim(@$_GET['search']);

            if (is_callable($searchFunction)) {
                $results = $searchFunction($field, $search);
            } else {
                $this->loadProperties(false, function($fieldName, $columnData, &$query) use($field) {
                    return $field == $fieldName;
                });
                $results = !empty($this->data['properties']['fields'][$field]['null'])? ['' => ''] : [];
                $options = $this->data['properties']['fields'][$field]['options'];
                foreach ($options as $option_value => $option_row) {
                    $text = "$option_row";
                    $haystack = explode(' ', strtolower(preg_replace("/\W+/", ' ', $text)));
                    $needles = explode(' ', strtolower(preg_replace("/\W+/", ' ', $search)));
                    if (!count($needles)) continue;
                    // All needles must be present (starts with) in haystack
                    $notFound = false;
                    foreach ($needles as $needle) {
                        if ($needle == "") continue;
                        foreach ($haystack as $word) {
                            if (strpos($word, $needle) === 0) {
                                continue 2;
                            }
                        }
                        $notFound = true;
                    }
                    if (!$notFound) {
                        $results[$option_value] = $text;
                    }
                }
            }

            echo json_encode($results);
            
            exit;
        }
        return $this;
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

    public function saveData($values, &$error = null, $source = false, array $customFunctions = []/* array of field => function($value, $row, $prop, &$data, $isCreate) */, $validateSave = null /* Function(&$row, &$error, $values, $isCreate) that returns true to validate row before saving */) {
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
                                                    $returnedData[$key] = $customFunctions[$key]($value, $values, $prop, $data, true);
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
                                            if (!is_callable($validateSave) || $validateSave($row, $error, $values, true) === true) {
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
                                                            if (isset($customFunctions[$key])) {
                                                                if (is_callable($customFunctions[$key])) {
                                                                    $row->set($key, $customFunctions[$key]($value, $row, $prop, $data, false), false);
                                                                } else {
                                                                    $row->set($key, $customFunctions[$key], false);
                                                                }
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
                                                    if (!is_callable($validateSave) || $validateSave($row, $error, $values, false) === true) {
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

    // MUST ALSO EDIT arrayElements.js::X_inlineTableEdit_addArrayElement()
    public static function outputObjectArrayField($inputName, $structure, $val, $nbFields = 1, $options = []) {
        X_js(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/arrayElements.js");
        switch (gettype($structure)) {
            case 'string':
                $fieldName = preg_replace("/^(.*\[)?(\w+)\]?$/", '$2', $inputName);
                $attributes = [
                    'label' => ucfirst(trim(str_replace('_', ' ', $fieldName))),
                ];
                if (preg_match("/^(\w+)\?(.*)$/", $structure, $matches)) {
                    parse_str($matches[2], $attrs);
                    $attributes = $attrs + $attributes;
                    $structure = $matches[1];
                }
                if (!isset($attributes['placeholder']) && $structure === 'timer') $attributes['placeholder'] = '00:00';
                if (!isset($attributes['placeholder'])) $attributes['placeholder'] = $attributes['label'];
                $autocompleteValue = "false_".preg_replace("/[\]\[]+/", '_', $inputName);
                if ($structure == 'checkbox') {?>
                <input type="hidden" name="<?=$inputName?>" value="0">
                <?php }
                switch ($structure) {
                    default:
                        ?>
                        <input 
                            type="<?=$structure!==''?$structure:'text'?>" 
                            name="<?=$inputName?>"
                            data-field="<?=$fieldName?>"
                            placeholder="<?=$attributes['placeholder']?>" 
                            title="<?=$attributes['label']?>" 
                            autocomplete="<?=$autocompleteValue?>" 
                            data-nbfields="<?=$nbFields?>"
                            <?php if ($structure == 'checkbox') {?>
                                value="1"
                                <?php if ($val) echo 'checked';?>
                            <?php } else {?>
                                value="<?=$val?>"
                            <?php }?>
                            />
                        <?php
                    break;
                    case 'decimal':
                        ?>
                        <input 
                            type="number" 
                            step="0.01"
                            min="0.0"
                            name="<?=$inputName?>"
                            data-field="<?=$fieldName?>"
                            placeholder="<?=$attributes['placeholder']?>" 
                            title="<?=$attributes['label']?>" 
                            autocomplete="<?=$autocompleteValue?>" 
                            data-nbfields="<?=$nbFields?>"
                            value="<?=$val?>" 
                            />
                        <?php
                    break;
                    case 'textarea':
                        ?>
                        <textarea 
                            name="<?=$inputName?>"
                            data-field="<?=$fieldName?>"
                            placeholder="<?=$attributes['placeholder']?>" 
                            title="<?=$attributes['label']?>" 
                            autocomplete="<?=$autocompleteValue?>" 
                            data-nbfields="<?=$nbFields?>"
                            ><?=$val?></textarea>
                        <?php
                    break;
                    case 'select':
                        $attributes['options'] = isset($attributes['options'])? explode(',', $attributes['options']) : [];
                        if (!empty($attributes['autocomplete_ajax'])) {
                            $options = [
                                "$val" => isset($options[$val])? $options[$val] : $val,
                            ];
                        }
                        ?>
                        <select 
                            name="<?=$inputName?>"
                            data-field="<?=$fieldName?>"
                            title="<?=$attributes['label']?>" 
                            autocomplete="<?=$autocompleteValue?>" 
                            <?php if (!empty($attributes['autocomplete_ajax'])) {?>
                            autocomplete_ajax="<?=$attributes['autocomplete_ajax']?>" 
                            <?php }?>
                            data-nbfields="<?=$nbFields?>"
                            >
                            <?php foreach ($attributes['options'] as $option) {?>
                                <option <?php if ($val == $option) echo 'selected'; ?> value="<?=$option?>"><?=$option?></option>
                            <?php }?>
                            <?php foreach ((array)$options as $v=>$option) {?>
                                <option <?php if ($val == $v) echo 'selected'; ?> value="<?=$v?>"><?=$option?></option>
                            <?php }?>
                        </select>
                        <?php
                    break;
                }
            break;
            case 'NULL':
                self::outputObjectArrayField($inputName, '', $val, 1, $options);
            break;
            case 'array':
                if ($nbFields == 1) foreach ($structure as $key=>$type) {
                    self::outputObjectArrayField($inputName."[$key]", $type, @$val[$key], count($structure), @$options[$key]);
                }
            break;
        }
    }

    public static function generateInputField($fieldName, $prop = 'text', $value = null) {
        if (!is_array($prop)) {
            $prop_str = $prop;
            $prop = [
                'dataType' => preg_replace("/^(\w+)(\W.*)$/", "$1", strtolower(trim($prop_str))),
            ];
            if (preg_match("/^(\w+)\?(.*)$/", $prop_str, $matches)) {
                parse_str($matches[2], $attrs);
                $attributes = $attrs + $attributes;
                $structure = $matches[1];
            }
        }
        $readonly = !empty($prop['attributes']['readonly'])? ' readonly ':'';
        $required = !empty($prop['attributes']['required'])? ' required ':'';
        if (!empty($prop['attributes']['strip_tags'])) {
            $value = strip_tags($value);
        }
        $type = isset($prop['attributes']['type'])? $prop['attributes']['type'] : (isset($prop['dataType'])?$prop['dataType']:$prop['type']);
        $placeholder = (isset($prop['attributes']['placeholder']))? ' placeholder="'.htmlentities($prop['attributes']['placeholder']).'" ' : '';
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

        if (!isset($prop['length'])) {
            $prop['length'] = 255;
        }
        $autocomplete_list = "";
        if (!empty($prop['attributes']['autocomplete'])) {
            if ($type == "select") {
                $autocomplete_list=" autocomplete_list ";
                include_once(XLIB_PATH.'helpers/select_autocomplete.phtml');
            } else {
                $datalistID = "datalist_$fieldName";
                if (!isset(self::$uniqueDomIDs[$datalistID])) {
                    self::$uniqueDomIDs[$datalistID] = true;
                    echo '<datalist id="'.$datalistID.'">';
                    if (@$prop['options']) foreach ($prop['options'] as $option_value => $option_row) {
                        $option_labelField = @$prop['attributes']['options_label'];
                        $option_label = $option_labelField? $option_row[$option_labelField] : $option_row;
                        echo '<option value="'.htmlspecialchars($option_value).'" label="'.htmlspecialchars($option_label).'" />';
                    }
                    echo '</datalist>';
                }
                $autocomplete_list = " list=\"$datalistID\" ";
            }
        }
        if (!empty($prop['attributes']['autocomplete_ajax']) && $type == "select") {
            $autocomplete_list=' autocomplete_ajax="?X_GET_INLINE_EDIT_AUTOCOMPLETE_AJAX_FIELD='.$fieldName.'" ';
            include_once(XLIB_PATH.'helpers/select_autocomplete.phtml');
        }

        if (!empty($prop['attributes']['checkbox'])) {
            ?>
            <input type="checkbox" class="checkboxToActivateField" name="<?=$fieldName?>" value="" onchange="$(this).next().find('input,textarea').prop('disabled', !$(this).prop('checked'));" <?=$value?'checked':''?>>
            <div>
            <?php
        }

        switch ($type) {
            case 'varchar':
            case 'string':
                //TODO implement translatable
                echo '<input type="text" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="20" maxlength="'.$prop['length'].'" '.$placeholder.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'email':
                echo '<input type="email" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="30" maxlength="'.$prop['length'].'" '.$placeholder.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'phone':
                echo '<input type="phone" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="30" maxlength="'.$prop['length'].'" '.$placeholder.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'decimal':
                echo '<input type="number" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" step="'.(1.0/pow(10, (int)preg_replace("#\d+,\s*(\d+)#","$1",$prop['length']))).'" size="'.(ceil((int)$prop['length']/2)+1).'" '.$placeholder.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'int':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
            case 'number':
                echo '<input type="number" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="'.(ceil((int)$prop['length']/2)+1).'" '.$placeholder.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'timer':
                X_js(XLIB_PATH."helpers/inputTimer.js");
                if (empty($placeholder)) $placeholder=' placeholder="00:00" ';
                echo '<input type="timer" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="5" '.$placeholder.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'password':
                if (empty($placeholder)) $placeholder=' placeholder="New Password" ';
                echo '<input type="password" name="'.$fieldName.'" value="" autocomplete="new-password" '.$placeholder.$readonly.$required.' />';
            break;
            case 'date':
                echo '<input type="date" name="'.$fieldName.'" value="'.htmlspecialchars($value?$value->format('Y-m-d'):'').'" '.$readonly.$required.$autocomplete_list.' />';
            break;
            // case 'create_timestamp':
            // case 'current_timestamp':
            case 'timestamp':
            case 'datetime':
                echo '<input type="datetime-local" name="'.$fieldName.'" value="'.htmlspecialchars($value?$value->format('Y-m-d\TH:i:s'):'').'" '.$readonly.$required.$autocomplete_list.' />';
            break;
            case 'bool':
            case 'checkbox':
                if ($readonly) {
                    $readonly = "$readonly disabled ";
                }
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
                if ($value == '' || !empty($prop['null'])) echo '<option></option>';
                if (@$prop['options']) foreach ($prop['options'] as $option_value => $option_row) {
                    if (empty($prop['attributes']['autocomplete_ajax']) || $value == $option_value) {
                        $option_labelField = @$prop['attributes']['options_label'];
                        $option_label = $option_labelField? $option_row[$option_labelField] : $option_row;
                        echo '<option '.($value == $option_value ? 'selected':'').' value="'.$option_value.'">'.$option_label.'</option>';
                    }
            }
                echo '</select>';
            break;
            case 'lang':
                echo '<select name="'.$fieldName.'" '.$readonly.$required.'>';
                if ($value == '' || !empty($prop['null'])) echo '<option></option>';
                foreach (explode('|', LANGS) as $lang) {
                    echo '<option '.($value == $lang ? 'selected':'').' value="'.$lang.'">'.$lang.'</option>';
                }
                echo '</select>';
            break;
            case 'text':
            case 'textarea':
                //TODO implement translatable
                echo '<textarea name="'.$fieldName.'" '.$placeholder.$readonly.$required.$autocomplete_list.'>'.$value.'</textarea>';
            break;
            case 'array':
                X_js(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/arrayElements.js");
                ?>
                <input type="hidden" name="<?=$fieldName?>" value="">
                <?php
                $options = !empty($prop['options'])? $prop['options'] : [];
                foreach ($options as $f => &$opts) {
                    if (preg_match("/autocomplete_ajax/", @$prop['structure'][$f])) {
                        $opts = [];
                    }
                }
                if (is_array(@$prop['structure']) && count($prop['structure']) > 2) {
                    echo '<table class="arrayfield"><thead><tr>';
                    foreach ($prop['structure'] as $key=>$structure) {
                        $attributes = [
                            'label' => ucfirst(trim(str_replace('_', ' ', $key))),
                        ];
                        if (preg_match("/^(\w+)\?(.*)$/", $structure, $matches)) {
                            parse_str($matches[2], $attrs);
                            $attributes = $attrs + $attributes;
                        }
                        echo '<th>'.$attributes['label'].'</th>';
                    }
                    echo '<th></th></tr></thead><tbody>';
                    if ($value) {
                        foreach ((array)$value as $i=>$val) {
                            ?>
                            <tr data-i="<?=$i?>">
                                <?php foreach ($prop['structure'] as $key=>$structure) {?>
                                    <td>
                                        <?php self::outputObjectArrayField($fieldName."[$i][$key]", $structure, @$val[$key], count($prop['structure']), @$prop['options'][$key]);?>
                                    </td>
                                <?php } ?>
                                <td>
                                    <i onclick="X_inlineTableEdit_removeArrayElement('<?=$fieldName?>', $(this))" class="fas fa-times"></i>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>
                    <tr>
                        <td colspan="<?=count($prop['structure'])+1?>">
                            <a class="add" onclick="X_inlineTableEdit_addArrayElement('<?=$fieldName?>', <?=str_replace('"', "'", json_encode(@$prop['structure']))?>, $(this), <?=str_replace('"', "'", json_encode($options))?>)"><i class="fas fa-plus"></i></a>
                        </td>
                    </tr>
                    <?php 
                    echo '</tbody></table>';
                } else {
                    ?>
                    <div class="arrayfield">
                        <?php if ($value) foreach ((array)$value as $i=>$val) {?>
                            <div data-i="<?=$i?>">
                                <?php
                                self::outputObjectArrayField($fieldName."[$i]", @$prop['structure'], $val, 1, @$prop['options']);
                                ?>
                                <i onclick="X_inlineTableEdit_removeArrayElement('<?=$fieldName?>', $(this))" class="fas fa-times"></i>
                            </div>
                            <?php
                        }
                        ?>
                        <a class="add" onclick="X_inlineTableEdit_addArrayElement('<?=$fieldName?>', <?=str_replace('"', "'", json_encode(@$prop['structure']))?>, $(this), <?=str_replace('"', "'", json_encode($options))?>)"><i class="fas fa-plus"></i></a>
                    </div>
                    <?php 
                }
            break;
            case 'object':
                self::outputObjectArrayField($fieldName, @$prop['structure'], $value, 1, @$prop['options']);
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
                    >'.$value.'</tr>';
            break;
            default:
                if (!empty($prop['attributes']['readonly'])) echo $value;
            break;
        }
        if (!empty($prop['attributes']['checkbox'])) {
            echo "</div>";
        }
    }

    public function generateField($row, $fieldName, $prop = null, $isAddForm = false) {
        if ($prop === null) $prop = $this->data['properties']['fields'][$fieldName];
        $canEdit = (array)$this->canEdit;
        $defaultCanEdit = (@$canEdit[0])? true:false;
        $canEdit = (!isset($canEdit[$fieldName]) && $defaultCanEdit) || !empty($canEdit[$fieldName]);
        $readonly = (!$isAddForm && !$canEdit) || (!empty($prop['attributes']['readonly']) || (!$isAddForm && !empty($prop['attributes']['createonly'])));
        $required = (!empty($prop['attributes']['required']) || @$prop['null'] === false) && !$readonly;
        $prop['attributes']['readonly'] = $readonly;
        $prop['attributes']['required'] = $required;
        $value = @$row[$fieldName];
        if ($isAddForm && $value === null) {
            $value = isset($prop['attributes']['default'])? $prop['attributes']['default'] : @$prop['default'];
        }
        self::generateInputField($fieldName, $prop, $value);
    }

    public function generateAddForm(array $customFunctions = []/* array of field => function($value, $row, $prop, $isAddForm)  OR  field => false */, array $row = []) {
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
        <?php X_css(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/addform.css");?>
        <?php X_js(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/addform.js");?>
        <?php 
        return $this;
    }

    public function generateTable(array $customFunctions = []/* array of field => function($value, $row, $prop, $isAddForm) */) {
        $canView = (array)$this->canView;
        $defaultCanView = (@$canView[0])? true:false;
        X_js(XLIB_PATH.'helpers/ajaxUpload.js');
        echo '<table class="inlineTableEdit">';
        echo '<thead>';
        echo '<tr>';
            if ((@$canView['id']) !== false) {
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
            echo '<tr data-id="'.$id.'">';
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
        <?php X_css(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/table.css");?>
        <?php X_js(XLIB_PATH."Xenon/CMS/inlineTableEdit_assets/table.js");?>
        <?php
        return $this;
    }

}
