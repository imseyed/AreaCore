<?php
namespace ORM\MySQL;

use Attribute;

#[Attribute]
class NoColumn {}

#[Attribute]
class Index {}

#[Attribute]
class Unique {}

enum Type: string {
    case BOOL = 'tinyint(1)';
    case VARCHAR = 'varchar';
    case TEXT = 'text';
    case MEDIUMTEXT = 'mediumtext';
    case LONGTEXT = 'longtext';
    case INT = 'int unsigned';
    case INT_SIGNED = 'int signed';
    case BIGINT = 'bigint unsigned';
    case BIGINT_SIGNED = 'bigint signed';
    case FLOAT = 'float';
    case DECIMAL = 'decimal'; // need real,decimal value like: (18,2)
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Length {
    public Type $type;
    public ?string $maxLength;
    
    public function __construct(Type $type = Type::VARCHAR, ?int $maxLength = 255, int $decimal = null) {
        $this->type = $type;
        $this->maxLength = $maxLength;
        if ($type === Type::DECIMAL && $maxLength !== null && $decimal !== null)
            $this->maxLength .= ",$decimal";
    }
}
