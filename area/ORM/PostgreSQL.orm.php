<?php

require_once "PostgreSQL/PDO_SQL.php";
require_once "PostgreSQL/PDO_Fetch.php";
require_once "PostgreSQL/PostgreSQL.php";
require_once "PostgreSQL/PostgreSQL_Table.php";
require_once "PostgreSQL/PostgreSQL_Data.php";
require_once "PostgreSQL/PostgreSQL_DataJson.php";
require_once "PostgreSQL/PostgreSQL_Arrtibute.php";

global $DB_Config;
if (strtolower((string)($DB_Config['mode'] ?? '')) === 'pgsql'
    || strtolower((string)($DB_Config['mode'] ?? '')) === 'postgresql'
    || strtolower((string)($DB_Config['mode'] ?? '')) === 'postgres') {
    \ORM\PostgreSQL\PDO_SQL::initial($DB_Config);
}
