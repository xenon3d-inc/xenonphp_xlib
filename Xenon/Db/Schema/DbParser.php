<?php

namespace Xenon\Db\Schema;

class DbParser
{
    protected $table;
    protected $columns = array();

    public function __construct($mysqli_link, $tableName)
    {
        // Get Table Data
        $query = "SHOW CREATE TABLE `" . $tableName . "`;\n";
        $result = mysqli_query($mysqli_link, $query);
        
        if ($result && ($row = mysqli_fetch_row($result))) {

            // Parse Each Line
            $columnLines = explode("\n", $row[1]);
            array_shift($columnLines);
            $tableSpecs = explode(' ', array_pop($columnLines));
            array_shift($tableSpecs);
            $tableData = array();
            $columns = array();
            $tableData['table'] = $tableName;

            // Default Options
            $auto_increment = 1;

            // Set Table Specs
            foreach ($tableSpecs as $specLine) {
                $spec = explode('=', $specLine);
                if (count($spec) == 2) {
                    $value = strtolower($spec[1]);
                    $spec = strtolower($spec[0]);
                    switch ($spec) {
                        case 'engine' :
                            $tableData['engine'] = $value;
                            break;
                        case 'charset' :
                            $tableData['charset'] = $value;
                            break;
                        case 'auto_increment' :
                            $auto_increment = $value;
                            break;
                    }
                }
            }

            // Set TableData
            $this->table = new Table("DB_TABLE(" . $tableName . ")", $tableData);

            // Loop through Columns
            foreach ($columnLines as $columnLine) {
                if (preg_match("#^ *`([a-z0-9_]+)` *([a-z]+)(\(([0-9 ,]+)\))? *(.*),?$#i", $columnLine, $matches)) {
                    // If is Column Definition

                    $columnName = $matches[1];
                    if (!isset($columns[$columnName]['id'])) {
                        $columns[$columnName]['id'] = false; // Set Primary Key to False before setting other params
                    }
                    $columns[$columnName]['column'] = $matches[1];
                    $columns[$columnName]['type'] = $matches[2];
                    if ($matches[4] || $matches[4] === '0') {
                        $columns[$columnName]['length'] = $matches[4];
                    }
                    $options = $matches[5];

                    // NULL
                    if (preg_match("#NOT NULL#i", $options)) {
                        $columns[$columnName]['null'] = false;
                    } else if (preg_match("#NULL#i", $options)) {
                        $columns[$columnName]['null'] = true;
                    }

                    // AUTO_INCREMENT
                    if (preg_match("#AUTO_INCREMENT#i", $options)) {
                        $columns[$columnName]['auto_increment'] = $auto_increment;
                    }

                    // DEFAULT
                    if (preg_match("#DEFAULT ('(.*)'|([0-9a-z_]+( ?\([a-z0-9 ,\._-]*\))?))( |,|$)#i", $options, $matches)) {
                        switch (strtolower($matches[3])) {
                            case 'null':
                                $columns[$columnName]['default'] = null;
                            break;
                            case 'current_timestamp':
                                $columns[$columnName]['default'] = 'current_timestamp()';
                            break;
                            default: 
                                $columns[$columnName]['default'] = $matches[2] . strtolower($matches[3]);
                            break;
                        }
                    }

                    // ON UPDATE
                    if (preg_match("#ON UPDATE ('(.*)'|([0-9a-z_]+( ?\([a-z0-9 ,\._-]*\))?))( |,|$)#i", $options, $matches)) {
                        $columns[$columnName]['onupdate'] = (strtolower($matches[3]) === 'null') ? null : $matches[2] . strtolower($matches[3]);
                    }
                } else {
                    // If is Other Option
                    // Primary Key
                    if (preg_match("#^ *PRIMARY KEY ?\(`([a-z0-9_]+)`\) *(.*),?$#i", $columnLine, $matches)) {
                        $columns[$matches[1]]['id'] = true;
                    }
                    // Foreign Key //FOREIGN KEY (subcategory_parent) REFERENCES category(category_id) ON DELETE CASCADE
                    if (preg_match("#^.*FOREIGN KEY ?[` a-z0-9_-]*\(`([a-z0-9_]+)`\) *REFERENCES `([0-9a-z_]+)`\(`([0-9a-z_]+)`\) *(ON ([a-z]+) ([a-z]+))? *(ON ([a-z]+) ([a-z]+))?,?$#i", $columnLine, $matches)) {
                        $columns[$matches[1]]['foreign_key'] = ['table' => $matches[2], 'column' => $matches[3], 'model' => null, 'field' => null];
                        if ($matches[5] && $matches[6]) {
                            $columns[$matches[1]]['on' . strtolower($matches[5])] = strtolower($matches[6]);
                        }
                        if ($matches[8] && $matches[9]) {
                            $columns[$matches[1]]['on' . strtolower($matches[8])] = strtolower($matches[9]);
                        }
                    }
                    // Index
                    if (preg_match("#^ *(INDEX|KEY) ?[` a-z0-9_-]*\(`([a-z0-9_]+)`\) *(.*),?$#i", $columnLine, $matches)) {
                        $columns[$matches[2]]['index'] = true;
                    }
                }
            }

            // Set Columns Data
            array_walk($columns, function($data, $columnName) use($tableName) {
                $this->columns[$columnName] = new Column("DB_TABLE(" . $tableName . ")", $columnName, $data);
            });
        }
    }

