<?php

namespace ORM\PostgreSQL;

use ReflectionClass;
use ReflectionProperty;

trait PostgreSQL
{
    static private function this_class_table(): string
    {
        $tableName = get_called_class();
        $tableName = strtolower($tableName);
        return str_replace("\\", "/", $tableName);
    }
    
    public function get_vars($valued = true, $hasColumn = true): array
    {
        $vars = array_filter((array)$this, static fn(string $key): bool => !str_starts_with($key, "\0"), ARRAY_FILTER_USE_KEY);
        
        if ($hasColumn) {
            $reflection = new ReflectionClass(get_class($this));
            foreach ($vars as $key => &$value) {
                if ($reflection->hasProperty($key)) {
                    $prop = $reflection->getProperty($key);
                    if (!empty($prop->getAttributes(NoColumn::class))) {
                        unset($vars[$key]);
                    }
                }
                
                try {
                    $this->normalize_booleans(get_class($this), $key, $value);
                } catch (\ReflectionException $e) {}
            }
        }
        return $vars;
    }
    
    static function properties(): array
    {
        $class = new ReflectionClass(get_called_class());
        $publicProperties = $class->getProperties(ReflectionProperty::IS_PUBLIC);
        $publicProperties = array_filter($publicProperties, fn($v) => empty($v->getAttributes(NoColumn::class)));
        $publicProperties = array_map(fn($v) => $v->name, $publicProperties);
        $staticProperties = $class->getProperties(ReflectionProperty::IS_STATIC);
        $staticProperties = array_map(fn($v) => $v->name, $staticProperties);
        return array_diff($publicProperties, $staticProperties);
    }
    
    static function table(?string $tableName = null): PostgreSQL_Table
    {
        $tableName ??= self::this_class_table();
        $properties = self::properties();
        return new PostgreSQL_Table($tableName, $properties, get_called_class());
    }
    
    public function load($array): bool
    {
        if (is_object($array)) {
            $array = (array)$array;
        }
        if (!is_array($array)) {
            return false;
        }
        $properties = array_keys($this->get_vars(true, false));
        foreach ($array as $item => $value) {
            if (in_array($item, $properties, true)) {
                @$this->{$item} = $value;
            }
        }
        return true;
    }
    
    public function clear(): void
    {
        foreach ($this as $item => $value) {
            @$this->{$item} = null;
        }
    }
    
    static function insert(array $data, string $tableName = null)
    {
        $tableName ??= self::this_class_table();
        $orm = new PostgreSQL_Data($tableName, get_called_class());
        return $orm->insert_array($data);
    }
    
    static function get(...$con): PostgreSQL_Data|array
    {
        if (is_array(@$con[0])) {
            $con = array_merge(...$con);
        }
        $table = self::this_class_table();
        $orm = new PostgreSQL_Data($table, get_called_class());
        return $orm->set_condition($con);
    }
    
    static function count_rows(...$con): PostgreSQL_Data|int
    {
        if (is_array(@$con[0])) {
            $con = array_merge(...$con);
        }
        $table = self::this_class_table();
        $orm = new PostgreSQL_Data($table, get_called_class());
        return $orm->set_condition($con, 'count');
    }
    
    static function last_ID(string $table = null): bool|int
    {
        $table ??= self::this_class_table();
        return PDO_SQL::last_id($table);
    }
    
    public function __construct(...$con)
    {
        if ($con) {
            $this->set(...$con);
        }
    }
    
    public function set(...$con): array|PostgreSQL_Data
    {
        if (count($con) === 1 && is_numeric(@$con[0])) {
            $con = ["ID" => $con[0]];
        }
        return self::get(...$con)->limit(1)->set_object($this);
    }
    
    public function set_byID($ID = 0): PostgreSQL_Data|bool|int
    {
        if (!$ID) {
            if ($this->ID) {
                $ID = $this->ID;
            } else {
                $this->clear();
                return false;
            }
        }
        return self::get(["ID" => $ID])->limit(1)->set_object($this);
    }
    
    public function check(): bool
    {
        return (bool)$this->ID;
    }
    
    public function save(string|null $table = null, bool $newRow = false): bool|int
    {
        $table ??= self::this_class_table();
        $vars = $this->get_vars(false);
        if (@$vars['ID'] && !$newRow) {
            return $this->ID = PDO_SQL::update_row($table, $vars);
        }
        return $this->ID = PDO_SQL::insert_row($table, $vars);
    }
    
    public function delete(string $table = ''): bool
    {
        if (!$table) {
            $table = self::this_class_table();
        }
        $vars = $this->get_vars(false);
        if (@$vars['ID']) {
            return PDO_SQL::delete_row($table, $vars['ID']);
        }
        return false;
    }
    
    public function change($attribute, $value, $ID = null, $table = null): bool
    {
        $reflection = new ReflectionClass(get_class($this));
        if (!$reflection->hasProperty($attribute)) {
            return false;
        }
        
        $table ??= self::this_class_table();
        if (!$ID) {
            $ID = $this->ID;
        }
        
        try {
            $this->normalize_booleans(get_class($this), $attribute, $value);
        } catch (\ReflectionException $e) {}
        
        $this->$attribute = $value;
        return PDO_SQL::update_one_value($table, $ID, $attribute, $value);
    }
    
    public function __call(string $name, array $arguments): bool
    {
        $this->$name = $arguments[0];
        if (in_array($name, self::properties(), true)) {
            return $this->change($name, $this->$name);
        }
        return false;
    }
    
    /**
     * In Postgre boolean columns must save as t or f
     *
     * @param $className
     * @param $property
     * @param $value
     * @return void
     * @throws \ReflectionException
     */
    private function normalize_booleans($className, $property, &$value): void
    {
        if (PostgreSQL_Table::is_boolean_property($className, $property))
            $value = ($value ? "t" : "f");
    }
}
