<?php

namespace ORM\MySQL;

use ReflectionClass;
use ReflectionProperty;

trait MySQL
{
    static private function this_class_table(): string
    {
        $tableName = get_called_class(); // OR use get_class($this) if its a object
        $tableName = strtolower($tableName);
        return str_replace("\\", "/", $tableName); // If using name space replace \ by / in table name
    }
    
    public function get_vars($valued=true, $hasColumn=true): array
    {
        $vars = array_filter((array) $this, static fn(string $key): bool => !str_starts_with($key, "\0"), ARRAY_FILTER_USE_KEY);
        
        $reflection = new ReflectionClass(get_class($this));
        foreach ($vars as $key => &$value) {
            // May some properties hasn't column in table
            if ($hasColumn) {
                // Check for #[NoColumn] attribute on the property and remove it from vars
                if ($reflection->hasProperty($key)) {
                    $prop = $reflection->getProperty($key);
                    // If the property has NoColumn attribute, remove it from export
                    if (!empty($prop->getAttributes(NoColumn::class))) {
                        unset($vars[$key]);
                        continue;
                    }
                }
            }
            
            // In MySQL boolean columns must save as int, so must check them
            if (is_bool($value)) {
                if ($reflection->hasProperty($key)) {
                    $prop = $reflection->getProperty($key);
                    $type = $prop->getType();
                    if ($type && $type->getName() == "bool")
                        $value = (int) $value;
                }
            }
        }
        
        return $vars;
        // return ((new ReflectionObject($this))->getProperties(ReflectionProperty::IS_PUBLIC));
    }
    
    static function properties():array
    {
        $class = new ReflectionClass(get_called_class());
        $publicProperties = $class->getProperties(ReflectionProperty::IS_PUBLIC);
        // Doesn't need create column   #[NoColumn]
        $publicProperties = array_filter($publicProperties, fn($v)=>empty($v->getAttributes(NoColumn::class)));
        $publicProperties = array_map(fn($v)=>$v->name,$publicProperties);
        $staticProperties = $class->getProperties(ReflectionProperty::IS_STATIC);
        $staticProperties = array_map(fn($v)=>$v->name,$staticProperties);
        return array_diff($publicProperties, $staticProperties);
    }
    
    /** Return the explicitly marked identifier, or the first matching conventional name. */
    public static function identifier_column(): string
    {
        $reflection = new ReflectionClass(get_called_class());
        $properties = self::properties();
        $marked = [];
        foreach ($properties as $name) {
            if ($reflection->getProperty($name)->getAttributes(Identifier::class)) {
                $marked[] = $name;
            }
        }
        if (count($marked) > 1) {
            throw new \LogicException('Only one property can be marked as Identifier.');
        }
        if ($marked) return $marked[0];
        foreach (['ID', 'Id', 'id', 'Uuid', 'uuid', 'UUID'] as $name) {
            if (in_array($name, $properties, true)) return $name;
        }
        return 'ID';
    }

    static function table(?string $tableName = null): MySQL_Table
    {
        $tableName ??= self::this_class_table();
        $properties = self::properties();
        return new MySQL_Table($tableName, $properties, get_called_class());
    }
    
    public function load($array): bool
    {
        if(is_object($array))
            $array = (array)$array;
        if (!is_array($array))
            return false;
        $properties = array_keys($this->get_vars(true, false));
        foreach ($array as $item=>$value){
            if (in_array($item, $properties))
                @$this->{$item}=$value;
        }
        return true;
    }
    
    public function clear():void
    {
        foreach ($this as $item=>$value)
            @$this->{$item} = null;
    }
    
    static function insert(array $data, ?string $tableName = null)
    {
        $tableName ??= self::this_class_table();
        $orm = new MySQL_Data($tableName);
        return $orm->insert_array($data);
    }
    
    static function get(...$con): MySQL_Data|array
    {
        if (is_array(@$con[0]))
            $con = array_merge(...$con);
        $table = self::this_class_table();
        $orm = new MySQL_Data($table, get_called_class());
        return $orm->set_condition($con);
    }
    
    static function count_rows(...$con): MySQL_Data|int
    {
        if (is_array(@$con[0]))
            $con = array_merge(...$con);
        $table = self::this_class_table();
        $orm = new MySQL_Data($table, get_called_class());
        return $orm->set_condition($con, 'count');
    }
    
    static function last_ID(?string $table = null): bool|int|string
    {
        $table ??= self::this_class_table();
        return PDO_SQL::last_id($table, self::identifier_column());
    }
    
    public function __construct(...$con)
    {
        if ($con)
            $this->set(...$con);
    }
    
    public function set(...$con): array|MySQL_Data
    {
        if (count($con) === 1 && array_key_exists(0, $con) && (is_int($con[0]) || is_string($con[0]))) {
            $con = [self::identifier_column() => $con[0]];
        }
        return self::get(...$con)->limit(1)->set_object($this);
    }
    
    public function set_byID($ID = 0): MySQL_Data|bool|int
    {
        $identifier = self::identifier_column();
        if (!$ID) {
            $ID = $this->{$identifier} ?? null;
            if (!$ID) { $this->clear(); return false; }
        }
        return self::get([$identifier => $ID])->limit(1)->set_object($this);
    }

    public function check(): bool
    {
        $identifier = self::identifier_column();
        return (bool)($this->{$identifier} ?? null);
    }

    public function save(?string $table = null, bool $newRow = false): bool|int|string
    {
        $table ??= self::this_class_table();
        $identifier = self::identifier_column();
        $vars = $this->get_vars(false);
        if (!empty($vars[$identifier]) && !$newRow) {
            return PDO_SQL::update_row($table, $vars, $identifier) ?? false;
        }
        $result = PDO_SQL::insert_row($table, $vars, $identifier);
        if ($result === false || $result === null) return false;
        $this->{$identifier} = $result;
        return $result;
    }

    public function delete(string $table = ''): bool
    {
        if (!$table) $table = self::this_class_table();
        $identifier = self::identifier_column();
        $value = $this->{$identifier} ?? null;
        return $value ? (PDO_SQL::delete_row($table, $value, $identifier) ?? false) : false;
    }

    public function change($attribute, $value, $ID = null, $table = null): bool
    {
        $reflection = new ReflectionClass(get_class($this));
        if (!$reflection->hasProperty($attribute)) return false;
        $table ??= self::this_class_table();
        $identifier = self::identifier_column();
        $ID ??= $this->{$identifier} ?? null;
        $this->$attribute = $value;
        if (is_bool($value)) $value = (int)$value;
        return PDO_SQL::update_one_value($table, $ID, $attribute, $value, $identifier) ?? false;
    }

    public function __call(string $name, array $arguments):bool
    {
        $this->$name = $arguments[0];
        if (in_array($name, self::properties()))
            return $this->change($name, $this->$name);
        return false;
    }
    
}