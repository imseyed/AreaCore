<?php

namespace ORM\PostgreSQL;

use Exception;
use PDO;
use PDOException;

class PDO_SQL
{
    static ?PDO $connection = null;
    static ?PDO $connectionNoBuffer = null;
    static string $error = "";
    static array $config = [];
    
    static array $BUFFER_CONFIG = [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];
    
    static array $NO_BUFFER_CONFIG = [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ];
    
    static function initial($config): true
    {
        if (empty(@$config['dbname'])) {
            self::$error .= "Database name is not set!" . EOL;
        }
        $mode = strtolower((string)(@$config['mode'] ?? ''));
        if (in_array($mode, ['postgresql', 'postgres'], true)) {
            $mode = 'pgsql';
        }
        if ($mode !== 'pgsql' || !in_array('pgsql', PDO::getAvailableDrivers(), true)) {
            self::$error = "PostgreSQL driver is not supported." . EOL;
            throw new Exception(self::$error);
        }
        if (empty(@$config['hostname']) || empty(@$config['username']) || !isset($config['password'])) {
            self::$error = "Database config is not set!" . EOL;
            throw new Exception(self::$error);
        }
        $config['mode'] = 'pgsql';
        self::$config = $config;
        return true;
    }
    
    public static function quote(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }
    
    public static function count_alias(): string
    {
        return 'count_rows';
    }
    
    public function __construct($noBuffer = false)
    {
        if (!$noBuffer && self::$connection instanceof PDO) {
            return self::$connection;
        }
        if ($noBuffer && self::$connectionNoBuffer instanceof PDO) {
            return self::$connectionNoBuffer;
        }
        
        $config = self::$config;
        $hostname = $config['hostname'];
        $port = $config['port'] ?: 5432;
        $username = $config['username'];
        $password = $config['password'];
        $dbname = $config['dbname'] ?? "";
        $timeout = $config['timeout'] ?? null;
        
        try {
            $dsn = "pgsql:host=$hostname;port=$port;dbname=$dbname";
            if ($noBuffer) {
                self::$connectionNoBuffer = new PDO($dsn, $username, $password, self::$NO_BUFFER_CONFIG);
                if ($timeout) {
                    self::$connectionNoBuffer->exec('SET statement_timeout=' . ((int)$timeout * 1000));
                }
                return self::$connectionNoBuffer;
            }
            self::$connection = new PDO($dsn, $username, $password, self::$BUFFER_CONFIG);
            if ($timeout) {
                self::$connection->exec('SET statement_timeout=' . ((int)$timeout * 1000));
            }
            return self::$connection;
        } catch (PDOException|Exception $e) {
            echo 'We have ERROR in connection!';
            self::$error = $e->getMessage();
            if (function_exists('database_connection_refused')) {
                database_connection_refused();
                return false;
            }
            die("Database connection refused");
        }
    }
    
    static function reconnect(): PDO_SQL|bool
    {
        if (!self::check_connection()) {
            self::$error = "Reconnecting to database..." . EOL;
            return new self();
        }
        return true;
    }
    
