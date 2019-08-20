<?php
namespace Xenon\CMS;

class InlineTableEdit {
    public $source;
    public $data; // [ 'properties' => [...], 'rows' => [...] ]

    public function __construct($source = null) {
        $this->source = $source;
        return $this;
    }

    public function ajaxAutoSave(array $customFunctions = []/* array of field => function(value, row, prop, &data) */) {
        if (!AJAX) return $this;
        if (($upload = X_upload())) die($upload);
        $this->saveData($_POST, $error, false, $customFunctions);
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
                                    $where = new \Xenon\Db\Query\Helper\Where($source);
                                    foreach ((array)$filters as $key => $value) {
                                        if (is_numeric($key)) $where->andWhere($source, $value);
                                        else $where->andWhere($source, $key, $value);
                                    }
                                    $filters = $where;
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

    public function saveData($values, &$error = null, $source = false, array $customFunctions = []/* array of field => function(value, row, prop, &data) */) {
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
                                    // Create New Entry
                                    unset($values['id']);
                                    $data = [];
                                    $returnedData = [];
                                    foreach ($values as $key => $value) {
                                        $prop = $properties['fields'][$key];
                                        //TODO validate some things like attributes from $prop...
                                        if (isset($customFunctions[$key]) && is_callable($customFunctions[$key])) {
                                            $returnedData[$key] = $customFunctions[$key]($value, $values, $prop, $data);
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
                                        $row->save();
                                    } catch(Exception $e) {
                                        $error = "Error while saving new entry: ".$e->getMessage();
                                    }
                                } else {
                                    $row = $source::fetchById($values['id']);
                                    if ($row) {
                                        unset($values['id']);
                                        if (count($values) == 1 && isset($values['_ACTION_'])) {
                                            switch ($values['_ACTION_']) {
                                                case 'DELETE': 
                                                //TODO validate that we can delete it...
                                                try {
                                                    $row->delete();
                                                } catch (Exception $e) {
                                                    $error = "Error while trying to delete entry: ".$e->getMessage();
                                                }
                                                break;
                                                default: 
                                                $error = "Invalid Action";
                                                break;
                                            }
                                        } else {
                                            // Edit Entry
                                            try {
                                                $data = [];
                                                foreach ($values as $key => $value) {
                                                    $prop = $properties['fields'][$key];
                                                    //TODO validate some things like attributes from $prop...
                                                    if (isset($customFunctions[$key]) && is_callable($customFunctions[$key])) {
                                                        $row->set($key, $customFunctions[$key]($value, $row, $prop, $data), false);
                                                    } else {
                                                        $row->set($key, $value, false);
                                                    }
                                                }
                                                foreach ($data as $key => $val) {
                                                    $row->set($key, $val);
                                                }
                                                $row->save();
                                            } catch(Exception $e) {
                                                $error = "Error while saving data: ".$e->getMessage();
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
                            return $this->saveData($values, $error, get_parent_class($source), $customFunctions);
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
        $type = isset($prop['attributes']['type'])? $prop['attributes']['type'] : $prop['type'];
        $value = @$row[$fieldName];
        if (isset($prop['attributes']['strip_tags'])) {
            $value = strip_tags($value);
        }
        $readonly = (isset($prop['attributes']['readonly']) || (!$isAddForm && isset($prop['attributes']['createonly'])))? ' readonly ':'';
        $required = ((isset($prop['attributes']['required']) || $prop['null'] === false) && !$readonly)? ' required ':'';
        if ($type == 'tinyint' && $prop['handler'] == 'bool') {
            $type = 'bool';
        } else if ($type == 'varchar' && $prop['handler'] == 'enum') {
            $type = 'select';
            if (empty($prop['options'])) {
                $prop['options'] = [];
                foreach ($prop['enum'] as $v) {
                    $prop['options'][$v] = $v;
                }
            }
        } else if ($type == 'int' && ($prop['handler'] == 'manytoone' || $prop['handler'] == 'onetoone')) {
            $type = 'select';
        } else if ($type == 'text' && $prop['handler'] == 'array') {
            $type = 'array';
        } else if ($type == 'text' && $prop['handler'] == 'object') {
            $type = 'object';
        }
        if (isset($prop['attributes']['checkbox'])) {
            ?>
            <input type="checkbox" class="checkboxToActivateField" name="<?=$fieldName?>" value="" onchange="$(this).next().find('input,textarea').prop('disabled', !$(this).prop('checked'));" <?=$value?'checked':''?>>
            <div>
            <?php
        }
        switch ($type) {
            case 'varchar':
                //TODO implement translatable
                echo '<input type="text" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="20" maxlength="'.$prop['length'].'" '.$readonly.$required.' />';
            break;
            case 'email':
                echo '<input type="email" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="30" maxlength="'.$prop['length'].'" '.$readonly.$required.' />';
            break;
            case 'phone':
                echo '<input type="phone" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="30" maxlength="'.$prop['length'].'" '.$readonly.$required.' />';
            break;
            case 'decimal':
                echo '<input type="number" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" step="'.(1.0/pow(10, (int)preg_replace("#\d+,\s*(\d+)#","$1",$prop['length']))).'" size="'.(ceil((int)$prop['length']/2)+1).'" '.$readonly.$required.' />';
            break;
            case 'int':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
            case 'number':
                echo '<input type="number" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="'.(ceil((int)$prop['length']/2)+1).'" '.$readonly.$required.' />';
            break;
            case 'password':
                echo '<input type="password" name="'.$fieldName.'" value="" placeholder="New Password" autocomplete="new-password" '.$readonly.$required.' />';
            break;
            case 'date':
                echo '<input type="date" name="'.$fieldName.'" value="'.htmlspecialchars($value?$value->format('Y-m-d'):'').'" '.$readonly.$required.' />';
            break;
            case 'timestamp':
                echo '<input type="datetime-local" name="'.$fieldName.'" value="'.htmlspecialchars($value?$value->format('Y-m-d\TH:i:s'):'').'" '.$readonly.$required.' />';
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
                echo '<select name="'.$fieldName.'" '.$readonly.$required.'>';
                if (($value == '' && $row['id'] != '_NEW_') || $prop['null']) echo '<option></option>';
                if (@$prop['options']) foreach ($prop['options'] as $option_value => $option_row) {
                    $option_labelField = @$prop['attributes']['options_label'];
                    $option_label = $option_labelField? (is_object($option_row)? $option_row->$option_labelField : $option_row[$option_labelField]) : $option_row;
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
                echo '<textarea name="'.$fieldName.'" '.$readonly.$required.'>'.$value.'</textarea>';
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
        }
        if (isset($prop['attributes']['checkbox'])) {
            echo "</div>";
        }
    }

    public function generateAddForm(array $customFunctions = []/* array of field => function(value, row, prop, isAddForm)  OR  field => false */, array $row = []) {
        $row['id'] = '_NEW_';
        echo '<form class="inlineEditTable_add" autocomplete="off">';
        echo '<input type="hidden" name="id" value="_NEW_" />';
        foreach ($this->data['properties']['fields'] as $fieldName => $prop) if (!empty($prop['column']) && $prop['attributes'] && empty($prop['attributes']['readonly'])) {
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
        echo '<input type="submit" />';
        echo '</form>';
        ?>
        <style>
            form.inlineEditTable_add {
                margin: 20px auto;
                width: 90%;
                max-width: 1080px;
                background-color: #eee;
                overflow: hidden;
            }
            form.inlineEditTable_add > label {
                display: inline-block;
                margin: 10px;
                width: 250px;
                float: left;
            }
            form.inlineEditTable_add > label > strong {
                font-size: 14px;
                color: #444;
            }
            form.inlineEditTable_add > label > input,
            form.inlineEditTable_add > label > select,
            form.inlineEditTable_add > label > textarea {
                display: block;
                width: 250px;
                padding: 6px;
            }
            form.inlineEditTable_add > label > input[type="checkbox"] {
                height: 30px;
                width: 30px;
            }
            form.inlineEditTable_add > label > textarea {
                height: 100px;
            }
            form.inlineEditTable_add input[type="submit"] {
                display: block;
                clear: both;
                width: 100px;
                height: 30px;
                margin: 20px auto;
                border: solid 1px #aaa;
            }
            form.inlineEditTable_add div[data-i] {
                min-width: 200px;
                width: 100%;
                clear: both;
            }
            form.inlineEditTable_add div[data-i] > input {
                min-width: initial;
                width: 90%;
                float: left;
            }
            form.inlineEditTable_add div[data-i] > input:nth-child(1) {
                clear: both;
            }
            form.inlineEditTable_add div[data-i] > input[data-nbfields="2"] {
                width: 46%;
            }
            form.inlineEditTable_add div[data-i] i.fa-times {
                width: 5%;
                float: left;
                padding: 5px;
            }
            form.inlineEditTable_add a.add {
                display: inline-block;
                clear: both;
                padding: 10px 20px;
                margin: 5px;
                border-radius: 5px;
                background-color: #ccc;
            }
            form.inlineEditTable_add input.checkboxToActivateField:not(:checked) + div {
                display: none;
            }
        </style>
        <script>
            $('form.inlineEditTable_add').on('submit', function(e){
                e.preventDefault();
                $.ajax({
                    url: '',
                    method: 'post',
                    data: $(this).serialize(),
                    success: function(response){
                        if (response === "OK") {
                            location.reload(true);
                        } else {
                            alert(response);
                        }
                    },
                    error: function(response){
                        alert(response);
                    }
                });
            });
        </script>
        <?php
    }

    public function generateTable(array $customFunctions = []/* array of field => function(value, row, prop, isAddForm) */) {
        X_js(XLIB_PATH.'helpers/ajaxUpload.js');
        echo '<table class="inlineTableEdit">';
        echo '<thead>';
            echo '<tr>';
            echo '<th>';
            echo 'ID / Delete';
            echo '</th>';
            foreach ($this->data['properties']['fields'] as $fieldName => $prop) if ($prop['attributes']) {
                if (isset($customFunctions[$fieldName]) && $customFunctions[$fieldName] === false) {
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
            echo '<td data-fieldname="id">';
            echo $id;
            echo '<i class="fas fa-times" data-delete-id="'.$id.'"></i>';
            echo '</td>';
            foreach ($this->data['properties']['fields'] as $fieldName => $prop) if ($prop['attributes']) {
                if (isset($customFunctions[$fieldName]) && $customFunctions[$fieldName] === false) {
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
        <style>
            table.inlineTableEdit {

            }
            table.inlineTableEdit tr {

            }
            table.inlineTableEdit th {
                padding: 10px;
            }
            table.inlineTableEdit td {
                background-color: #fff;
                border: solid 1px #ccc;
                padding: 5px;
                text-align: center;
            }
            table.inlineTableEdit td > i.fa-times {
                float: right;
                padding: 0 5px;
            }
            table.inlineTableEdit td input,
            table.inlineTableEdit td select,
            table.inlineTableEdit td textarea {
                background-color: transparent;
                max-width: 100%;
                height: 100%;
                min-height: 60px;
                display: block;
                padding: 5px;
                text-align: center;
            }
            table.inlineTableEdit td input[type="checkbox"] {
                height: 30px;
                width: 30px;
            }
            table.inlineTableEdit td input {
                
            }
            table.inlineTableEdit td select {

            }
            table.inlineTableEdit td textarea {

            }
            table.inlineTableEdit td img {
                margin: 10px;
                max-height: 50px;
            }
            table.inlineTableEdit td[status="saving"] {
                background-color: #ff0;
            }
            table.inlineTableEdit td[status="success"] {
                background-color: #080;
            }
            table.inlineTableEdit td[status="error"] {
                background-color: #f00;
            }
            table.inlineTableEdit td[status=""] {
                transition: background-color 1000ms;
            }
            table.inlineTableEdit td div[data-i] {
                min-width: 200px;
                width: 100%;
                clear: both;
            }
            table.inlineTableEdit td div[data-i] > input {
                min-width: initial;
                width: 90%;
                float: left;
            }
            table.inlineTableEdit td div[data-i] > input:nth-child(1) {
                clear: both;
            }
            table.inlineTableEdit td div[data-i] > input[data-nbfields="2"] {
                width: 46%;
            }
            table.inlineTableEdit td div[data-i] i.fa-times {
                width: 5%;
                float: left;
                padding: 5px;
                line-height: 50px;
            }
            table.inlineTableEdit a.add {
                display: inline-block;
                clear: both;
                padding: 10px 20px;
                margin: 5px;
                border-radius: 5px;
                background-color: #ccc;
            }
            table.inlineTableEdit td input.checkboxToActivateField:not(:checked) + div {
                display: none;
            }
        </style>
        <script>
            // Ajax Auto Save
            /*
                Custom JS functions : 
                    X_tableEditBeforeAjax_FIELDNAME(data, $td) // we may modify data, return false to cancel the ajax request
                    X_tableEditAjaxSuccess_FIELDNAME(response, $td) // return true for success, otherwise its considered a failure, string is an error message
            */
            $('.inlineTableEdit').on('change.autosave', 'input[name], select[name], textarea[name], .wysiwyg', function(){
                console.log('triggered autosave');
                var $input = $(this);
                var autoSaveAction = function(){
                    if ($input.get(0).tagName == "INPUT" && $input.get(0).type == "file") return;
                    var $parent = $input.closest('[data-fieldname]');
                    var customBeforeFunc = 'X_tableEditBeforeAjax_'+$parent.attr('data-fieldname');
                    var customSuccessFunc = 'X_tableEditAjaxSuccess_'+$parent.attr('data-fieldname');
                    var data = {
                        id: $parent.attr('data-id'),
                    };
                    $parent.find('[name]').each(function(){
                        var value = $(this).hasClass('wysiwyg')? $(this).html() : $(this).val();
                        if ($(this).attr('name').match(/\[\]$/)) {
                            var name = $(this).attr('name').replace(/\[\]$/, '');
                            if (!$(this).prop('disabled')) {
                                if (this.tagName != "INPUT" || this.type != "checkbox" || $(this).prop('checked')) {
                                    if (typeof data[name] != 'object') {
                                        data[name] = [];
                                    }
                                    data[name].push(value);
                                }
                            }
                        } else {
                            if (this.tagName == "INPUT" && this.type == "checkbox") {
                                value = $(this).prop('checked')? 1:0;
                            }
                            if (typeof value === 'object' && Object.keys(value).length == 0) value = "";
                            if (typeof value === 'undefined') value = "";
                            if (!$(this).prop('disabled')) data[$(this).attr('name')] = value;
                        }
                    });
                    if (typeof window[customBeforeFunc] === 'function') {
                        if (window[customBeforeFunc](data, $parent) === false) {
                            return;
                        }
                    }
                    $parent.attr('status', "saving");
                    $.ajax({
                        url: '',
                        method: 'post',
                        data: data,
                        success: function(response){
                            if (typeof window[customSuccessFunc] === 'function') {
                                response = window[customSuccessFunc](response, $parent);
                            } else {
                                if (response === "OK") {
                                    response = true;
                                }
                            }
                            if (response === true) {
                                $parent.attr('status', "success");
                                setTimeout(function(){$parent.attr('status', "");}, 1000);
                            } else {
                                $parent.attr('status', "error");
                                alert(response);
                            }
                        },
                        error: function(response){
                            $parent.attr('status', "error");
                            alert(response);
                        },
                        complete: function(){

                        }
                    });
                };
                if ($input.is('[delayed]')) {
                    setTimeout(autoSaveAction, +$input.attr('delayed'));
                } else {
                    autoSaveAction();
                }
            });
            // Ajax Delete
            $('.inlineTableEdit').on('click', '[data-delete-id]', function(){
                if (confirm("Delete this entry ?")) {
                    $.ajax({
                        url: '',
                        method: 'post',
                        data: {
                            'id': $(this).attr('data-delete-id'),
                            '_ACTION_': 'DELETE',
                        },
                        success: function(response) {
                            location.reload(true);
                        },
                        error: function(response) {
                            alert(response);
                        }
                    });
                }
            });

            function X_inlineTableEdit_removeArrayElement(fieldName, $elem) {
                var $td = $elem.closest('td');
                $elem.closest('div').remove();
                if ($td.length) {
                    $td.find('input[name="'+fieldName+'"]').trigger('change.autosave');
                }
            }

            function X_inlineTableEdit_addArrayElement(fieldName, structure, $elem) {
                var $parent = $elem.parent();
                var nextIndex = 0;
                $parent.find('div[data-i]').each(function(){
                    var i = +$(this).attr('data-i');
                    if (i >= nextIndex) {
                        nextIndex = i+1;
                    }
                });
                var $div = $('<div>').attr('data-i', nextIndex).insertBefore($elem);

                var appendField = function(name, structure, nbfields) {
                    nbfields = nbfields || 1;
                    switch (typeof structure) {
                        case 'string':
                            switch (structure) {
                                default:
                                    $('<input>').appendTo($div)
                                        .attr('type', structure || 'text')
                                        .attr('name', name)
                                        .attr('data-nbfields', nbfields)
                                        .attr('autocomplete', 'false_'+name.replace(/\]\[/g, '_'))
                                        .attr('value', '')
                                    ;
                                break;
                                case 'textarea':
                                    $('<textarea>').appendTo($div)
                                        .attr('name', name)
                                        .attr('data-nbfields', nbfields)
                                        .attr('autocomplete', 'false_'+name.replace(/\]\[/g, '_'))
                                    ;
                                break;
                            }
                        break;
                        case 'object':
                            if (structure === null) {
                                appendField(name, '');
                            } else {
                                for (var i in structure) {
                                    appendField(name+'['+i+']', structure[i], Object.keys(structure).length);
                                }
                            }
                        break;
                    }
                };
                appendField(fieldName+'['+nextIndex+']', structure);

                $div.find('input').get(0).focus();
                $('<i>').attr('class', 'fas fa-times').on('click', function(){
                    X_inlineTableEdit_removeArrayElement(fieldName, $(this));
                }).appendTo($div);
            }

        </script>
        <?php
    }

}
