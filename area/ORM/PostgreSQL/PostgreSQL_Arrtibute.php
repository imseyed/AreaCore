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
    case SMALLINT = 'smallint';
    case INT = 'integer';
    case BIGINT = 'bigint';
    case REAL = 'real';
    case FLOAT = 'double precision';
    case DECIMAL = 'numeric';
    case VARCHAR = 'varchar';
    case CHAR = 'char';
    case TEXT = 'text';
    case DATE = 'date';
    case TIME = 'time';
    case TIMESTAMP = 'timestamp';
    case TIMESTAMPTZ = 'timestamptz';
    case JSON = 'json';
    case JSONB = 'jsonb';
    case UUID = 'uuid';
    case BYTEA = 'bytea';
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
