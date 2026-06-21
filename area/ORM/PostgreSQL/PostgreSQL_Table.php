<?php

namespace ORM\PostgreSQL;

use ReflectionClass;

class PostgreSQL_Table
{
    static PDO_SQL|null $DB = null;
    private string $table;
    private array $properties;
    private string $calledClass;
    private array $indexes = [];
    
    public function __construct(string $table, $properties, $calledClass = null)
    {
        $this->table = $table;
        $this->properties = $properties;
        $this->calledClass = $calledClass;
        return $this;
    }
    
    public function index(...$columns): static
    {
        if (is_array(@$columns[0])) {
            $columns = array_merge(...$columns);
        }
        
        if (is_array($columns)) {
            $this->indexes = $columns;
        }
        
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
    
    public function create($replace = true): bool
    {
        return PDO_SQL::run_exec($this->create_table_query($replace)) !== false;
    }
    
    public function delete(): bool
    {
        return PDO_SQL::table_delete($this->table);
    }
    
    public function update(): bool
    {
        if (!PDO_SQL::table_exist($this->table)) {
            return self::create();
        }
        if ($needChange = $this->update_table_query()) {
            var_dump($needChange);
            return PDO_SQL::run_exec($needChange) !== false;
        }
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
        
        $query = 'CREATE EXTENSION IF NOT EXISTS citext; ';
        if ($replace) {
            $query .= "DROP TABLE IF EXISTS " . $this->quote($tableName) . "; ";
        }
        
        $query .= "CREATE TABLE " . $this->quote($tableName) . " (
        " . $this->quote('ID') . " BIGSERIAL PRIMARY KEY,";
        
        $reflection = new ReflectionClass($this->calledClass);
        foreach ($vars as $var) {
            if ($var === "ID") {
                continue;
            }
            
            $isUnique = false;
            $columnType = 'CITEXT';
            $maxLength = null;
            
            if ($reflection->hasProperty($var)) {
                $prop = $reflection->getProperty($var);
                
                $lengthAttributes = $prop->getAttributes(Length::class);
                if (!empty($lengthAttributes)) {
                    /** @var Length $length */
                    $length = $lengthAttributes[0]->newInstance();
                    $columnType = $this->normalize_type_from_attribute($length->type, $length->maxLength);
                    $maxLength = $length->maxLength;
                } else {
                    $type = $prop->getType();
                    if ($type !== null) {
                        $columnType = $this->normalize_type_from_php($type->getName());
                    }
                }
                
                if (!empty($prop->getAttributes(Unique::class))) {
                    $isUnique = true;
                }
            }
            
            $columnDef = $this->quote($var) . " " . $this->build_type_definition($columnType, $maxLength) . " NULL";
            if ($isUnique) {
                $columnDef .= " UNIQUE";
            }
            $query .= $columnDef . ",";
        }
        
        $query = rtrim($query, ',') . ");";
        return $query;
    }
    
    private function update_table_query(): ?string
    {
        $tableName = $this->table;
        $vars = $this->properties;
        $reflection = new ReflectionClass($this->calledClass);
        
        $existingColumns = PDO_SQL::table_columns($tableName);
        $existingColumnMap = [];
        foreach ($existingColumns as $col) {
            $existingColumnMap[$col['Field']] = $col;
        }
        
        $alterStatements = [];
        
        foreach ($vars as $var) {
            if ($var === "ID") {
                continue;
            }
            
            $columnType = 'CITEXT';
            $maxLength = null;
            $isUnique = false;
            
            if ($reflection->hasProperty($var)) {
                $prop = $reflection->getProperty($var);
                
                $lengthAttrs = $prop->getAttributes(Length::class);
                if ($lengthAttrs) {
                    $length = $lengthAttrs[0]->newInstance();
                    $columnType = $this->normalize_type_from_attribute($length->type, $length->maxLength);
                    $maxLength = $length->maxLength;
                } else {
                    $type = $prop->getType();
                    if ($type) {
                        $columnType = $this->normalize_type_from_php($type->getName());
                    }
                }
                
                if ($prop->getAttributes(Unique::class)) {
                    $isUnique = true;
                }
            }
            
            $definition = $this->build_type_definition($columnType, $maxLength) . " NULL";
            
            if (!isset($existingColumnMap[$var])) {
                $alterStatements[] = "ADD COLUMN " . $this->quote($var) . " " . $definition;
                if ($isUnique) {
                    $alterStatements[] = "ADD CONSTRAINT " . $this->quote($this->unique_name($var)) . " UNIQUE (" . $this->quote($var) . ")";
                }
                continue;
            }
            
            $existingType = strtolower((string)$existingColumnMap[$var]['Type']);
            $expectedType = strtolower($this->build_type_definition($columnType, $maxLength));
            $isUniqueInDb = (($existingColumnMap[$var]['Key'] ?? '') === 'UNI');
            
            if ($this->should_modify_column($existingType, $expectedType)) {
                $alterStatements[] = "ALTER COLUMN " . $this->quote($var) . " TYPE " . $this->build_type_definition($columnType, $maxLength) . " USING " . $this->quote($var) . "::" . $this->build_type_definition($columnType, $maxLength);
            }
            
            if ($isUnique && !$isUniqueInDb) {
                $alterStatements[] = "ADD CONSTRAINT " . $this->quote($this->unique_name($var)) . " UNIQUE (" . $this->quote($var) . ")";
            } elseif (!$isUnique && $isUniqueInDb) {
                $alterStatements[] = "DROP CONSTRAINT IF EXISTS " . $this->quote($this->unique_name($var));
            }
        }
        
        foreach ($existingColumnMap as $existingCol => $colData) {
            if ($existingCol === "ID") {
                continue;
            }
            if (!in_array($existingCol, $vars, true)) {
                $alterStatements[] = "DROP COLUMN IF EXISTS " . $this->quote($existingCol);
            }
        }
        
        if (empty($alterStatements)) {
            return null;
        }
        
        return "CREATE EXTENSION IF NOT EXISTS citext; ALTER TABLE " . $this->quote($tableName) . " " . implode(", ", $alterStatements) . ";";
    }
    
    private function index_definition_query(): string
    {
        $tableName = $this->table;
        $rows = PDO_SQL::table_indexes($tableName);
        
        $existingIndexes = [];
        foreach ($rows as $row) {
            if ((int)$row['Non_unique'] === 0) {
                continue;
            }
            $key = $row['Key_name'];
            $seq = (int)$row['Seq_in_index'] - 1;
            $existingIndexes[$key][$seq] = $row['Column_name'];
        }
        
        foreach ($existingIndexes as &$cols) {
            ksort($cols);
            $cols = array_values($cols);
        }
        unset($cols);
        
        $normalizedIndexes = [];
        foreach ($this->indexes as $key => $val) {
            $cols = (array)$val;
            $indexName = is_int($key) ? 'idx_' . strtolower($this->table . '_' . implode('_', $cols)) : strtolower((string)$key);
            $normalizedIndexes[$indexName] = array_map('strtolower', array_values($cols));
        }
        
        $query = '';
        
        foreach ($existingIndexes as $existingName => $existingCols) {
            $existingColsLower = array_map('strtolower', $existingCols);
            $found = false;
            foreach ($normalizedIndexes as $normCols) {
                if ($existingColsLower === $normCols) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $query .= "DROP INDEX IF EXISTS " . $this->quote($existingName) . ";\n";
            }
        }
        
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
                $cols = implode(',', array_map(fn($col) => $this->quote($col), $columns));
                $query .= "CREATE INDEX " . $this->quote($indexName) . " ON " . $this->quote($tableName) . " ($cols);\n";
            }
        }
        
