<?php

require_once "MySQL/PDO_SQL.php";
require_once "MySQL/PDO_Fetch.php";
require_once "MySQL/MySQL.php";
require_once "MySQL/MySQL_Table.php";
require_once "MySQL/MySQL_Data.php";

global $DB_Config;
\ORM\MySQL\PDO_SQL::initial($DB_Config);