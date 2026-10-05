<?php

namespace ORM\MySQL;


use ReflectionClass;

class MySQL_Table
{
    static PDO_SQL|null $DB=null;
    private string $table;
    private array $properties;
    private string $calledClass;
    private array $indexes = array();
    private array $options = [];
    private bool $changeStructure = false;
    
    public function __construct(string $table, $properties, $calledClass = null)
    {
        $this->table = $table;
        $this->properties = $properties;
        $this->calledClass = $calledClass;
        return $this;
    }
    
    /**
     * Each string defines a separate index; each array defines a composite index.
     */
    public function index(...$columns): static
    {
        $this->indexes = $columns;
        $ref = new ReflectionClass($this->calledClass);
        foreach ($ref->getProperties() as $prop) {
            if (!empty($prop->getAttributes(Index::class))) {
                $col = $prop->getName();
                if (!in_array($col, $this->indexes, true)) {
                    $this->indexes[] = $col;
                }
            }
        }
        return $this;
    }

    /**
     * Set CREATE TABLE options. Calling this method more than once merges the
     * supplied options with the options already set on this table.
     */
    public function option(array $options): static
    {
        $allowed = [
            'engine', 'auto_increment', 'avg_row_length', 'character_set',
            'checksum', 'collate', 'comment', 'compression', 'delay_key_write',
            'encryption', 'key_block_size', 'max_rows', 'min_rows',
            'pack_keys', 'row_format',
        ];
        $unknown = array_diff(array_keys($options), $allowed);
        if ($unknown) {
            throw new \InvalidArgumentException('Unsupported MySQL table option: ' . reset($unknown));
        }

        $this->options = array_replace($this->options, $options);
        return $this;
    }
    
    public function create($replace=true): bool
    {
        $this->changeStructure = true;
        return PDO_SQL::run_exec($this->create_table_query($replace))!==false;
    }
    
    public function delete(): bool
    {
        return PDO_SQL::table_delete($this->table);
    }
    
    public function update(): bool
    {
        $this->changeStructure = true;
        if (!PDO_SQL::table_exist($this->table)){
            return self::create();
        }
        // echo $this->update_table_query();
        if ($needChange = $this->update_table_query())
            return PDO_SQL::run_exec($needChange)!==false;
        return false;
    }
    
    public function rename($oldName, $newName): bool
    {
        return PDO_SQL::table_rename($oldName, $newName);
    }
    
    public function exist(): bool
    {
        return PDO_SQL::table_exist($this->table);
    }
    
    private function create_table_query(bool $replace = true): string
    {
        $tableName = $this->table;
        $vars = $this->properties;
        
        $query = '';
        if ($replace) {
            $query .= "DROP TABLE IF EXISTS `{$tableName}`; ";
        }
        
        $query .= "CREATE TABLE `{$tableName}` (
        `ID` INT UNSIGNED NOT NULL AUTO_INCREMENT,";
        
        $reflection = new ReflectionClass($this->calledClass);
        foreach ($vars as $var) {
            if ($var === "ID") {
                continue;
            }
            
            $columnType = 'TEXT';
            $maxLength = null;
            $isUnique = false;
            
            if ($reflection->hasProperty($var)) {
                $prop = $reflection->getProperty($var);
                
                // Length attribute
                $lengthAttributes = $prop->getAttributes(Length::class);
                if (!empty($lengthAttributes)) {
                    /** @var Length $length */
                    $length = $lengthAttributes[0]->newInstance();
                    $columnType = strtoupper($length->type->value);
                    $maxLength = $length->maxLength;
                } else {
                    // Get type from declared property
                    $type = $prop->getType();
                    if ($type !== null) {
                        $typeName = $type->getName();
                        switch ($typeName) {
                            case 'int':
                                $columnType = 'BIGINT UNSIGNED';
                                break;
                            case 'float':
                                $columnType = 'FLOAT';
                                break;
                            case 'bool':
                                $columnType = 'TINYINT(1)';
                                break;
                            case 'string':
                                $columnType = 'TEXT';
                                break;
                            default:
                                $columnType = 'TEXT';
                        }
                    }
                }
                
                // Check for #[Unique]
                $uniqueAttributes = $prop->getAttributes(Unique::class);
                if (!empty($uniqueAttributes)) {
                    $isUnique = true;
                }
            }
            
            $columnDef = "`{$var}` ";
            if ($columnType === 'VARCHAR' && $maxLength !== null) {
                $columnDef .= "VARCHAR({$maxLength}) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            }elseif ($columnType === 'DECIMAL' && $maxLength !== null) {
                $columnDef .= "DECIMAL({$maxLength})";
            } elseif (in_array($columnType, ['TEXT', 'MEDIUMTEXT', 'LONGTEXT'])) {
                $columnDef .= "{$columnType} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
            } else {
                $columnDef .= "{$columnType}";
            }
            
            $columnDef .= " NULL";
            
            if ($isUnique) {
                $columnDef .= " UNIQUE";
            }
            
            $query .= $columnDef . ",";
        }
        
        $query .= "PRIMARY KEY (`ID`) USING BTREE )" . $this->table_options_sql() . ";";
        
        return $query;
    }

