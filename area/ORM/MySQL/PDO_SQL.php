<?php

namespace ORM\MySQL;

use Exception;
use PDO;
use PDOException;
use PDOStatement;

class PDO_SQL
{
    static ?PDO $connection = null;
    static ?PDO $connectionNoBuffer = null;
    static string $error = "";
    static array $config = [];
    
    static array $BUFFER_CONFIG = [
        /**
         * Enable multi statements:
         * Allow run two or more SQL statements async in a connection.
         */
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        
        /**
         * Persistent connections:
         * By default, PDO uses non-persistent connections.
         * So we set `PERSISTENT = true`. Persistent connection use when we have infinitive loop.
         * That prevent connection closing when app doesn't send any query for a long time.
         * You can show current timeout value by this SQL CODE:
         * SHOW VARIABLES LIKE 'wait_timeout';
         * SHOW VARIABLES LIKE 'interactive_timeout';
         */
        PDO::ATTR_PERSISTENT => true,
    ];
    
    static array $NO_BUFFER_CONFIG  = [
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
    ];
    
    ### Connection methods
    
    static function initial($config): true
    {
        if (empty(@$config['dbname'])){
            self::$error .= "Database name is not set!".EOL;
            // But don't throw any exception because may create db now
        }
        if (!in_array(@$config['mode'], PDO::getAvailableDrivers())){
            self::$error = "Database mode is not supported.".EOL;
            throw new Exception(self::$error);
        }
        if (empty(@$config['hostname']) || empty(@$config['username']) || !isset($config['password'])){
            self::$error = "Database config is not set!".EOL;
            throw new Exception(self::$error);
        }
        self::$config = $config;
        return true;
    }

    /**
     * Create a database without requiring the configured database to exist.
     */
    public static function create_database(string $database, string $charset = 'utf8mb4', string $collate = 'utf8mb4_unicode_ci'): bool {
        $config = self::$config;
        $hostname = $config['hostname'] ?? '';
        $port = $config['port'] ?? 3306;
        $username = $config['username'] ?? '';
        $password = $config['password'] ?? '';

        try {
            $dsn = "mysql:host=$hostname;port=$port;charset=$charset";
            $connection = new PDO($dsn, $username, $password, self::$BUFFER_CONFIG);
            $query = 'CREATE DATABASE IF NOT EXISTS ' . self::quote_identifier($database)
                . ' CHARACTER SET ' . self::quote_identifier($charset)
                . ' COLLATE ' . self::quote_identifier($collate);
            $connection->exec($query);
            return true;
        } catch (PDOException|Exception $e) {
            self::$error .= $e->getMessage() . EOL;
            return false;
        }
    }

    private static function quote_identifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
    
    public function __construct($noBuffer = false)
    {
        // Was connected
        if (!$noBuffer && self::$connection instanceof PDO)
            return self::$connection;
        
        if ($noBuffer && self::$connectionNoBuffer instanceof PDO)
            return self::$connectionNoBuffer;
        
        $config = self::$config;
        
        $mode = $config['mode'];
        $hostname = $config['hostname'];
        $port = $config['port'] ?: 3306;
        $username = $config['username'];
        $password = $config['password'];
        $dbname = $config['dbname'] ?? "";
        $charset = $config['charset'] ?? 'utf8mb4';
        $timeout = $config['timeout'] ?? null;
        
        try {
            $DSN = "mysql:host=$hostname;port=$port;dbname=$dbname;charset=$charset";
            if ($noBuffer){
                self::$connectionNoBuffer = new PDO($DSN, $username, $password, self::$NO_BUFFER_CONFIG);
                self::$connectionNoBuffer->exec('SET NAMES utf8mb4');
                if ($timeout)
                    self::$connectionNoBuffer->exec('SET session wait_timeout='.$timeout);
                return self::$connectionNoBuffer;
            }else{ // Default
                self::$connection = new PDO($DSN, $username, $password, self::$BUFFER_CONFIG);
                self::$connection->exec('SET NAMES utf8mb4');
                if ($timeout)
                    self::$connection->exec('SET session wait_timeout='.$timeout);
                return self::$connection;
            }
        } catch (PDOException|Exception $e) {
            echo 'We have ERROR in connection!';
            self::$error = $e->getMessage();
            if (function_exists('database_connection_refused')) {
                database_connection_refused();
                return false;
            } else {
                die("Database connection refused");
            }
        }
    }
    
    static function reconnect(): PDO_SQL|bool
    {
        if (!self::check_connection()){
            self::$error = "Reconnecting to database...".EOL;
            return new self();
        }
        return true;
    }

    static function check_connection(): ?bool
    {
        new self; if (!self::$connection) return null;
        
        try {
            self::$connection->query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            self::$error .= $e->getMessage().EOL;
        }
        return false;
    }
    
    
    ### Query methods
    
