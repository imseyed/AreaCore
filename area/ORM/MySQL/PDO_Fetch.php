<?php

namespace ORM\MySQL;

use Exception;
use PDO;
use PDOException;
use PDOStatement;

class PDO_Fetch
{
    public ?PDOStatement $stmt = null;
    public ?PDOStatement $stmt_NoBuffer = null;
    public $threadID_NoBuffer = null;
    
    public function __construct(PDOStatement $stmt)
    {
        $this->stmt = $stmt;
    }
    
    public function data_to_array(): ?array
    {
        if (is_null($this->stmt)) return null;
        return $this->stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function column_to_array(): array
    {
        if (is_null($this->stmt)) return [];
        return $this->stmt->fetchColumn(PDO::FETCH_NUM);
    }
    
    public function data_to_array_num(): array
    {
        if (is_null($this->stmt)) return [];
        return $this->stmt->fetchAll();
    }
    
    public function row_to_array(): mixed
    {
        if (is_null($this->stmt)) return [];
        return $this->stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function row_to_array_numeral(): mixed
    {
        if (is_null($this->stmt)) return [];
        return $this->stmt->fetch();
    }
    
    public function row_to_object($className = \stdClass::class): mixed
    {
        return $this->stmt->fetchObject($className);
    }
    
    
}