    private function table_options_sql(): string
    {
        $options = $this->options;
        $sql = ' ENGINE=' . $this->option_identifier($options['engine'] ?? 'InnoDB');
        $sql .= ' DEFAULT CHARSET=' . $this->option_identifier($options['character_set'] ?? 'utf8mb4');
        $sql .= ' COLLATE=' . $this->option_identifier($options['collate'] ?? 'utf8mb4_unicode_ci');

        $identifiers = ['compression', 'encryption', 'pack_keys', 'row_format', 'delay_key_write'];
        foreach ($identifiers as $key) {
            if (array_key_exists($key, $options)) {
                $sql .= ' ' . strtoupper($key === 'character_set' ? 'CHARACTER SET' : str_replace('_', ' ', $key)) . '=' . $this->option_identifier($options[$key]);
            }
        }

        foreach (['auto_increment', 'avg_row_length', 'key_block_size', 'max_rows', 'min_rows'] as $key) {
            if (array_key_exists($key, $options)) {
                $sql .= ' ' . strtoupper(str_replace('_', ' ', $key)) . '=' . $this->option_integer($options[$key]);
            }
        }
        if (array_key_exists('checksum', $options)) {
            $sql .= ' CHECKSUM=' . ($this->option_bool($options['checksum']) ? '1' : '0');
        }
        if (array_key_exists('comment', $options)) {
            $sql .= " COMMENT='" . str_replace("'", "''", (string)$options['comment']) . "'";
        }
        return $sql;
    }