        return $query;
    }
    
    private function should_modify_column(string $existingType, string $expectedType): bool
    {
        $normalize = function (string $type): string {
            $type = strtolower(trim($type));
            $type = str_replace(['character varying', 'double precision'], ['varchar', 'float8'], $type);
            $type = preg_replace('/\s+/', ' ', $type);
            return $type;
        };
        return $normalize($existingType) !== $normalize($expectedType);
    }
    
    private function normalize_type_from_php(string $phpType): string
    {
        return match ($phpType) {
            'int' => 'BIGINT',
            'float' => 'DOUBLE PRECISION',
            'bool' => 'BOOLEAN',
            'string' => 'CITEXT',
            default => 'CITEXT',
        };
    }
    
    private function normalize_type_from_attribute(Type $type, ?string $maxLength): string
    {
        // Use case-insensitive text so SELECT comparisons ignore letter casing.
        if ($type === Type::TEXT)
            return 'CITEXT';
        
        return strtoupper($type->value);
    }
    
    private function build_type_definition(string $columnType, ?string $maxLength): string
    {
        $upper = strtoupper($columnType);
        if (in_array($upper, ['VARCHAR', 'CHAR'], true) && $maxLength !== null) {
            return "$upper($maxLength)";
        }
        if (in_array($upper, ['DECIMAL', 'NUMERIC'], true) && $maxLength !== null) {
            return "$upper($maxLength)";
        }
        return $upper;
    }
    
    private function unique_name(string $column): string
    {
        return $this->table . '_' . $column . '_key';
    }
    
    private function quote(string $identifier): string
    {
        return PDO_SQL::quote($identifier);
    }
    
    public function __destruct()
    {
        if ($this->table) {
            $indexesQuery = $this->index_definition_query();
            if ($indexesQuery) {
                PDO_SQL::run_exec($indexesQuery);
            }
        }
    }
}
