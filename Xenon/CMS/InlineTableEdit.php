<?php
namespace Xenon\CMS;

class InlineTableEdit {
    protected $source;
    protected $data; // [ 'properties' => [...], 'rows' => [...] ]

    public function __construct($source) {
        $this->source = $source;
        return $this;
    }

    public function ajaxAutoSave(array $customFunctions = []/* array of function(value, row) */) {
        if (!AJAX) return $this;
        if (($upload = X_upload())) die($upload);
        $this->saveData($_POST, $error, false, $customFunctions);
        die($error?$error:"OK");
    }

    public function loadData($source = false) {
        if ($source === false) $source = $this->source;
        if ($source === null) return $this;
        switch (gettype($source)) {
            case "string":
                if (class_exists($source)) {
                    switch ($source) {
                        case 'Xenon\Db\Model':
                            $source = $this->source;
                            $query = $source::select();
                            $query->orderBy('id ASC');
                            $this->data = [
                                'properties' => $source::getProperties(),
                                'rows' => $query->fetchAllTableArray(),
                            ];
                            return $this;
                        //TODO handle more class types
                        default:
                            return $this->loadData(get_parent_class($source));
                    }
                } else {
                    //TODO handle more string source types
                }
            break;
            //TODO handle more source types
        }
        return $this;
    }

    public function saveData($values, &$error = null, $source = false, array $customFunctions = []/* array of function(value, row) */) {
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
                                        $prop = $this->$properties['fields'][$fieldName];
                                        //TODO validate some things like attributes from $prop...
                                        if (isset($customFunctions[$key]) && is_callable($customFunctions[$key])) {
                                            $data[$key] = $customFunctions[$key]($value, $values);
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
                                                    $prop = $this->$properties['fields'][$fieldName];
                                                    //TODO validate some things like attributes from $prop...
                                                    if (isset($customFunctions[$key]) && is_callable($customFunctions[$key])) {
                                                        $row->set($key, $customFunctions[$key]($value, $row), false);
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

    public function generateField($row, $fieldName, $prop = null) {
        if ($prop === null) $prop = $this->data['properties']['fields'][$fieldName];
        $type = isset($prop['attributes']['type'])? $prop['attributes']['type'] : $prop['type'];
        $value = @$row[$fieldName];
        if (isset($prop['attributes']['strip_tags'])) {
            $value = strip_tags($value);
        }
        switch ($type) {
            case 'string':
            case 'varchar':
            case 'int':
                echo '<input type="text" name="'.$fieldName.'" value="'.addslashes($value).'" />';
            break;
            case 'image_upload':
                X_simpleImageUpload($fieldName, $value, "//placehold.it/50x50&text=$fieldName", "?size=50x50&margins");
            break;
            case 'select':
                echo '<select name="'.$fieldName.'">';
                if ($value == '' && $row['id'] != '_NEW_') echo '<option></option>';
                if (@$prop['options']) foreach ($prop['options'] as $option_value => $option_row) {
                    $option_label = isset($prop['attributes']['option_label'])? $option_row[$prop['attributes']['option_label']] : $option_row->__toString();
                    echo '<option '.($value == $option_value ? 'selected':'').' value="'.$option_value.'">'.$option_label.'</option>';
                }
                echo '</select>';
            break;
            case 'lang':
                echo '<select name="'.$fieldName.'">';
                if ($value == '' && $row['id'] != '_NEW_') echo '<option></option>';
                foreach (explode('|', LANGS) as $lang) {
                    echo '<option '.($value == $lang ? 'selected':'').' value="'.$lang.'">'.$lang.'</option>';
                }
                echo '</select>';
            break;
            case 'text':
                echo '<textarea name="'.$fieldName.'">'.$value.'</textarea>';
            break;
        }
    }

    public function generateAddForm(array $row = [], array $customFunctions = []/* array of function(value, row) */) {
        $row['id'] = '_NEW_';
        echo '<form class="inlineEditTable_add">';
        echo '<input type="hidden" name="id" value="_NEW_" />';
        foreach ($this->data['properties']['fields'] as $fieldName => $prop) if ($prop['attributes']) {
            echo '<label>';
            echo '<strong>';
            echo isset($prop['attributes']['label'])? $prop['attributes']['label'] : ucfirst(str_replace('_', '', $fieldName));
            echo '</strong>';
            if (isset($customFunctions[$fieldName]) && is_callable($customFunctions[$fieldName])) {
                $customFunctions[$fieldName](@$row[$fieldName], $row);
            } else {
                $this->generateField($row, $fieldName, $prop);
            }
            echo '</label>';
        }
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
            form.inlineEditTable_add > label > textarea {
                height: 100px;
            }
            form.inlineEditTable_add input[type="submit"] {
                display: block;
                clear: both;
                width: 100px;
                height: 30px;
                margin: 10px auto;
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

    public function generateTable(array $customFunctions = []/* array of function(value, row) */) {
        X_js(XLIB_PATH.'helpers/ajaxUpload.js');
        echo '<table class="inlineTableEdit">';
        echo '<thead>';
            echo '<tr>';
            echo '<th>';
            echo 'ID / Delete';
            echo '</th>';
            foreach ($this->data['properties']['fields'] as $fieldName => $prop) if ($prop['attributes']) {
                echo '<th>';
                echo isset($prop['attributes']['label'])? $prop['attributes']['label'] : ucfirst(str_replace('_', '', $fieldName));
                echo '</th>';
            }
            echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        foreach ($this->data['rows'] as $id => $row) {
            echo '<tr>';
            echo '<td>';
            echo $id;
            echo '<i class="fas fa-times" data-delete-id="'.$id.'"></i>';
            echo '</td>';
            foreach ($this->data['properties']['fields'] as $fieldName => $prop) if ($prop['attributes']) {
                echo '<td data-id="'.$id.'" data-fieldname="'.$fieldName.'">';
                if (isset($customFunctions[$fieldName]) && is_callable($customFunctions[$fieldName])) {
                    $customFunctions[$fieldName](@$row[$fieldName], $row);
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
                width: 100%;
                height: 100%;
                min-height: 30px;
                display: block;
                padding: 5px;
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
        </style>
        <script>
            // Ajax Auto Save
            $('table.inlineTableEdit').on('change', 'input, select, textarea', function(){
                $input = $(this);
                if ($input.get(0).tagName == "INPUT" && $input.get(0).type == "file") return;
                $td = $input.closest('td');
                $td.css('background-color', "#ff0");
                var data = {
                    id: $td.attr('data-id'),
                };
                data[$td.attr('data-fieldname')] = $input.val();
                $.ajax({
                    url: '',
                    method: 'post',
                    data: data,
                    success: function(response){
                        if (response === "OK") {
                            $td.css('background-color', "#080").stop().animate({'background-color': '#fff'}, 2000);
                        } else {
                            $td.css('background-color', "#f00");
                            alert(response);
                        }
                    },
                    error: function(response){
                        $td.css('background-color', "#f00");
                        alert(response);
                    },
                    complete: function(){

                    }
                });
            });
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
