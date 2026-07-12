<?php

namespace ORM\MySQL;


/**
 * @property null|array $do Return all array data
 * @property null|bool|PDO_Fetch|array $ro Return rows one by one
 * @property null|bool|PDO_Fetch|array $noBuffer Return rows one by one directly without buffer. If you want to **break** fetching data, must use `PDO_SQL::kill_no_buffer($fetch)`
 * @property null|int $co Return rows count
 */

class MySQL_Data
{
    private string $table;
    private string $calledClass;
    private array $con=[];
    private array $limit=[];
    private array $columns=[];
    private bool $reverse=false;
    private string $sort="ID";
    private array $notNULL=[];
    private array $isNULL=[];
    private array $executes=[];
    private ?object $objectForSet=null;
    private string $action='';
    private mixed $statement=false; // Made by pdo on fetch
    
    
    ##############
    public function set_condition($conditions, string $action='select', bool $final=false): static|array|int
    {
        $this->action = $action;
        $this->con = $conditions;
        
        if ($final){
            $query = $this->create_query();
            return match ($this->action) {
                'select' => PDO_SQL::search_where($query, $this->executes)->data_to_array(),
                'count' => (int)PDO_SQL::search_where($query, $this->executes)->data_to_array()[0]['COUNT(*)'],
                default => null,
            };
        }
        return $this;
    }
    
    /**
     * @param int $length_start length we want OR start (from zero)
     * @param int $count When use start
     * @return $this
     */
    public function limit(int $length_start=0, int $count=0): static
    {
        if ($count>0){
            $this->limit[0] = $length_start;
            $this->limit[1] = $count;
        } else{
            $this->limit[0] = 0;
            $this->limit[1] = $length_start;
        }
        return $this;
    }
    public function select(...$columns): static // Select some columns
    {
        if (is_array(@$columns[0]))
            $columns = array_merge(...$columns);
        
        if (is_array($columns))
            $this->columns = $columns;
        return $this;
    }
    public function reverse(): static
    {
        $this->reverse = true;
        return $this;
    }
    public function sort($column="ID"): static
    {
        if($column=="ID") // Default value is 'ID'
            return $this;
        if(str_starts_with($column, '#')){ // String sort
            $column = str_replace('#', '', $column);
            $this->sort = "`$column`";
        }else{ // Numeral sort
            $decimalDigitsCount = (str_contains($column, '.') ?strlen($column) - strpos($column, '.')-1 : 0);
            $this->sort = "CAST(`$column` AS DECIMAL(10, ".$decimalDigitsCount."))";
        }
        return $this;
    }
    public function table(string $table): static
    {
        $this->table = $table;
        return $this;
    }
    public function has(...$columns): static
    {
        if (is_array(@$columns[0]))
            $columns = array_merge(...$columns);
        if (is_array($columns))
            $this->notNULL = $columns;
        return $this;
    }
    public function null(...$columns): static
    {
        if (is_array(@$columns[0]))
            $columns = array_merge(...$columns);
        if (is_array($columns))
            $this->isNULL = $columns;
        return $this;
    }
    
    public function create_query():string
    {
        $query = match($this->action){
            'select'=> "SELECT ",
            'count'=> "SELECT COUNT(*) ",
        };
        if ($this->action=='select'){
            if ($this->columns){
                $query .= '`'.implode('`,`', $this->columns).'` ';
            }
            else
                $query .= "* ";
        }
        
        $query .= "FROM `$this->table` ";
        if ($this->con || $this->notNULL || $this->isNULL || $this->limit){
            $query .= "WHERE ";
            if ($this->con){
                $this->main_query($query, $this->con);
            }
            if ($this->notNULL){
                foreach ($this->notNULL as $item)
                    $query .= "`$item` != '' AND ";
            }
            if ($this->isNULL){
                foreach ($this->isNULL as $item)
                    $query .= "(`$item` IS NULL OR `$item` = '') AND ";
            }
            if (str_ends_with($query, "AND "))
                $query = substr($query, 0, -4);
        }
        $query = rtrim($query, "WHERE ")." ";
        if (!$this->reverse)
            $query .= "ORDER BY $this->sort ";
        else
            $query .= "ORDER BY $this->sort DESC ";
        if ($this->limit)
            $query .= "LIMIT ".$this->limit[0].",".$this->limit[1];
        return $query;
    }
    
