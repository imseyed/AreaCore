<?php

namespace AreaCore\ORM\PostgreSQL;

use ORM\PostgreSQL\Index;
use ORM\PostgreSQL\Length;
use ORM\PostgreSQL\NoCase;
use ORM\PostgreSQL\PDO_SQL;
use ORM\PostgreSQL\Type;
use ORM\PostgreSQL\Unique;
use ReflectionClass;

class PostgreSQL_Table
{
    static PDO_SQL|null $DB = null;
    private string $table;
    private array $properties;
    private string $calledClass;
    private array $indexes = [];
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
        $allowed = ['temporary', 'unlogged', 'tablespace', 'storage_parameters'];
        $unknown = array_diff(array_keys($options), $allowed);
        if ($unknown) {
            throw new \InvalidArgumentException('Unsupported PostgreSQL table option: ' . reset($unknown));
        }
        if (($options['temporary'] ?? $this->options['temporary'] ?? false)
            && ($options['unlogged'] ?? $this->options['unlogged'] ?? false)) {
            throw new \InvalidArgumentException('PostgreSQL tables cannot be both TEMPORARY and UNLOGGED.');
        }
        if (isset($options['storage_parameters']) && !is_array($options['storage_parameters'])) {
            throw new \InvalidArgumentException('PostgreSQL storage_parameters must be an associative array.');
        }
        $this->options = array_replace($this->options, $options);
        return $this;
    }

    public function create($replace = true): bool
    {
        $this->changeStructure = true;
        return PDO_SQL::run_exec($this->create_table_query($replace)) !== false;
    }

    public function delete(): bool
    {
        return PDO_SQL::table_delete($this->table);
    }

    public function update(): bool
    {
        if (!PDO_SQL::table_exist($this->table)) {
            return $this->create();
        }

        $identifier = $this->calledClass::identifier_column();
        $columns = PDO_SQL::table_columns($this->table);
        $changedIdentifier = false;
        if ($this->identifier_needs_update($columns, $identifier)) {
            $changedIdentifier = PDO_SQL::change_empty_table($this->table, function () use ($identifier) {
                $columns = PDO_SQL::table_columns($this->table);
                if (!$this->identifier_needs_update($columns, $identifier)) return null;
                $primary = array_values(array_filter($columns, fn($column) => ($column['Key'] ?? '') === 'PRI'));
                if (count($primary) === 1 && $primary[0]['Field'] === $identifier) {
                    $quoted = $this->quote($identifier);
                    $dropGeneration = !empty($primary[0]['Identity']) ? ' DROP IDENTITY,' : ' DROP DEFAULT,';
                    return 'ALTER TABLE ' . $this->quote($this->table)
                        . ' ALTER COLUMN ' . $quoted . $dropGeneration
                        . ' ALTER COLUMN ' . $quoted . ' TYPE UUID USING ' . $quoted . '::text::uuid,'
                        . ' ALTER COLUMN ' . $quoted . ' SET DEFAULT gen_random_uuid()';
                }
                return $this->identifier_update_query($columns, $identifier);
            });
            if (!$changedIdentifier) return false;
        }

        $needChange = $this->update_table_query();
        if ($needChange && PDO_SQL::run_exec($needChange) === false) return false;
        $this->changeStructure = true;
        return $changedIdentifier || (bool)$needChange;
    }

    public function update_sequence(): bool
    {
        $quotedTable = $this->quote($this->table);
        $identifier = $this->calledClass::identifier_column();
        $quotedID = $this->quote($identifier);
        $query = "SELECT setval(pg_get_serial_sequence(?, ?), COALESCE((SELECT MAX($quotedID) FROM $quotedTable), 1), EXISTS (SELECT 1 FROM $quotedTable));";

        return PDO_SQL::run_exec($query, [$quotedTable, $identifier]) !== false;
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
        $identifier = $this->calledClass::identifier_column();

        $query = '';
        $usesCitext = false;
        if ($replace) {
            $query .= "DROP TABLE IF EXISTS " . $this->quote($tableName) . "; ";
        }

        $tableKind = !empty($this->options['temporary']) ? 'TEMPORARY' : (!empty($this->options['unlogged']) ? 'UNLOGGED' : '');
        $query .= "CREATE " . ($tableKind ? $tableKind . ' ' : '') . "TABLE " . $this->quote($tableName) . " (";
        if (!in_array($identifier, $vars, true)) $query .= $this->quote('ID') . " BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,";

        foreach ($vars as $var) {
            $columnDef = $this->column_definition($var, $identifier);
            if (str_contains($columnDef, ' CITEXT')) $usesCitext = true;
            $query .= $columnDef . ",";
        }

        $query = ($usesCitext ? 'CREATE EXTENSION IF NOT EXISTS citext; ' : '') . rtrim($query, ',') . ")" . $this->table_options_sql() . ";";
        return $query;
    }

    private function column_definition(string $var, string $identifier): string
    {
        $reflection = new ReflectionClass($this->calledClass);
        $isUnique = false;
        $columnType = 'TEXT';
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

            if ($var === $identifier && !$prop->getType() && !$lengthAttributes && !in_array($var, ['Uuid', 'uuid', 'UUID'], true)) {
                $columnType = 'BIGINT';
            }

            if (!empty($prop->getAttributes(Unique::class))) {
                $isUnique = true;
            }
        }

        if ($var === $identifier && $this->is_uuid_identifier($identifier)) {
            $columnType = 'UUID';
        }
        $columnDef = $this->quote($var) . " " . $this->build_type_definition($columnType, $maxLength);
        if ($var === $identifier) {
            if ($columnType === 'UUID') {
                $columnDef .= ' DEFAULT gen_random_uuid() PRIMARY KEY';
            } else {
                $columnDef .= in_array(strtoupper($columnType), ['SMALLINT', 'INTEGER', 'BIGINT'], true) ? " GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY" : " PRIMARY KEY";
            }
        } else {
            $columnDef .= " NULL";
        }
        if ($isUnique && $var !== $identifier) {
            $columnDef .= " UNIQUE";
        }
        return $columnDef;
    }

    private function table_options_sql(): string
    {
        $sql = '';
        if (isset($this->options['storage_parameters'])) {
            $parameters = [];
            foreach ($this->options['storage_parameters'] as $name => $value) {
                if (!is_string($name) || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
                    throw new \InvalidArgumentException('Invalid PostgreSQL storage parameter name.');
                }
                if (!is_scalar($value)) {
                    throw new \InvalidArgumentException('PostgreSQL storage parameter values must be scalar.');
                }
                $value = is_bool($value) ? ($value ? 'true' : 'false') : str_replace("'", "''", (string)$value);
                $parameters[] = $name . "='" . $value . "'";
            }
            if ($parameters) {
                $sql .= ' WITH (' . implode(', ', $parameters) . ')';
            }
        }
        if (isset($this->options['tablespace'])) {
            if (!is_string($this->options['tablespace']) || !preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $this->options['tablespace'])) {
                throw new \InvalidArgumentException('PostgreSQL tablespace must be a valid identifier.');
            }
            $sql .= ' TABLESPACE ' . $this->quote($this->options['tablespace']);
        }
        return $sql;
    }

    private function is_uuid_identifier(string $identifier): bool
    {
        if (strcasecmp($identifier, 'uuid') === 0) return true;
        $reflection = new ReflectionClass($this->calledClass);
        if (!$reflection->hasProperty($identifier)) return false;
        $attributes = $reflection->getProperty($identifier)->getAttributes(Length::class);
        return $attributes && $attributes[0]->newInstance()->type === Type::UUID;
    }

    private function identifier_needs_update(array $columns, string $identifier): bool
    {
        $primary = array_values(array_map(
            fn($column) => $column['Field'],
            array_filter($columns, fn($column) => ($column['Key'] ?? '') === 'PRI')
        ));
        if ($primary !== [$identifier]) return true;
        if (!$this->is_uuid_identifier($identifier)) return false;
        foreach ($columns as $column) {
            if ($column['Field'] === $identifier) return strtolower($column['Type']) !== 'uuid';
        }
        return true;
    }

    private function identifier_update_query(array $columns, string $identifier): string
    {
        $primary = array_values(array_filter($columns, fn($column) => ($column['Key'] ?? '') === 'PRI'));
        $existing = array_column($columns, null, 'Field');
        $actions = [];
        if ($primary) {
            $actions[] = 'DROP CONSTRAINT ' . $this->quote($primary[0]['PrimaryConstraint']);
            foreach ($primary as $column) {
                if ($column['Field'] !== $identifier && !empty($column['Identity'])) {
                    $actions[] = 'ALTER COLUMN ' . $this->quote($column['Field']) . ' DROP IDENTITY';
                }
                if ($column['Field'] !== $identifier && in_array($column['Field'], $this->properties, true)) {
                    $actions[] = 'ALTER COLUMN ' . $this->quote($column['Field']) . ' DROP NOT NULL';
                }
            }
        }
        $definition = in_array($identifier, $this->properties, true)
            ? $this->column_definition($identifier, $identifier)
            : $this->quote($identifier) . ' BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY';
        $definition = substr($definition, strlen($this->quote($identifier)) + 1);
        $definition = substr($definition, 0, -strlen(' PRIMARY KEY'));
        $identity = str_ends_with($definition, ' GENERATED BY DEFAULT AS IDENTITY');
        $uuid = str_ends_with($definition, ' DEFAULT gen_random_uuid()');
        $type = $identity ? substr($definition, 0, -strlen(' GENERATED BY DEFAULT AS IDENTITY'))
            : ($uuid ? substr($definition, 0, -strlen(' DEFAULT gen_random_uuid()')) : $definition);
        $quoted = $this->quote($identifier);
        if (isset($existing[$identifier])) {
            if ($uuid && !empty($existing[$identifier]['Identity'])) {
                $actions[] = 'ALTER COLUMN ' . $quoted . ' DROP IDENTITY';
            } elseif ($uuid) {
                $actions[] = 'ALTER COLUMN ' . $quoted . ' DROP DEFAULT';
            }
            $actions[] = 'ALTER COLUMN ' . $quoted . ' TYPE ' . $type . ' USING ' . $quoted . ($uuid ? '::text::uuid' : '::' . $type);
            $actions[] = 'ALTER COLUMN ' . $quoted . ' SET NOT NULL';
            if ($identity && empty($existing[$identifier]['Identity'])) {
                $actions[] = 'ALTER COLUMN ' . $quoted . ' DROP DEFAULT';
                $actions[] = 'ALTER COLUMN ' . $quoted . ' ADD GENERATED BY DEFAULT AS IDENTITY';
            } elseif (!$identity && !$uuid && !empty($existing[$identifier]['Identity'])) {
                $actions[] = 'ALTER COLUMN ' . $quoted . ' DROP IDENTITY';
            }
            if ($uuid) $actions[] = 'ALTER COLUMN ' . $quoted . ' SET DEFAULT gen_random_uuid()';
        } else {
            $actions[] = 'ADD COLUMN ' . $quoted . ' ' . $definition;
        }
        $actions[] = 'ADD PRIMARY KEY (' . $quoted . ')';
        return 'ALTER TABLE ' . $this->quote($this->table) . ' ' . implode(', ', $actions);
    }

    private function update_table_query(): ?string
    {
        $identifier = $this->calledClass::identifier_column();
        $tableName = $this->table;
        $vars = $this->properties;
        $reflection = new ReflectionClass($this->calledClass);

        $existingColumns = PDO_SQL::table_columns($tableName);
        $existingColumnMap = [];
        foreach ($existingColumns as $col) {
            $existingColumnMap[$col['Field']] = $col;
        }

        $alterStatements = [];
        $usesCitext = false;
        if ($this->is_uuid_identifier($identifier) && isset($existingColumnMap[$identifier])
            && strtolower($existingColumnMap[$identifier]['Type']) === 'uuid'
            && !preg_match('/^(?:pg_catalog\\.)?gen_random_uuid\\(\\)$/i', trim($existingColumnMap[$identifier]['Default'] ?? ''))) {
            $alterStatements[] = 'ALTER COLUMN ' . $this->quote($identifier) . ' SET DEFAULT gen_random_uuid()';
        }

        foreach ($vars as $var) {
            if ($var === $identifier) {
                continue;
            }

            $columnType = 'TEXT';
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
            if (strtoupper($columnType) === 'CITEXT') {
                $usesCitext = true;
            }

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

            if (($existingColumnMap[$var]['Null'] ?? 'YES') === 'NO' && empty($existingColumnMap[$var]['Identity'])) {
                $alterStatements[] = 'ALTER COLUMN ' . $this->quote($var) . ' DROP NOT NULL';
            }

            if ($this->should_modify_column($existingType, $expectedType)) {
                $usingClause = $this->build_using_clause($var, $columnType, $existingType);
                $alterStatements[] = "ALTER COLUMN " . $this->quote($var) . " TYPE " . $this->build_type_definition($columnType, $maxLength) . " USING " . $usingClause;
            }

            if ($isUnique && !$isUniqueInDb) {
                $alterStatements[] = "ADD CONSTRAINT " . $this->quote($this->unique_name($var)) . " UNIQUE (" . $this->quote($var) . ")";
            } elseif (!$isUnique && $isUniqueInDb) {
                $alterStatements[] = "DROP CONSTRAINT IF EXISTS " . $this->quote($this->unique_name($var));
            }
        }

        foreach ($existingColumnMap as $existingCol => $colData) {
            if ($existingCol === $identifier) {
                continue;
            }
            if (!in_array($existingCol, $vars, true)) {
                $alterStatements[] = "DROP COLUMN IF EXISTS " . $this->quote($existingCol);
            }
        }

        if (empty($alterStatements)) {
            return null;
        }

        $extensionQuery = $usesCitext ? 'CREATE EXTENSION IF NOT EXISTS citext; ' : '';
        return $extensionQuery . "ALTER TABLE " . $this->quote($tableName) . " " . implode(", ", $alterStatements) . ";";
    }

    private function build_using_clause(string $columnName, string $targetType, string $existingType, bool $isNullable=true): string
    {
        $quoted = $this->quote($columnName);
        $targetUpper = strtoupper($targetType);
        $existingLower = strtolower($existingType);

        // Condition 1: From anything's to BOOLEAN
        if ($targetUpper === 'BOOLEAN') {
            $nullFallback = $isNullable ? 'NULL' : 'false';

            if (preg_match('/^(int|integer|smallint|bigint|numeric|decimal|real|double)/', $existingLower)) {
                return "CASE WHEN $quoted IS NULL THEN $nullFallback WHEN $quoted = 0 THEN false ELSE true END";
            }
            if (preg_match('/^(text|citext|varchar|char)/', $existingLower)) {
                return "CASE WHEN $quoted IS NULL THEN $nullFallback WHEN $quoted IN ('0','false','f','no','') THEN false ELSE true END";
            }
            // fallback for another types
            return "CASE WHEN $quoted IS NULL THEN $nullFallback ELSE $quoted::boolean END";
        }

        // Condition 2: From BOOLEAN to others, Conver from t&f to 1&0
        if (preg_match('/^bool/', $existingLower)) {
            $nullFallback = $isNullable ? 'NULL' : '0';
            return "CASE WHEN $quoted IS NULL THEN $nullFallback WHEN $quoted THEN 1 ELSE 0 END";
        }

        // Normal cast for another
        return "$quoted::" . $this->build_type_definition($targetType, null);
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
            $columns = array_values($cols);
            $normalizedIndexes[$indexName] = [
                'columns' => $columns,
                'normalizedColumns' => array_map(fn($column) => $this->normalize_index_expression($this->index_expression((string)$column)), $columns),
            ];
        }

        $query = '';

        foreach ($existingIndexes as $existingName => $existingCols) {
            $existingColsLower = array_map(fn($column) => $this->normalize_index_expression($column), $existingCols);
            $found = false;
            foreach ($normalizedIndexes as $indexData) {
                if ($existingColsLower === $indexData['normalizedColumns']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $query .= "DROP INDEX IF EXISTS " . $this->quote($existingName) . ";\n";
            }
        }

        foreach ($normalizedIndexes as $indexName => $indexData) {
            $columns = $indexData['columns'];
            $columnsNormalized = $indexData['normalizedColumns'];
            $found = false;
            foreach ($existingIndexes as $existingCols) {
                $existingColsNormalized = array_map(fn($column) => $this->normalize_index_expression($column), $existingCols);
                if ($existingColsNormalized === $columnsNormalized) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $cols = implode(',', array_map(fn($column) => $this->index_expression((string)$column), $columns));
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
            'string' => 'TEXT',
            default => 'TEXT',
        };
    }

    private function normalize_type_from_attribute(Type $type, ?string $maxLength): string
    {
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

    private function index_expression(string $column): string
    {
        $quotedColumn = $this->quote($column);
        return self::is_no_case_property($this->calledClass, $column) ? "LOWER($quotedColumn)" : $quotedColumn;
    }

    private function normalize_index_expression(string $expression): string
    {
        return preg_replace('/\s+/', '', strtolower(str_replace('"', '', trim($expression))));
    }

    private function quote(string $identifier): string
    {
        return PDO_SQL::quote($identifier);
    }

    public function __destruct()
    {
        if ($this->changeStructure && $this->table) {
            $indexesQuery = $this->index_definition_query();
            if ($indexesQuery) {
                PDO_SQL::run_exec($indexesQuery);
            }
        }
    }

    static function is_boolean_property($className, $property): bool
    {
        if ($className === '' || !class_exists($className)) {
            return false;
        }
        $reflection = new ReflectionClass($className);
        if (!$reflection->hasProperty($property))
            return false;

        $prop = $reflection->getProperty($property);
        $type = $prop->getType();

        if ($type && $type->getName() == "bool") {
            return true;
        } elseif (!empty($prop->getAttributes(Length::class))) {
            $lengthAttributes = $prop->getAttributes(Length::class);
            $length = $lengthAttributes[0]->newInstance();
            if (strtolower($length->type->value) == "boolean")
                return true;
        }
        return false;
    }

    static function is_no_case_property(string $className, string $property): bool
    {
        if ($className === '' || !class_exists($className)) {
            return false;
        }
        $reflection = new ReflectionClass($className);
        return $reflection->hasProperty($property) && !empty($reflection->getProperty($property)->getAttributes(NoCase::class));
    }

    static function normalize_table_name(string $tableName): string
    {
        $tableName = strtolower($tableName);
        return str_replace("\\", "/", $tableName);
    }

}
