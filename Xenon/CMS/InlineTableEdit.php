<?php
namespace Xenon\CMS;

class InlineTableEdit {
    public $source;
    public $data; // [ 'properties' => [...], 'rows' => [...] ]

    public function __construct($source = null) {
        $this->source = $source;
        return $this;
    }

    public function ajaxAutoSave(array $customFunctions = []/* array of field => function(value, row, prop) */) {
        if (!AJAX) return $this;
        if (($upload = X_upload())) die($upload);
        $this->saveData($_POST, $error, false, $customFunctions);
        die($error?$error:"OK");
    }

    public function loadData($source = false, $orderBy = 'id ASC') {
        if ($source === false) $source = $this->source;
        if ($source === null) return $this;
        switch (gettype($source)) {
            case "string":
                if (class_exists($source)) {
                    switch ($source) {
                        case 'Xenon\Db\Model':
                            $source = $this->source;
                            $query = $source::select();
                            $query->orderBy($orderBy);
                            $this->data = [
                                'query' => "$query",
                                'properties' => $source::getProperties(true),
                                'rows' => $query->fetchAllTableArray(),
                            ];
                            return $this;
                        //TODO handle more class types
                        default:
                            return $this->loadData(get_parent_class($source), $orderBy);
                    }
                } else {
                    //TODO handle more string source types
                }
            break;
            //TODO handle more source types
        }
        return $this;
    }

