<?php
namespace ORM\PostgreSQL;

use Attribute;

#[Attribute]
class NoColumn {}

#[Attribute]
class Index {}

#[Attribute]
class Unique {}

enum Type: string {
    case BOOL = 'boolean';
    case VARCHAR = 'varchar';
    case TEXT = 'text';
    case MEDIUMTEXT = 'text';
    case LONGTEXT = 'text';
    case INT = 'bigint';
    case INT_SIGNED = 'bigint';
    case BIGINT = 'bigint';
    case BIGINT_SIGNED = 'bigint';
    case FLOAT = 'double precision';
    case DECIMAL = 'decimal';
}

#[Attribute(Attribute::TARGET_PROPERTY)]
class Length {
    public Type $type;
    public ?string $maxLength;
    
    public function __construct(Type $type = Type::VARCHAR, ?int $maxLength = 255, int $decimal = null) {
        $this->type = $type;
        $this->maxLength = $maxLength;
        if ($type === Type::DECIMAL && $maxLength !== null && $decimal !== null) {
            $this->maxLength .= ",$decimal";
        }
    }
}
