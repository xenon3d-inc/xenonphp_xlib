<?php 
namespace Xenon\CMS;

class CSV {
    public $model;

    public static $TEXTS = [
        'upload_error' => "An error occured while uploading, or nothing was uploaded",
        'file_is_empty' => "File is empty",
        'file_not_exists' => "File '__FILEPATH__' does not exist",
        'invalid_file' => "File must be a valid CSV with the correct structure",
        'error_on_line' => "Error on line __LINE_NUMBER__",
        'obj_updated' => "Row with ID __ID__ has been updated",
        'successfully_saved_n_rows' => "__N_ROWS__ rows were saved successfully",
        'no_rows_saved_error' => "Nothing has been imported",
    ];

    public $config = [
        'fields' => [
            'id' => [
                'hint' => "Row ID",
                'example' => "1",
                // 'import' => function(&$value, &$errorMessage) {
                //     return true;
                // },
                // 'export' => function($value, $row) {
                //     return $value;
                // },
            ],
            //...
        ],
        'primary' => ['id'],
        // 'validate' => function(&$row, &$errorMessage){
        //     return true;
        // },
    ];

    public function __construct($model) {
        global $X_CONFIG;
        if (!empty($X_CONFIG[$model."_CSV"])) {
            $this->config = $X_CONFIG[$model."_CSV"];
        }
        $this->model = $model;
    }

    public function generateExampleTable() {
        ?>
        <table>
            <thead>
                <tr>
                    <?php foreach ($this->config['fields'] as $field => $options) {?>
                        <th><?=$field?></th>
                    <?php }?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <?php foreach ($this->config['fields'] as $field => $options) {?>
                        <td><?=$options['hint']?></td>
                    <?php }?>
                </tr>
                <tr>
                    <?php foreach ($this->config['fields'] as $field => $options) {?>
                        <td><?=$options['example']?></td>
                    <?php }?>
                </tr>
            </tbody>
        </table>
        <?php
    }

    public function upload($inputFileName, &$successMessages, &$warningMessages, &$errorMessages) {
        $file = X_upload($inputFileName, null, "#\.csv$#i");
        if ($file) {
            $this->importFile(DOCUMENT_ROOT.$file, $successMessages, $warningMessages, $errorMessages);
        } else {
            $errorMessages[] = self::$TEXTS['upload_error'];
        }
        return !(!empty($errorMessages) && count($errorMessages));
    }

    public function importFile($filePath, &$successMessages, &$warningMessages, &$errorMessages) {
        if (is_file($filePath)) {
            if (trim($fileContent = file_get_contents($filePath))) {
                $this->importCSVContent($fileContent, $successMessages, $warningMessages, $errorMessages);
            } else {
                $errorMessages[] = self::$TEXTS['file_is_empty'];
            }
        } else {
            $errorMessages[] = str_replace('__FILEPATH__', $filePath, self::$TEXTS['file_not_exists']);
        }
    }

    public function importCSVContent($fileContent, &$successMessages, &$warningMessages, &$errorMessages) {
        $MODEL = $this->model;
        $lines = preg_split("#[\n\r]+#m", $fileContent);
        $header = array_shift($lines);
        if (preg_match("#^(\w+([;,\t]) *){2,}#", $header, $matches)) {
            $separator = $matches[2];
            $headers = explode($separator, $header);
            $rows = [];
            $n = 1;
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                $n++;
                $row = [];
                $rowValues = explode($separator, $line);
                for ($i = 0; $i < count($headers); $i++) {
                    $field = strtolower(trim($headers[$i]));
                    if (array_key_exists($field, $this->config['fields'])) {
                        $options = $this->config['fields'][$field];
                        $value = isset($rowValues[$i])? trim($rowValues[$i]) : "";
                        $row[$field] = $value;
                    }
                }
                // Add missing field values as ""
                foreach ($this->config['fields'] as $field => $options) {
                    if (!isset($row[$field])) {
                        $row[$field] = "";
                    }
                }
                foreach ($row as $field => $value) {
                    $options = $this->config['fields'][$field];
                    // Import/Validate individual column value
                    if (isset($options['import'])) {
                        if (is_callable($options['import'])) {
                            if ($options['import']($value, $errorMessage)) {
                                $row[$field] = $value;
                            } else {
                                $errorMessages[] = str_replace('__LINE_NUMBER__', $n, self::$TEXTS['error_on_line']) ." : $errorMessage";
                            }
                        } else {
                            $row[$field] = $value;
                        }
                    }
                }
                // Validate Row
                if ($this->config['validate']($row, $errorMessage)) {
                    $rows[] = $row;
                } else {
                    $errorMessages[] = str_replace('__LINE_NUMBER__', $n, self::$TEXTS['error_on_line']) ." : $errorMessage";
                }
            }
            if (empty($errorMessages) && count($rows)) {
                // Insert all
                $savedRows = 0;
                foreach ($rows as $row) {
                    $primaryKeys = [];
                    foreach ($this->config['primary'] as $key) {
                        $primaryKeys[$key] = $row[$key];
                    }
                    if (($obj = $MODEL::fetchBy($primaryKeys))) {
                        foreach ($row as $key => $value) {
                            $obj->$key = $value;
                        }
                        $obj->save();
                        $warningMessages[] = str_replace('__ID__', $obj->id, self::$TEXTS['obj_updated']);
                    } else {
                        $obj = (new $MODEL($row))->save();
                    }
                    if ($obj->id) {
                        $savedRows++;
                    }
                }
                $successMessages[] = str_replace('__N_ROWS__', $obj->id, self::$TEXTS['successfully_saved_n_rows']);
            } else {
                $errorMessages[] = self::$TEXTS['no_rows_saved_error'];
            }
        } else {
            $errorMessages[] = self::$TEXTS['invalid_file'];
        }
    }

    public function download($filename = null, $rows = null) {
        if ($filename === null) $filename = $this->model.'_'.date('YmdHis');
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=$filename.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo $this->export($rows);
        exit;
    }

    public function export($rows = null) {
        $MODEL = $this->model;
        if ($rows === null) $rows = $MODEL::select()->fetchAll();
        $fields = $this->config['fields'];
        $output = implode(",", array_keys($fields))."\r\n";
        foreach ($rows as $row) {
            $line = [];
            foreach ($fields as $field => $options) {
                $val = @$row[$field];
                if (isset($options['export'])) {
                    if (is_callable($options['export'])) {
                        $val = $options['export']($val, $row);
                    } else {
                        $val = $options['export'];
                    }
                }
                $line[] = $val;
            }
            $output .= implode(",", $line)."\r\n";
        }
        return $output;
    }

}