    static function check_connection(): ?bool
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        
        try {
            self::$connection->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            self::$error .= $e->getMessage() . EOL;
        }
        return false;
    }
    
    public static function run_exec(string $query, array $params = []): false|int
    {
        new self;
        if (!self::$connection) {
            return false;
        }
        
        try {
            if ($params) {
                $stmt = self::$connection->prepare($query);
                $stmt->execute($params);
                return $stmt->rowCount();
            }
            return self::$connection->exec($query);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function run_query(string $query): false|PDO_Fetch|null
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        
        try {
            $stmt = self::$connection->query($query);
            return new PDO_Fetch($stmt);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function table_exist($tableName): ?bool
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        try {
            $stmt = self::$connection->prepare("SELECT to_regclass(:table_name)");
            $stmt->execute([':table_name' => $tableName]);
            return $stmt->fetchColumn() !== null;
        } catch (PDOException $e) {
            self::$error .= "Table $tableName not exist" . EOL;
            return false;
        }
    }
    
    public static function table_delete($tableName): bool
    {
        return self::run_exec("DROP TABLE IF EXISTS " . self::quote($tableName) . ";") !== false;
    }
    
    public static function table_rename($oldName, $newName): bool
    {
        return self::run_exec("ALTER TABLE " . self::quote($oldName) . " RENAME TO " . self::quote($newName) . ";") !== false;
    }
    
    public static function table_describe($tableName): ?array
    {
        return self::table_columns($tableName);
    }
    
    public static function table_columns($tableName): array
    {
        $query = "
            SELECT
                a.attname AS \"Field\",
                pg_catalog.format_type(a.atttypid, a.atttypmod) AS \"Type\",
                CASE WHEN EXISTS (
                    SELECT 1
                    FROM pg_constraint con
                    JOIN pg_attribute ua ON ua.attrelid = con.conrelid
                    WHERE con.contype = 'u'
                      AND con.conrelid = cls.oid
                      AND ua.attname = a.attname
                      AND ua.attnum = ANY(con.conkey)
                ) THEN 'UNI' ELSE '' END AS \"Key\"
            FROM pg_attribute a
            JOIN pg_class cls ON cls.oid = a.attrelid
            JOIN pg_namespace ns ON ns.oid = cls.relnamespace
            WHERE cls.relname = :table_name
              AND ns.nspname = current_schema()
              AND a.attnum > 0
              AND NOT a.attisdropped
            ORDER BY a.attnum
        ";
        $stms = self::search_where($query, [':table_name' => $tableName]);
        if (!$stms) {
            return [];
        }
        return $stms->data_to_array();
    }
    
    public static function table_indexes($tableName): array
    {
        $query = "
            SELECT
                CASE WHEN ix.indisunique THEN 0 ELSE 1 END AS \"Non_unique\",
                i.relname AS \"Key_name\",
                ord.pos AS \"Seq_in_index\",
                a.attname AS \"Column_name\"
            FROM pg_class t
            JOIN pg_namespace ns ON ns.oid = t.relnamespace
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN LATERAL unnest(ix.indkey) WITH ORDINALITY ord(attnum, pos) ON true
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ord.attnum
            WHERE t.relname = :table_name
              AND ns.nspname = current_schema()
              AND ix.indisprimary = false
            ORDER BY i.relname, ord.pos
        ";
        $stmt = self::search_where($query, [':table_name' => $tableName]);
        if (!$stmt) {
            return [];
        }
        return $stmt->data_to_array();
    }
    
    public static function all_data($table): false|PDO_Fetch|null
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        try {
            $stmt = self::$connection->query("SELECT * FROM " . self::quote($table));
            return new PDO_Fetch($stmt);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage() . EOL;
        }
        return false;
    }
    
    public static function search_byID($table, $ID): false|PDO_Fetch|null
    {
        $qTable = self::quote($table);
        $qID = self::quote("ID");
        return self::search_where("SELECT * FROM $qTable WHERE $qID = ?", [$ID]);
    }
    
    public static function last_ID(string $table)
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        try {
            $alias = self::count_alias();
            $qTable = self::quote($table);
            $qID = self::quote("ID");
            $statement = self::run_query("SELECT MAX($qID) AS \"$alias\" FROM $qTable");
            return (int)($statement?->data_to_array_num()[0][$alias] ?? 0);
        } catch (PDOException $e) {
            self::$error .= 'We have ERROR in count MAX data!' . EOL;
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function search($table, $column, $value): false|PDO_Fetch|null
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        try {
            $qTable = self::quote($table);
            $qColumn = self::quote($column);
            $stmt = self::$connection->prepare("SELECT * FROM $qTable WHERE $qColumn = :value");
            $stmt->bindValue(':value', $value);
            $stmt->execute();
            return new PDO_Fetch($stmt);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function search_where($query, $executes = []): false|PDO_Fetch|null
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        try {
            $stmt = self::$connection->prepare($query);
            $stmt->execute($executes);
            return new PDO_Fetch($stmt);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function search_where_no_buffer($query, $executes = []): false|PDO_Fetch|null
    {
        new self(true);
        if (!self::$connectionNoBuffer) {
            return null;
        }
        
        try {
            $stmt = self::$connectionNoBuffer->prepare($query);
            $stmt->execute($executes);
            $fetch = new PDO_Fetch($stmt);
            $fetch->stmt_NoBuffer = &$stmt;
            return $fetch;
        } catch (PDOException $e) {
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function kill_no_buffer(PDO_Fetch $fetch): true
    {
        if ($fetch->stmt_NoBuffer) {
            $fetch->stmt_NoBuffer->closeCursor();
        }
        self::$connectionNoBuffer = null;
        return true;
    }
    
    public static function insert_multiple_data($query, $executes = []): ?bool
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        try {
            $statement = self::$connection->prepare($query);
            $statement->execute($executes);
            if (empty($statement->errorInfo())) {
                return false;
            }
            return true;
        } catch (PDOException $e) {
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function insert_row($table, $vars): false|int|null
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        
        if (array_key_exists('ID', $vars)) {
            if ($vars['ID'] === null || $vars['ID'] === '' || (is_numeric($vars['ID']) && (int)$vars['ID'] === 0)) {
                unset($vars['ID']);
            }
        }
        
        $columns = "";
        $values = "";
        $valuesArray = [];
        foreach ($vars as $item => $value) {
            $columns .= self::quote((string)$item) . ",";
            $values .= "?,";
            $valuesArray[] = $value;
        }
        $columns = rtrim($columns, ",");
        $values = rtrim($values, ",");
        try {
            self::$connection->beginTransaction();
            $qTable = self::quote($table);
            $qID = self::quote("ID");
            if ($columns === "" || $values === "") {
                $statement = self::$connection->prepare("INSERT INTO $qTable DEFAULT VALUES RETURNING $qID;");
                $statement->execute();
            } else {
                $statement = self::$connection->prepare("INSERT INTO $qTable($columns) VALUES ($values) RETURNING $qID;");
                $statement->execute($valuesArray);
            }
            $insertedID = $statement->fetchColumn();
            self::$connection->commit();
            return (int)$insertedID;
        } catch (PDOException $e) {
            if (self::$connection->inTransaction()) {
                self::$connection->rollback();
            }
            echo 'We have ERROR in set data!' . EOL;
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function update_row($table, $vars): bool|int|null
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        
        $string = "";
        $ID = $vars['ID'];
        unset($vars['ID']);
        $valuesArray = [];
        foreach ($vars as $item => $value) {
            $string .= self::quote((string)$item) . " = ?,";
            $valuesArray[] = $value;
        }
        $string = rtrim($string, ",");
        $valuesArray[] = $ID;
        try {
            $qTable = self::quote($table);
            $qID = self::quote("ID");
            $statement = self::$connection->prepare("UPDATE $qTable SET $string WHERE $qID = ?");
            $statement->execute($valuesArray);
            return (int)$ID;
        } catch (PDOException $e) {
            echo 'We have ERROR in set data!' . EOL;
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function delete_row($table, $ID): ?bool
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        
        try {
            $qTable = self::quote($table);
            $qID = self::quote("ID");
            $statement = self::$connection->prepare("DELETE FROM $qTable WHERE $qID = ?");
            $statement->execute([$ID]);
            return true;
        } catch (PDOException $e) {
            echo 'We have ERROR on delete data!' . EOL;
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
    
    public static function update_one_value($table, $ID, $attribute, $value): ?bool
    {
        new self;
        if (!self::$connection) {
            return null;
        }
        try {
            $qTable = self::quote($table);
            $qAttr = self::quote($attribute);
            $qID = self::quote("ID");
            $statement = self::$connection->prepare("UPDATE $qTable SET $qAttr = :value WHERE $qID = :id");
            $statement->bindValue(':value', $value);
            $statement->bindValue(':id', $ID);
            $statement->execute();
            return true;
        } catch (PDOException $e) {
            echo 'We have ERROR in update data!' . EOL;
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }
}
