<?php

namespace ORM\PostgreSQL;

class PostgreSQL_DataJson
{
    private PostgreSQL_Data $data;
    private string $column;
    private string $boolean = 'AND';
    
    public function __construct(PostgreSQL_Data $data, string $column)
    {
        $this->data = $data;
        $this->column = $column;
    }
    
    public function or(): static
    {
        $this->boolean = 'OR';
        return $this;
    }
    
    public function contains(mixed $value, array|string|null $path = null): PostgreSQL_Data
    {
        $expression = $this->json_expression($path);
        return $this->add("$expression @> ?::jsonb", [$this->json_value($value)]);
    }
    
    public function contained_by(mixed $value, array|string|null $path = null): PostgreSQL_Data
    {
        $expression = $this->json_expression($path);
        return $this->add("$expression <@ ?::jsonb", [$this->json_value($value)]);
    }
    
    public function has_key(string $key, array|string|null $path = null): PostgreSQL_Data
    {
        $expression = $this->json_expression($path);
        return $this->add("jsonb_exists($expression, ?)", [$key]);
    }
    
    public function has_any_key(array $keys, array|string|null $path = null): PostgreSQL_Data
    {
        $expression = $this->json_expression($path);
        return $this->add("jsonb_exists_any($expression, ?::text[])", [$this->text_array($keys)]);
    }
    
    public function has_all_keys(array $keys, array|string|null $path = null): PostgreSQL_Data
    {
        $expression = $this->json_expression($path);
        return $this->add("jsonb_exists_all($expression, ?::text[])", [$this->text_array($keys)]);
    }
    
    public function path_exists(string $jsonPath): PostgreSQL_Data
    {
        return $this->add("jsonb_path_exists(" . $this->quote($this->column) . ", ?::jsonpath)", [$jsonPath]);
    }
    
    public function path_match(string $jsonPath): PostgreSQL_Data
    {
        return $this->add("jsonb_path_match(" . $this->quote($this->column) . ", ?::jsonpath)", [$jsonPath]);
    }
    
    public function path_equals(array|string $path, mixed $value, bool $asText = true): PostgreSQL_Data
    {
        return $this->where_path($path, '=', $value, $asText);
    }
    
    public function path_not_equals(array|string $path, mixed $value, bool $asText = true): PostgreSQL_Data
    {
        return $this->where_path($path, '<>', $value, $asText);
    }
    
    public function path_greater(array|string $path, int|float|string $value, bool $numeric = true): PostgreSQL_Data
    {
        return $this->where_path($path, '>', $value, true, $numeric);
    }
    
    public function path_greater_or_equal(array|string $path, int|float|string $value, bool $numeric = true): PostgreSQL_Data
    {
        return $this->where_path($path, '>=', $value, true, $numeric);
    }
    
    public function path_less(array|string $path, int|float|string $value, bool $numeric = true): PostgreSQL_Data
    {
        return $this->where_path($path, '<', $value, true, $numeric);
    }
    
    public function path_less_or_equal(array|string $path, int|float|string $value, bool $numeric = true): PostgreSQL_Data
    {
        return $this->where_path($path, '<=', $value, true, $numeric);
    }
    
    public function where_path(array|string $path, string $operator, mixed $value, bool $asText = true, bool $numeric = false): PostgreSQL_Data
    {
        $allowed = ['=', '<>', '!=', '>', '>=', '<', '<=', 'LIKE', 'ILIKE'];
        $operator = strtoupper($operator);
        if ($operator === '!=') {
            $operator = '<>';
        }
        if (!in_array($operator, $allowed, true)) {
            $operator = '=';
        }
        
        $expression = $this->path_expression($path, $asText);
        if ($numeric) {
            $expression = "($expression)::numeric";
        }
        
        return $this->add("$expression $operator ?", [$value]);
    }
    
    public function path_is_null(array|string $path): PostgreSQL_Data
    {
        return $this->add($this->path_expression($path, false) . " IS NULL");
    }
    
    public function path_is_not_null(array|string $path): PostgreSQL_Data
    {
        return $this->add($this->path_expression($path, false) . " IS NOT NULL");
    }
    
    private function add(string $condition, array $executes = []): PostgreSQL_Data
    {
        $boolean = $this->boolean;
        $this->boolean = 'AND';
        return $this->data->add_json_condition($condition, $executes, $boolean);
    }
    
    private function json_expression(array|string|null $path = null): string
    {
        if ($path === null || $path === []) {
            return $this->quote($this->column);
        }
        return $this->path_expression($path, false);
    }
    
    private function path_expression(array|string $path, bool $asText): string
    {
        $operator = $asText ? '#>>' : '#>';
        return $this->quote($this->column) . " $operator " . $this->text_array_literal($path);
    }
    
    private function json_value(mixed $value): string
    {
        if (is_string($value)) {
            json_decode($value);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value;
            }
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    private function text_array(array|string $items): string
    {
        $items = (array)$items;
        $escaped = array_map(function ($item) {
            $item = str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$item);
            return '"' . $item . '"';
        }, $items);
        return '{' . implode(',', $escaped) . '}';
    }
    
    private function text_array_literal(array|string $items): string
    {
        $items = (array)$items;
        $quoted = array_map(function ($item) {
            return "'" . str_replace("'", "''", (string)$item) . "'";
        }, $items);
        return "ARRAY[" . implode(',', $quoted) . "]::text[]";
    }
    
    private function quote(string $identifier): string
    {
        return PDO_SQL::quote($identifier);
    }
}