    public function getTable()
    {
        return $this->table;
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function getColumn($columnName)
    {
        if (isset($this->columns[$columnName])) {
            return $this->columns[$columnName];
        }
        return false;
    }

    public static function TableSpecsToSql(array $tableSpecs)
    {
        $specs = array();
        foreach ($tableSpecs as $spec => $value) {
            if (((string) $value) !== '') {
                switch (strtolower($spec)) {
                    case 'engine' :
                        $specs[] = "ENGINE=" . $value;
                        break;
                    case 'auto_increment' :
                        $specs[] = "AUTO_INCREMENT=" . $value;
                        break;
                    case 'charset' :
                        $specs[] = "CHARACTER SET " . $value;
                        break;
                    case 'collate' :
                        $specs[] = "COLLATE " . $value;
                        break;
                }
            }
        }
        return implode(" ", $specs);
    }

    public static function TableToSql(Table $table, array $columns)
    {
        $options = array();
        $specs = array();

        foreach ($columns as $column) {
            if ($column->column === null) continue;
            
            // Column Options
            $options[] = DbParser::ColumnToSql($column);

            // Primary Key
            if ($column->id) {
                $options[] = "PRIMARY KEY (`" . $column->column . "`)";
            }

            // Index
            if ($column->index) {
                $options[] = "INDEX `" . $column->column . "` (`" . $column->column . "`)";
            }

            // Foreign Key
            if ($column->foreign_key) {
                $onDelete = $column->ondelete ? ' ON DELETE ' . strtoupper($column->ondelete) : '';
                $onUpdate = $column->onupdate ? ' ON UPDATE ' . strtoupper($column->onupdate) : '';
                $options[] = "FOREIGN KEY (`" . $column->column . "`) REFERENCES `" . $column->foreign_key['table'] . "`(`" . $column->foreign_key['column'] . "`)" . $onUpdate . $onDelete;
            }

            // AUTO_INCREMENT (for table specs)
            if ($column->auto_increment !== false && $column->auto_increment !== null && $column->auto_increment !== true) {
                $specs['auto_increment'] = $column->auto_increment;
            }
        }

        // Table Specs
        $specs['engine'] = $table->engine;
        $specs['charset'] = $table->charset;
        $specs['collate'] = $table->collate;

        return "CREATE TABLE `" . $table->table .
            "` (\n  " . implode(",\n  ", $options) . "\n) " .
            DbParser::TableSpecsToSql($specs) . ";\n";
    }

    public static function ColumnToSql(Column $column)
    {
        $options = array();

        // Name and Type(length)
        $options[] = "`" . $column->column . "`";
        $options[] = strtoupper($column->type) . ($column->length !== null ? "(" . $column->length . ")" : '');

        // AUTO_INCREMENT
        if ($column->auto_increment !== false && $column->auto_increment !== null) {
            $options[] = "AUTO_INCREMENT";
        }

        // NULL
        $options[] = $column->null ? 'NULL' : 'NOT NULL';

        // DEFAULT
        if ($column->default !== null) {
            if (strtolower($column->default) === 'now()') {
                $column->default = 'current_timestamp';
            }
            if (preg_match("#^['\"`](.*)['\"`]$#i", $column->default, $matches)) {
                $options[] = "DEFAULT '" . addslashes($matches[1]) . "'";
            } else {
                if (in_array(strtoupper($column->default), [
                        'CURRENT_TIMESTAMP',
                    ])) {
                    $options[] = "DEFAULT " . strtoupper($column->default);
                } else {
                    if (preg_match("#^[a-z0-9_]+ ?\([a-z0-9 ,\._-]*\)$#i", $column->default)) {
                        $options[] = "DEFAULT " . $column->default;
                    } else {
                        $options[] = "DEFAULT '" . addslashes($column->default) . "'";
                    }
                }
            }
        }

        // ON UPDATE
        if ($column->onupdate !== null && !$column->foreign_key) {
            if (strtolower($column->onupdate) === 'now()') {
                $column->onupdate = 'current_timestamp';
            }
            if (preg_match("#^['\"`](.*)['\"`]$#i", $column->onupdate, $matches)) {
                $options[] = "ON UPDATE '" . addslashes($matches[1]) . "'";
            } else {
                if (in_array(strtoupper($column->onupdate), [
                        'CURRENT_TIMESTAMP',
                    ])) {
                    $options[] = "ON UPDATE " . strtoupper($column->onupdate);
                } else {
                    if (preg_match("#^[a-z0-9_]+ ?\([a-z0-9 ,\._-]*\)$#i", $column->onupdate)) {
                        $options[] = "ON UPDATE " . $column->onupdate;
                    } else {
                        $options[] = "ON UPDATE '" . addslashes($column->onupdate) . "'";
                    }
                }
            }
        }

        ///////////////////////////////
        return implode(' ', $options);
    }

    public static function AlterTableToSql(ModelData $from, ModelData $to)
    {
        $fromDB = $from;
        $current = $to;
        $existingCols = array();

        $colsToModify = array();
        $colsToAdd = array();
        $primaryKeysToRemove = array();
        $primaryKeysToAdd = array();
        $foreignKeysToModify = array();
        $foreignKeysToRemove = array();
        $foreignKeysToAdd = array();
        $indexesToRemove = array();
        $indexesToAdd = array();
        $colsToDelete = array();

        $tableDataToUpdate = array();

        // Table Data To Update
        if ($fromDB->getTable()->engine != $current->getTable()->engine) {
            $tableDataToUpdate['engine'] = $current->getTable()->engine;
        }
        if ($fromDB->getTable()->charset != $current->getTable()->charset) {
            $tableDataToUpdate['charset'] = $current->getTable()->charset;
        }
        if ($fromDB->getTable()->collate != $current->getTable()->collate) {
            $tableDataToUpdate['collate'] = $current->getTable()->collate;
        }

        // Columns To Add
        foreach ($current->getColumns() as $fieldName => $column) {
            if ($fromDB->getColumn($column->column)) {
                $existingCols[$column->column] = $column;
            } else {
                $colsToAdd[$column->column] = $column;
                if ($column->id) {
                    $primaryKeysToAdd[$column->column] = $column;
                }
                if ($column->index) {
                    $indexesToAdd[$column->column] = $column;
                }
                if ($column->foreign_key) {
                    $foreignKeysToAdd[$column->column] = $column;
                }
                if ($column->auto_increment) {
                    $tableDataToUpdate['auto_increment'] = $column->auto_increment;
                }
            }
        }

        // Columns To Modify
        foreach ($existingCols as $columnName => $column) {

            // PRIMARY KEY
            if ($column->id != $fromDB->getColumn($columnName)->id) {
                if ($column->id) {
                    $primaryKeysToAdd[$columnName] = $column;
                } else {
                    $primaryKeysToRemove[$columnName] = $column;
                }
            }

            // INDEX
            if ($column->index != $fromDB->getColumn($columnName)->index) {
                if ($column->index) {
                    $indexesToAdd[$columnName] = $column;
                } else {
                    $indexesToRemove[$columnName] = $column;
                }
            }

            // FOREIGN KEY
            if ($column->foreign_key || $fromDB->getColumn($columnName)->foreign_key) {
                if ($column->foreign_key) {
                    if ($fromDB->getColumn($columnName)->foreign_key) {
                        if (
                            $column->foreign_key['table'] != $fromDB->getColumn($columnName)->foreign_key['table'] ||
                            $column->foreign_key['column'] != $fromDB->getColumn($columnName)->foreign_key['column'] ||
                            $column->onupdate != $fromDB->getColumn($columnName)->onupdate ||
                            $column->ondelete != $fromDB->getColumn($columnName)->ondelete
                        ) {
                            $foreignKeysToModify[$columnName] = $column;
                        }
                    } else {
                        $foreignKeysToAdd[$columnName] = $column;
                    }
                } else {
                    $foreignKeysToRemove[$columnName] = $column;
                }
            }

            // AUTO_INCREMENT
            if ($column->auto_increment > $fromDB->getColumn($columnName)->auto_increment) {
                $tableDataToUpdate['auto_increment'] = $column->auto_increment;
                $colsToModify[$columnName] = $column;
            }

            // Type, Length, Default, Null
            if (
                $column->type != $fromDB->getColumn($columnName)->type ||
                $column->length != $fromDB->getColumn($columnName)->length ||
                (
                $column->default != str_replace("''", "'", $fromDB->getColumn($columnName)->default) &&
                $column->default != "'" . str_replace("''", "'", $fromDB->getColumn($columnName)->default) . "'"
                ) ||
                (
                $column->onupdate != str_replace("''", "'", $fromDB->getColumn($columnName)->onupdate) &&
                $column->onupdate != "'" . str_replace("''", "'", $fromDB->getColumn($columnName)->onupdate) . "'"
                ) ||
                $column->null != $fromDB->getColumn($columnName)->null
            ) {
                $colsToModify[$columnName] = $column;
            }
        }

        // Columns To Delete
        foreach ($fromDB->getColumns() as $columnName => $column) {
            if (!array_key_exists($columnName, $existingCols)) {
                if ($column->foreign_key) {
                    $foreignKeysToRemove[$columnName] = $column;
                }
                if ($column->index) {
                    $indexesToRemove[$columnName] = $column;
                }
                if ($column->id) {
                    $primaryKeysToRemove[$columnName] = $column;
                }
                $colsToDelete[$columnName] = $column;
            }
        }

        $sqlLines = array();

        $tableSpecs = DbParser::TableSpecsToSql($tableDataToUpdate);
        if ($tableSpecs) {
            $sqlLines[] = $tableSpecs;
        }

        foreach ($colsToModify as $columnName => $column) {
            $sqlLines[] = "MODIFY COLUMN " . DbParser::ColumnToSql($column);
        }
        foreach ($colsToAdd as $columnName => $column) {
            $sqlLines[] = "ADD COLUMN " . DbParser::ColumnToSql($column);
        }
        foreach ($primaryKeysToRemove as $columnName => $column) {
            $sqlLines['DROP PRIMARY KEY'] = "DROP PRIMARY KEY";
        }
        foreach ($primaryKeysToAdd as $columnName => $column) {
            $sqlLines[] = "ADD PRIMARY KEY (`" . $columnName . "`)";
        }
        foreach ($foreignKeysToModify as $columnName => $column) {
            trigger_error("You need to modify the Foreign Key manually in the Database for column `$column->column` in table `".$current->getTable()->table."`", E_USER_NOTICE);
//            $sqlLines[] = "";
        }
        foreach ($foreignKeysToRemove as $columnName => $column) {
            trigger_error("You need to remove the Foreign Key manually in the Database for column `$column->column` in table `".$current->getTable()->table."`", E_USER_NOTICE);
//            $sqlLines[] = "";
        }
        foreach ($foreignKeysToAdd as $columnName => $column) {
            $onDelete = $column->ondelete ? ' ON DELETE ' . strtoupper($column->ondelete) : '';
            $onUpdate = $column->onupdate ? ' ON UPDATE ' . strtoupper($column->onupdate) : '';
            $sqlLines[] = "FOREIGN KEY (`" . $column->column . "`) REFERENCES `" . $column->foreign_key['table'] . "`(`" . $column->foreign_key['column'] . "`)" . $onUpdate . $onDelete;
        }
        foreach ($indexesToRemove as $columnName => $column) {
            $sqlLines[] = "DROP INDEX `" . $columnName . "`";
        }
        foreach ($indexesToAdd as $columnName => $column) {
            $sqlLines[] = "ADD INDEX `" . $columnName . "` (`" . $columnName . "`)";
        }
        foreach ($colsToDelete as $columnName => $column) {
            $sqlLines[] = "DROP COLUMN `" . $columnName . "`";
        }

        if ($sqlLines) {
            return "ALTER TABLE `" . $current->getTable()->table . "` \n  " . implode(",\n  ", $sqlLines) . "\n;\n";
        }
    }

}