    public function saveData($values, &$error = null, $source = false, array $customFunctions = []/* array of field => function(value, row, prop) */) {
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
                                    foreach ($values as $key => $value) {
                                        $prop = $properties['fields'][$key];
                                        //TODO validate some things like attributes from $prop...
                                        if (isset($customFunctions[$key]) && is_callable($customFunctions[$key])) {
                                            $data[$key] = $customFunctions[$key]($value, $values, $prop);
                                        } else {
                                            if (isset($prop['attributes']['strip_tags'])) {
                                                $value = strip_tags($value);
                                            }
                                            $data[$key] = $value;
                                        }
                                    }
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
                                                foreach ($values as $key => $value) {
                                                    $prop = $properties['fields'][$key];
                                                    //TODO validate some things like attributes from $prop...
                                                    if (isset($customFunctions[$key]) && is_callable($customFunctions[$key])) {
                                                        $row->set($key, $customFunctions[$key]($value, $row, $prop), false);
                                                    } else {
                                                        $row->set($key, $value, false);
                                                    }
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

    public function generateField($row, $fieldName, $prop = null) {
        if ($prop === null) $prop = $this->data['properties']['fields'][$fieldName];
        $type = isset($prop['attributes']['type'])? $prop['attributes']['type'] : $prop['type'];
        $value = @$row[$fieldName];
        if (isset($prop['attributes']['strip_tags'])) {
            $value = strip_tags($value);
        }
        $readonly = !empty($prop['attributes']['readonly'])? ' readonly ':'';
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
        }
        switch ($type) {
            case 'varchar':
                //TODO implement translatable
                echo '<input type="text" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="20" maxlength="'.$prop['length'].'" '.$readonly.' />';
            break;
            case 'email':
                echo '<input type="email" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="30" maxlength="'.$prop['length'].'" '.$readonly.' />';
            break;
            case 'phone':
                echo '<input type="phone" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="30" maxlength="'.$prop['length'].'" '.$readonly.' />';
            break;
            case 'decimal':
            case 'int':
            case 'tinyint':
            case 'smallint':
            case 'mediumint':
            case 'bigint':
                echo '<input type="text" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="'.(ceil((int)$prop['length']/2)+1).'" '.$readonly.' />';
            break;
            case 'number':
                echo '<input type="number" name="'.$fieldName.'" value="'.htmlspecialchars($value).'" size="'.(ceil((int)$prop['length']/2)+1).'" '.$readonly.' />';
            break;
            case 'password':
                echo '<input type="password" name="'.$fieldName.'" value="" placeholder="New Password" autocomplete="new-password" '.$readonly.' />';
            break;
            case 'date':
                echo '<input type="date" name="'.$fieldName.'" value="'.htmlspecialchars($value?$value->format('Y-m-d'):'').'" '.$readonly.' />';
            break;
            case 'timestamp':
                echo '<input type="datetime-local" name="'.$fieldName.'" value="'.htmlspecialchars($value?$value->format('Y-m-d\TH:i:s'):'').'" '.$readonly.' />';
            break;
            case 'bool':
                echo '<input type="checkbox" name="'.$fieldName.'" value="1" '.($value && $value !== '0' ? 'checked':'').' '.$readonly.' />';
            break;
            case 'image_upload':
                if ($readonly) {
                    echo '<img src="'.($value?($value.'?size=50x50&margins'):'//placehold.it/50x50&text='.$fieldName).'" alt="'.$fieldName.'" />';
                } else {
                    X_simpleImageUpload($fieldName, $value, "//placehold.it/50x50&text=$fieldName", "?size=50x50&margins");
                }
            break;
            case 'select':
                echo '<select name="'.$fieldName.'" '.$readonly.' >';
                if (($value == '' && $row['id'] != '_NEW_') || $prop['null']) echo '<option></option>';
                if (@$prop['options']) foreach ($prop['options'] as $option_value => $option_row) {
                    $option_labelField = @$prop['attributes']['options_label'];
                    $option_label = $option_labelField? (is_object($option_row)? $option_row->$option_labelField : $option_row[$option_labelField]) : $option_row;
                    echo '<option '.($value == $option_value ? 'selected':'').' value="'.$option_value.'">'.$option_label.'</option>';
                }
                echo '</select>';
            break;
            case 'lang':
                echo '<select name="'.$fieldName.'" '.$readonly.' >';
                if ($value == '' && $row['id'] != '_NEW_') echo '<option></option>';
                foreach (explode('|', LANGS) as $lang) {
                    echo '<option '.($value == $lang ? 'selected':'').' value="'.$lang.'">'.$lang.'</option>';
                }
                echo '</select>';
            break;
            case 'text':
                //TODO implement translatable
                echo '<textarea name="'.$fieldName.'" '.$readonly.' >'.$value.'</textarea>';
            break;
        }
    }

    public function generateAddForm(array $row = [], array $customFunctions = []/* array of field => function(value, row, prop) */) {
        $row['id'] = '_NEW_';
        echo '<form class="inlineEditTable_add" autocomplete="off">';
        echo '<input type="hidden" name="id" value="_NEW_" />';
        foreach ($this->data['properties']['fields'] as $fieldName => $prop) if ($prop['attributes'] && empty($prop['attributes']['readonly'])) {
            echo '<label data-fieldname="'.$fieldName.'">';
            echo '<strong>';
            echo isset($prop['attributes']['label'])? $prop['attributes']['label'] : ucfirst(str_replace('_', ' ', $fieldName));
            echo '</strong>';
            if (isset($customFunctions[$fieldName]) && is_callable($customFunctions[$fieldName])) {
                $customFunctions[$fieldName](@$row[$fieldName], $row, $prop);
            } else {
                $this->generateField($row, $fieldName, $prop);
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

    public function generateTable(array $customFunctions = []/* array of field => function(value, row, prop) */) {
        X_js(XLIB_PATH.'helpers/ajaxUpload.js');
        echo '<table class="inlineTableEdit">';
        echo '<thead>';
            echo '<tr>';
            echo '<th>';
            echo 'ID / Delete';
            echo '</th>';
            foreach ($this->data['properties']['fields'] as $fieldName => $prop) if ($prop['attributes']) {
                echo '<th data-fieldname="'.$fieldName.'">';
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
                echo '<td data-id="'.$id.'" data-fieldname="'.$fieldName.'">';
                if (isset($customFunctions[$fieldName]) && is_callable($customFunctions[$fieldName])) {
                    $customFunctions[$fieldName](@$row[$fieldName], $row, $prop);
                } else {
                    $this->generateField($row, $fieldName, $prop);
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
        </style>
        <script>
            // Ajax Auto Save
            /*
                Custom JS functions : 
                    X_tableEditBeforeAjax_FIELDNAME(data, $td) // we may modify data, return false to cancel the ajax request
                    X_tableEditAjaxSuccess_FIELDNAME(response, $td) // return true for success, otherwise its considered a failure, string is an error message
            */
            $('table.inlineTableEdit').on('change', 'input[name], select[name], textarea[name]', function(){
                var $input = $(this);
                if ($input.get(0).tagName == "INPUT" && $input.get(0).type == "file") return;
                var $td = $input.closest('td');
                var customBeforeFunc = 'X_tableEditBeforeAjax_'+$td.attr('data-fieldname');
                var customSuccessFunc = 'X_tableEditAjaxSuccess_'+$td.attr('data-fieldname');
                var data = {
                    id: $td.attr('data-id'),
                };
                $td.find('[name]').each(function(){
                    var value = $(this).val();
                    if (this.tagName == "INPUT" && this.type == "checkbox") {
                        value = $(this).prop('checked')? 1:0;
                    }
                    data[$(this).attr('name')/*$td.attr('data-fieldname')*/] = value;
                });
                if (typeof window[customBeforeFunc] === 'function') {
                    if (window[customBeforeFunc](data, $td) === false) {
                        return;
                    }
                }
                $td.attr('status', "saving");
                $.ajax({
                    url: '',
                    method: 'post',
                    data: data,
                    success: function(response){
                        if (typeof window[customSuccessFunc] === 'function') {
                            response = window[customSuccessFunc](response, $td);
                        } else {
                            if (response === "OK") {
                                response = true;
                            }
                        }
                        if (response === true) {
                            $td.attr('status', "success");
                            setTimeout(function(){$td.attr('status', "");}, 1000);
                        } else {
                            $td.attr('status', "error");
                            alert(response);
                        }
                    },
                    error: function(response){
                        $td.attr('status', "error");
                        alert(response);
                    },
                    complete: function(){

                    }
                });
            });
            // Ajax Delete
            $('table.inlineTableEdit').on('click', '[data-delete-id]', function(){
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
        </script>
        <?php
    }

}