    private function option_identifier(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_]+$/', $value)) {
            throw new \InvalidArgumentException('MySQL table option must be a simple identifier.');
        }
        return $value;
    }

    private function option_integer(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int)$value < 0) {
            throw new \InvalidArgumentException('MySQL table option must be a non-negative integer.');
        }
        return (int)$value;
    }

    private function option_bool(mixed $value): bool
    {
        if (!is_bool($value) && !in_array($value, [0, 1, '0', '1'], true)) {
            throw new \InvalidArgumentException('MySQL table option must be boolean.');
        }
        return $value === true || $value === 1 || $value === '1';
    }
    
    private function update_table_query(): ?string
    {
        $tableName = $this->table;
        $vars = $this->properties;
        $reflection = new ReflectionClass($this->calledClass);
        
        // گرفتن ستون‌های موجود از دیتابیس
        $existingColumns = PDO_SQL::table_columns($tableName);
        
        
        $existingColumnMap = [];
        foreach ($existingColumns as $col) {
            $existingColumnMap[$col['Field']] = $col;
        }
        
        $alterStatements = [];
        
        foreach ($vars as $index=>$var) {
            if ($var === "ID") continue;
            
            $columnType = 'TEXT';
            $maxLength = null;
            $isUnique = false;
            
            if ($reflection->hasProperty($var)) {
                $prop = $reflection->getProperty($var);
                
                
                // Attribute: #[Length]
                $lengthAttrs = $prop->getAttributes(Length::class);
                if ($lengthAttrs) {
                    $length = $lengthAttrs[0]->newInstance();
                    $columnType = strtoupper($length->type->value);
                    $maxLength = $length->maxLength;
                } else {
                    // Variable type in php
                    $type = $prop->getType();
                    if ($type) {
                        switch ($type->getName()) {
                            case 'int':
                                $columnType = 'BIGINT UNSIGNED';
                                break;
                            case 'float':
                                $columnType = 'FLOAT';
                                break;
                            case 'bool':
                                $columnType = 'TINYINT(1)';
                                break;
                            case 'string':
                                $columnType = 'TEXT';
                                break;
                            default:
                                $columnType = 'TEXT';
                        }
                    }
                }
                
                // Attribute: #[Unique]
                $uniqueAttrs = $prop->getAttributes(Unique::class);
                if ($uniqueAttrs) {
                    $isUnique = true;
                }
            }
            
            // Create define columns
            if ($columnType === 'VARCHAR' && $maxLength) {
                $definition = "VARCHAR({$maxLength}) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL";
            } elseif ($columnType === 'DECIMAL' && $maxLength !== null) {
                $definition = "DECIMAL({$maxLength})";
            }elseif (in_array($columnType, ['TEXT', 'MEDIUMTEXT', 'LONGTEXT'])) {
                $definition = "{$columnType} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL";
            } else {
                $definition = "{$columnType} NULL";
            }
            
            // Add UNIQUE
            if ($isUnique) {
                $definition .= " UNIQUE";
            }
            
            if (!isset($existingColumnMap[$var])) {
                // If column not exist → ADD
                $alterStatements[] = "ADD COLUMN `{$var}` {$definition}";
            } else {
                // If column exist → MODIFY
                $existingType = strtolower($existingColumnMap[$var]['Type']);
                $expectedType = strtolower(preg_replace('/ character set.*$/i', '', $definition));
                
                $isUniqueInDb = ($existingColumnMap[$var]['Key'] ?? '') === 'UNI';
                
                // Need any change?
                $shouldModify =
                    $this->should_modify_column($existingType, $expectedType) ||
                    ($isUnique !== $isUniqueInDb);
                
                if ($shouldModify) {
                    // If change TYPE or MODIFY
                    $alterStatements[] = "MODIFY COLUMN `{$var}` {$definition}";
                    
                    // If was unique but now not → DROP INDEX
                    if (!$isUnique && $isUniqueInDb) {
                        $alterStatements[] = "DROP INDEX `{$var}`";
                    }
                    
                    // if was not unique but now is → Don't need change because in MODIFY
                }
            }
            
            // Remove extra columns not in class
            foreach ($existingColumnMap as $existingCol => $colData) {
                if ($existingCol === "ID") continue; // Don't drop primary key
                if (!in_array($existingCol, $vars)) {
                    $alterStatements[] = "DROP COLUMN  IF EXISTS `$existingCol`";
                }
            }
            
        }
        
        if (empty($alterStatements)) return null;
        
        return "ALTER TABLE `{$tableName}` " . implode(", ", $alterStatements) . ";";
    }
    
    private function index_definition_query(): string
    {
        $tableName = $this->table;
        
        // Get existing indexes
        $rows = PDO_SQL::table_indexes($tableName);
        $columnTypes = [];
        foreach (PDO_SQL::table_columns($tableName) as $column) {
            $columnTypes[strtolower($column['Field'])] = strtolower($column['Type']);
        }
        
        $existingIndexes = [];
        foreach ($rows as $row) {
            if ((int)$row['Non_unique'] === 0) continue; // Don't consider unique indexes
            
            $key = $row['Key_name'];
            $seq = (int)$row['Seq_in_index'] - 1;
            $existingIndexes[$key][$seq] = $row['Column_name'];
        }
        
        foreach ($existingIndexes as &$cols) {
            ksort($cols);
            $cols = array_values($cols);
        }
        unset($cols);
        
        // Defined indexes in Class
        $normalizedIndexes = [];
        foreach ($this->indexes as $key => $val) {
            $cols = (array)$val;
            $indexName = is_int($key) ? 'idx_' . implode('_', $cols) : $key;
            $normalizedIndexes[$indexName] = array_values($cols);
        }
        
        $query = '';
        
        // Remove extra indexes not in class yet
        foreach ($existingIndexes as $existingName => $existingCols) {
            $existingColsLower = array_map('strtolower', $existingCols);
            
            $found = false;
            foreach ($normalizedIndexes as $normCols) {
                if ($existingColsLower === array_map('strtolower', $normCols)) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $query .= "ALTER TABLE `{$tableName}` DROP INDEX `{$existingName}`;\n";
            }
        }
        
        // اضافه کردن ایندکس‌های جدید
        foreach ($normalizedIndexes as $indexName => $columns) {
            $columnsNormalized = array_map('strtolower', $columns);
            
            $found = false;
            foreach ($existingIndexes as $existingCols) {
                $existingColsNormalized = array_map('strtolower', $existingCols);
                if ($existingColsNormalized === $columnsNormalized) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                // TEXT/BLOB indexes need a prefix. Budget for utf8mb4's four
                // bytes per character within InnoDB's 3072-byte key limit.
                $prefixLength = min(191, max(1, intdiv(3072, 4 * count($columns))));
                $indexColumns = [];
                foreach ($columns as $column) {
                    $quotedColumn = '`' . str_replace('`', '``', $column) . '`';
                    $type = $columnTypes[strtolower($column)] ?? '';
                    if (preg_match('/^(tinytext|text|mediumtext|longtext|tinyblob|blob|mediumblob|longblob)\\b/', $type)) {
                        $quotedColumn .= "({$prefixLength})";
                    }
                    $indexColumns[] = $quotedColumn;
                }
                $cols = implode(',', $indexColumns);
                $query .= "ALTER TABLE `{$tableName}` ADD INDEX `$indexName` ($cols);\n";
            }
        }
        
        return $query;
    }
    
    private function should_modify_column(string $existingType, string $expectedType): bool {
        // normalize
        $existingType = strtolower($existingType);
        $expectedType = strtolower($expectedType);
        
        // parse base type, length (supports "10" and "10,2") and unsigned
        $parse_type = function ($type) {
            $unsigned = str_contains($type, 'unsigned');
            $base = trim(preg_replace('/[^a-z]+.*$/', '', $type));
            $length = null;
            if (preg_match('/\(([^)]+)\)/', $type, $matches)) {
                $lenRaw = trim($matches[1]);
                if (preg_match('/^\d+$/', $lenRaw)) {
                    $length = (int)$lenRaw;
                } else {
                    // keep precision/scale as compact string e.g. "10,2"
                    $length = preg_replace('/\s+/', '', $lenRaw);
                }
            }
            return [
                'base' => $base,
                'length' => $length,
                'unsigned' => $unsigned
            ];
        };
        
        $ex = $parse_type($existingType);
        $exp = $parse_type($expectedType);
        
        // base type changed
        if ($ex['base'] !== $exp['base']) {
            return true;
        }
        // unsigned changed
        if ($ex['unsigned'] !== $exp['unsigned']) {
            return true;
        }
        // varchar/char length check
        if (in_array($ex['base'], ['varchar', 'char'])) {
            return (int)$ex['length'] !== (int)$exp['length'];
        }
        // decimal precision/scale check (compare as normalized string)
        if ($ex['base'] === 'decimal') {
            return ((string)$ex['length']) !== ((string)$exp['length']);
        }
        // otherwise no change detected
        return false;
    }
    public function __destruct()
    {
        if ($this->changeStructure && $this->table) { //Must update indexes when finish create or update table
            $indexesQuery = $this->index_definition_query();
            // echo $indexesQuery;
            if ($indexesQuery)
                PDO_SQL::run_exec($indexesQuery);
        }
    }
    
    
}