    private function main_query(&$query, $conditions, $referredCondition=false):void
    {
        switch ($referredCondition){ // When set som conditions on array and must replace by ()
            case false:
                break;
            case "AND":
                $query .= "( ";
                break;
            case "OR":
                if (str_ends_with($query, "AND "))
                    $query = substr($query, 0, -4);
                $query .= "OR ( ";
                break;
        }
        foreach ($conditions as $item => $value) {
            if (is_array($value) && is_numeric($item)) { // () Conditions hasn't key so indexes by numbers 0, 1, 2
                if (array_key_first($value)=="|"){ // Example ['age>'=>18, 'gender'=>"female"], ['|'=>null, 'age<'=>18, 'gender'=>"male", '|gender'=>"female"]
                    array_shift($value);
                    $this->main_query($query, $value, "OR");
                    continue;
                }
                $this->main_query($query, $value, "AND");
                continue;
            }
            $item = str_replace('^','',$item);  // Example ['mode!='=>1, 'mode^!='=>2]
            if (!$value) {
                if (str_contains($item, '~')) {
                    $this->reverse = true;
                    continue;
                }
                if (str_contains($item, '@')) {   // Example @100@2000@
                    $limit = explode('@', $item);
                    array_shift($limit);
                    $this->limit = $limit;
                    continue;
                }
            }
            if ($value === null) {
                if (str_ends_with($item, '!=')) {
                    $item = str_replace('!=', '', $item);
                    $this->notNULL[] = $item;
                } else {
                    $item = str_replace('==', '', $item);
                    $this->isNULL[] = $item;
                }
            } else {
                if (str_starts_with($item, '|')) {  // Or ||
                    $item = str_replace('|', '', $item);
                    if (str_ends_with($query, "AND ")){
                        $query = substr($query,0, -4);
                    }
                    $query .= "OR ";
                }
                
                if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $item)) {
                    if ($value === '')
                        $query .= "(`$item` = '' OR `$item` IS NULL) AND ";
                    elseif(is_array($value) && count($value) > 0) {
                        $query .= "`$item` IN (".implode(", ", (array_fill(0, count($value), "?"))).") AND ";
                        foreach ($value as $v) {
                            $this->executes[] = $v;
                        }
                    }else{
                        $query .= "`$item` = ? AND ";
                        $this->executes[] = $value;
                    }
                } else {
                    // Has condition
                    $decimalDigitsCount = (str_contains($value, '.') ?strlen($value) - strpos($value, '.') - 1:0);
                    
                    if (str_ends_with($item, '==')) {
                        $item = str_replace('=', '', $item);
                        $query .= "`$item` = ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_ends_with($item, '>=#')) {
                        $item = str_replace('>=#', '', $item);
                        $query .= "CAST(`$item` AS DECIMAL(10, ".$decimalDigitsCount.") ) >= ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_ends_with($item, '<=#')) {
                        $item = str_replace('<=#', '', $item);
                        $query .= "CAST(`$item` AS DECIMAL(10, ".$decimalDigitsCount.") ) <= ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_ends_with($item, '>#')) {
                        $item = str_replace('>#', '', $item);
                        $query .= "CAST(`$item` AS DECIMAL(10, ".$decimalDigitsCount.") ) > ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_ends_with($item, '<#')) {
                        $item = str_replace('<#', '', $item);
                        $query .= "CAST(`$item` AS DECIMAL(10, ".$decimalDigitsCount.") ) < ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_ends_with($item, '>=')) {
                        $item = str_replace('>=', '', $item);
                        $query .= "`$item` >= ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_ends_with($item, '<=')) {
                        $item = str_replace('<=', '', $item);
                        $query .= "`$item` <= ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_ends_with($item, '>')) {
                        $item = str_replace('>', '', $item);
                        $query .= "`$item` > ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_ends_with($item, '<')) {
                        $item = str_replace('<', '', $item);
                        $query .= "`$item` < ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_ends_with($item, '!=')) {
                        $item = str_replace('!=', '', $item);
                        $query .= "`$item` <> ? AND ";
                        $this->executes[] = $value;
                    } elseif (str_starts_with($item, '*') || str_ends_with($item, '*')) { // Like search
                        $searchItem = str_replace('*', '', $item);
                        $query .= "`$searchItem` LIKE ? AND ";
                        
                        $value = str_replace( // For search % and _ in text
                            ['\\', '%', '_'],
                            ['\\\\', '\\%', '\\_'],
                            $value
                        );
                        if (str_starts_with($item, '*'))
                            $value = "%$value";
                        if (str_ends_with($item, '*'))
                            $value = "$value%";
                        
                        $this->executes[] = $value;
                    }
                }
            }
        }
        if ($referredCondition){ // May be: false | "OR" | "AND"
            if (str_ends_with($query, "AND "))
                $query = substr($query, 0, -4);
            $query .= " ) AND ";
        }
    }
    
    
    public function insert_array(array $data): bool
    {
        if (count($data)==0)
            return false;
        $keys = array_keys($data[0]);
        if (!is_array($keys))
            return false;
        $keys_number= array_filter($keys, function ($i){return is_numeric($i);});
        
        $query = "INSERT INTO `$this->table` (";
        
        if ($keys_number){  // First row = column  ||  Second row = data
            if (!(count($data))>1)
                return false;
            $keys = array_values($data[0]);
            array_shift($data);
        }else{
            // Columns as keys of all rows
            // Just for document
        }
        $query .= implode(", ", $keys);
        $query = rtrim($query, ", ");
        $query .= ") VALUES";
        foreach ($data as $datum){
            $query .= "(";
            $query .= str_repeat("?,", count($datum));
            $query = rtrim($query, ",");
            $query .= "), ";
            $datum = array_values($datum);
            array_push($this->executes, ...$datum);
        }
        $query = substr_replace($query,  ";", -2, 2);
        return PDO_SQL::insert_multiple_data($query, $this->executes);
    }
    
    public function set_object($object): static
    {
        $this->objectForSet = $object;
        return $this;
    }
    
    public function kill(): void
    {
        PDO_SQL::kill_no_buffer($this->statement);
    }
    ####################################################################
    
    public function __construct(string $table, string $calledClass="")
    {
        $this->table = $table;
        $this->calledClass = $calledClass;
    }
    
    public function __get(string $name) // when use ->do
    {
        // Make and send query
        switch($name){
            case "ro": // ->ro
                if($this->statement===false){
                    $query = $this->create_query();
                    $this->statement = PDO_SQL::search_where($query, $this->executes);
                }
                if($this->statement===null)
                    return [];
                return match ($this->action) {
                    'select' => $this->statement->row_to_object($this->calledClass),
                    'count' => (int)$this->statement->row_to_array()['COUNT(*)'],
                    default => [],
                };
            case "noBuffer": // ->noBuffer
                if($this->statement===false){
                    $query = $this->create_query();
                    $this->statement = PDO_SQL::search_where_no_buffer($query, $this->executes);
                }
                if($this->statement===null)
                    return [];
                return match ($this->action) {
                    'select' => $this->statement->row_to_object($this->calledClass),
                    'count' => (int)$this->statement->row_to_array()['COUNT(*)'],
                    default => [],
                };
            case "co": // ->co
                $this->action = "count";
                $query = $this->create_query();
                $return = (int)PDO_SQL::search_where($query, $this->executes)->data_to_array()[0]['COUNT(*)'];
                $this->action = "select";
                $this->executes = [];
                return $return;
            default: // ->do
                $query = $this->create_query();
                return match ($this->action) {
                    'select' => PDO_SQL::search_where($query, $this->executes)->data_to_array(),
                    'count' => (int)PDO_SQL::search_where($query, $this->executes)->data_to_array()[0]['COUNT(*)'],
                    default => [],
                };
        }
    }
    
    public function __destruct()
    {
        if ($this->objectForSet){
            $query = $this->create_query();
            $data = PDO_SQL::search_where($query, $this->executes)->data_to_array();
            if (count($data)>0)
                return $this->objectForSet->load($data[0]);
            $this->objectForSet->clear();
            return false;
        }
        return null;
    }
    
    public function __toString(): string
    {
        return $this->create_query();
    }
}