    public static function run_exec(string $query, array $params = []): false|int
    {
        new self; if (!self::$connection) return false;
        
        try {
            return self::$connection->exec($query);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }
    
    public static function run_query(string $query): false|PDO_Fetch|null
    {
        new self; if (!self::$connection) return null;
        
        try {
            $stmt = self::$connection->query($query);
            return new PDO_Fetch($stmt);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }
    
    ### Table methods
    
    public static function table_exist($tableName): ?bool
    {
        new self; if (!self::$connection) return null;
        try {
            @self::$connection->query("SELECT 1 FROM `$tableName` LIMIT 1;")->fetchAll();
            return true;
        } catch (PDOException $e) {
            self::$error .= "Table $tableName not exist".EOL;
            return false;
        }
    }
    
    public static function table_delete($tableName): bool
    {
        return self::run_exec("DROP TABLE IF EXISTS `$tableName`; ")!==false;
    }
    
    public static function table_rename($oldName, $newName): bool
    {
        return self::run_exec("ALTER TABLE `$oldName` RENAME TO `$newName`;")!==false;
    }
    
    public static function table_describe($tableName): ?array
    {
        return self::run_query("DESCRIBE `$tableName`;")->data_to_array();
    }
    
    /** Change a table only while it is empty and locked against writes. */
    public static function change_empty_table(string $table, callable $buildQuery): bool
    {
        new self;
        if (!self::$connection) return false;
        if (self::$connection->inTransaction()) {
            throw new \LogicException('Cannot change a primary key inside an active MySQL transaction.');
        }
        $quoted = self::quote_identifier($table);
        $locked = false;
        try {
            if (self::$connection->exec('LOCK TABLES ' . $quoted . ' WRITE') === false) return false;
            $locked = true;
            $statement = self::$connection->query('SELECT 1 FROM ' . $quoted . ' LIMIT 1');
            if ($statement === false) return false;
            $hasRows = $statement->fetchColumn() !== false;
            $statement->closeCursor();
            if ($hasRows) throw new \LogicException('Cannot change the primary key because the table contains data.');
            $query = $buildQuery();
            return $query === null || self::$connection->exec($query) !== false;
        } catch (PDOException $e) {
            self::$error .= $e->getMessage() . EOL;
            return false;
        } finally {
            if ($locked) self::$connection->exec('UNLOCK TABLES');
        }
    }

    public static function table_columns($tableName): array
    {
        $stms = self::run_query("SHOW COLUMNS FROM `$tableName`;");
        if (!$stms) return [];
        return $stms->data_to_array();
    }
    
    public static function table_indexes($tableName): array
    {
        $stmt = self::run_query("SHOW INDEX FROM `$tableName`;");
        if (!$stmt) return [];
        return $stmt->data_to_array();
    }
    
    ### SELECT methods
    
    public static function all_data($table): false|PDO_Fetch|null
    {
        new self; if (!self::$connection) return null;
        try {
            $stmt = self::$connection->query("SELECT * FROM `$table`");
            return new PDO_Fetch($stmt);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage().EOL;
        }
        return false;
    }

    public static function search_byID($table,$ID): false|PDO_Fetch|null
    {
        new self; if (!self::$connection) return null;
        return self::run_query("SELECT * FROM `$table` WHERE ID=`$ID`");
    }
    
    public static function last_ID(string $table, string $identifier = 'ID')
    {
        new self; if (!self::$connection) return null;
        try {
            $column = str_replace('`', '``', $identifier);
            $statement = self::run_query("SELECT MAX(`$column`) AS max_id FROM `$table`");
            $value = $statement->data_to_array_num()[0]['max_id'] ?? null;
            return is_numeric($value) ? (int)$value : ($value ?? false);
        } catch(PDOException $e) {
            self::$error .= 'We have ERROR in count MAX data!' .EOL;
            self::$error .= $e->getMessage() .EOL;
            return false;
        }
    }

    public static function search($table, $column, $value): false|PDO_Fetch|null
    {
        new self; if (!self::$connection) return null;
        try {
            $stmt = self::$connection->prepare("SELECT * FROM `$table` WHERE `$column`=:value");
            $stmt->bindValue(':value', $value);
            $stmt->execute();
            return new PDO_Fetch($stmt);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }
    
    public static function search_where($query, $executes=[]): false|PDO_Fetch|null
    {
        new self; if (!self::$connection) return null;
        try {
            $stmt = self::$connection->prepare($query);
            $stmt->execute($executes);
            return new PDO_Fetch($stmt);
        } catch (PDOException $e) {
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }
    
    
    ### SELECT No Buffer methods
    public static function search_where_no_buffer($query, $executes=[]): false|PDO_Fetch|null
    {
        new self(true); if (!self::$connectionNoBuffer) return null;
        
        try {
            $connectionID = self::$connectionNoBuffer->query("SELECT CONNECTION_ID()")->fetchColumn();
            $stmt = self::$connectionNoBuffer->prepare($query);
            $stmt->execute($executes);
            
            $fetch = new Pdo_Fetch($stmt);
            $fetch->threadID_NoBuffer = $connectionID;
            $fetch->stmt_NoBuffer = &$stmt;
            
            return $fetch;
        } catch (PDOException $e) {
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }
    
    public static function kill_no_buffer(PDO_Fetch $fetch): true
    {
        PDO_SQL::run_query("KILL $fetch->threadID_NoBuffer");
        $fetch->stmt_NoBuffer->closeCursor();
        self::$connectionNoBuffer = null;
        return true;
    }
    
    ### INSERT, UPDATE, DELETE methods
    
    public static function insert_multiple_data($query, $executes=[]): ?bool
    {
        new self; if (!self::$connection) return null;
        try {
            $statement = self::$connection->prepare($query);
            $statement->execute($executes);
            if (empty($statement->errorInfo()))
                return false;
            return true;
        } catch(PDOException $e) {
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }
    
    
    public static function insert_row($table,$vars, string $identifier = 'ID'): false|int|string|null
    {
        new self; if (!self::$connection) return null;

        $suppliedID = $vars[$identifier] ?? null;
        try {
            self::$connection->beginTransaction();
            if (($suppliedID === null || $suppliedID === '') && strcasecmp($identifier, 'uuid') === 0) {
                $uuidStatement = self::$connection->query('SELECT UUID()');
                if ($uuidStatement === false) throw new \RuntimeException('Could not generate UUID.');
                $suppliedID = $uuidStatement->fetchColumn();
                $uuidStatement->closeCursor();
                if (!is_string($suppliedID) || !preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/i', $suppliedID)) {
                    throw new \RuntimeException('Database returned an invalid UUID.');
                }
                $vars[$identifier] = $suppliedID;
            } elseif ($suppliedID === null || $suppliedID === '' || $suppliedID === 0) {
                unset($vars[$identifier]);
            }

            $columns = [];
            $valuesArray = [];
            foreach ($vars as $item => $value) {
                $columns[] = self::quote_identifier((string)$item);
                $valuesArray[] = $value;
            }
            $sql = 'INSERT INTO ' . self::quote_identifier($table) . ' (' . implode(',', $columns) . ') VALUES ('
                . implode(',', array_fill(0, count($columns), '?')) . ')';
            $statement = self::$connection->prepare($sql);
            if ($statement === false || !$statement->execute($valuesArray)) {
                throw new \RuntimeException('Could not insert row.');
            }
            $insertedID = ($suppliedID !== null && $suppliedID !== '' && $suppliedID !== 0)
                ? $suppliedID : (int)self::$connection->lastInsertId();
            self::$connection->commit();
            return $insertedID;
        } catch (PDOException|\RuntimeException $e) {
            if (self::$connection->inTransaction()) self::$connection->rollback();
            echo 'We have ERROR in set data!'.EOL;
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }

    public static function update_row($table,$vars, string $identifier = 'ID'): bool|int|string|null
    {
        new self; if (!self::$connection) return null;
        
        $string="";
        $ID = $vars[$identifier];
        unset($vars[$identifier]);
        $valuesArray = [];
        foreach ($vars as $item=>$value){
            $string .= "`".$item."` = ?,";
            $valuesArray[] = $value;
        }
        $string = rtrim($string,",");
        if ($string === "") return $ID;
        $valuesArray[] = $ID;
        $key = str_replace('`', '``', $identifier);
        try {
            $statement = self::$connection->prepare("UPDATE `$table` SET $string WHERE `$key` = ?");
            $statement->execute($valuesArray);
            return $ID;
        } catch(PDOException $e) {
            echo 'We have ERROR in set data!'.EOL;
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }
    
    public static function delete_row($table,$ID, string $identifier = 'ID'): ?bool
    {
        new self; if (!self::$connection) return null;
        
        try {
            $key = str_replace('`', '``', $identifier);
            $statement = self::$connection->prepare("DELETE FROM `$table` WHERE `$key` = ?");
            $statement->execute([$ID]);
            return true;
        } catch(PDOException $e) {
            echo 'We have ERROR on delete data!'.EOL;
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }
    
    public static function update_one_value($table,$ID,$attribute,$value, string $identifier = 'ID'): ?bool
    {
        new self; if (!self::$connection) return null;
        try {
            $key = str_replace('`', '``', $identifier);
            $statement = self::$connection->prepare("UPDATE `$table` SET `$attribute` = :value WHERE `$key` = :id");
            $statement->bindValue(':value', $value);
            $statement->bindValue(':id', $ID);
            $statement->execute();
            return true;
        } catch(PDOException $e) {
            echo 'We have ERROR in update data!' .EOL;
            self::$error .= $e->getMessage().EOL;
            return false;
        }
    }
    
}
