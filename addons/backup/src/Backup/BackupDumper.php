<?php

namespace App\Addons\Backup\Backup;

use Exception;
use PDO;
use PDOException;

class BackupDumper
{
    const MAXLINESIZE = 1000000;
    const GZIP = 'Gzip';
    const BZIP2 = 'Bzip2';
    const NONE = 'None';
    const UTF8 = 'utf8';
    const UTF8MB4 = 'utf8mb4';

    public $user;
    public $pass;
    public $dsn;
    public $fileName = 'php://stdout';

    private $tables = [];
    private $views = [];
    private $triggers = [];
    private $dbHandler = null;
    private $dbType = "mysql";
    private $compressManager;
    private $typeAdapter;
    private $dumpSettings = [];
    private $pdoSettings = [];
    private $version;
    private $tableColumnTypes = [];
    private $dbName;
    private $host;

    public function __construct($dsn = '', $user = '', $pass = '', $dumpSettings = [], $pdoSettings = [])
    {
        $dumpSettingsDefault = [
            'include-tables' => [],
            'exclude-tables' => ['cron_logs', 'logs'],
            'compress' => self::NONE,
            'init_commands' => [],
            'no-data' => [],
            'add-drop-table' => false,
            'add-drop-trigger' => true,
            'add-locks' => true,
            'complete-insert' => false,
            'default-character-set' => self::UTF8MB4,
            'disable-keys' => true,
            'extended-insert' => true,
            'hex-blob' => true,
            'insert-ignore' => false,
            'net_buffer_length' => self::MAXLINESIZE,
            'no-autocommit' => true,
            'no-create-info' => false,
            'lock-tables' => true,
            'single-transaction' => true,
            'skip-triggers' => false,
            'skip-comments' => false,
            'skip-dump-date' => false,
        ];

        $pdoSettingsDefault = [
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ];

        $this->user = $user;
        $this->pass = $pass;
        $this->host = config('database.connections.mysql.host', 'localhost');
        $this->dbName = config('database.connections.mysql.database', 'clientx');
        $this->dbType = 'mysql';

        if ($this->dbType === "mysql") {
            $pdoSettingsDefault[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = false;
        }

        $this->pdoSettings = array_replace_recursive($pdoSettingsDefault, $pdoSettings);
        $this->dumpSettings = array_replace_recursive($dumpSettingsDefault, $dumpSettings);
        $this->dumpSettings['init_commands'][] = "SET NAMES " . $this->dumpSettings['default-character-set'];
        $this->compressManager = new CompressNone();
    }

    public function start($filename, PDO $pdo)
    {
        $this->fileName = $filename;
        $this->dbHandler = $pdo;

        foreach ($this->dumpSettings['init_commands'] as $stmt) {
            $this->dbHandler->exec($stmt);
        }
        $this->version = $this->dbHandler->getAttribute(PDO::ATTR_SERVER_VERSION);
        $this->dbHandler->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_NATURAL);
        $this->typeAdapter = new TypeAdapterMysql($this->dbHandler, $this->dumpSettings);

        $this->compressManager->open($this->fileName);
        $this->compressManager->write($this->getDumpFileHeader());
        $this->compressManager->write($this->typeAdapter->backup_parameters());

        $this->getDatabaseStructureTables();
        $this->getDatabaseStructureViews();
        if ($this->dumpSettings['skip-triggers']) {
            $this->getDatabaseStructureTriggers();
        }

        $this->exportTables();
        $this->exportTriggers();
        $this->exportViews();

        $this->compressManager->write($this->typeAdapter->restore_parameters());
        $this->compressManager->write($this->getDumpFileFooter());
        $this->compressManager->close();
    }

    private function getDumpFileHeader()
    {
        $header = '';
        if ($this->dumpSettings['skip-comments']) {
            $header = "-- Database Backup" . PHP_EOL .
                "-- Host: {$this->host}\tDatabase: {$this->dbName}" . PHP_EOL .
                "-- Server version: " . $this->version . PHP_EOL .
                "-- Date: " . date('r') . PHP_EOL . PHP_EOL;
        }
        return $header;
    }

    private function getDumpFileFooter()
    {
        $footer = '';
        if ($this->dumpSettings['skip-comments']) {
            $footer .= '-- Dump completed on: ' . date('r') . PHP_EOL;
        }
        return $footer;
    }

    private function getDatabaseStructureTables()
    {
        $sql = "SELECT TABLE_NAME AS tbl_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='BASE TABLE' AND TABLE_SCHEMA='{$this->dbName}'";
        foreach ($this->dbHandler->query($sql) as $row) {
            if (!in_array($row['tbl_name'], $this->dumpSettings['exclude-tables'])) {
                $this->tables[] = $row['tbl_name'];
            }
        }
    }

    private function getDatabaseStructureViews()
    {
        $sql = "SELECT TABLE_NAME AS tbl_name FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE='VIEW' AND TABLE_SCHEMA='{$this->dbName}'";
        foreach ($this->dbHandler->query($sql) as $row) {
            $this->views[] = $row['tbl_name'];
        }
    }

    private function getDatabaseStructureTriggers()
    {
        foreach ($this->dbHandler->query("SHOW TRIGGERS FROM `{$this->dbName}`") as $row) {
            $this->triggers[] = $row['Trigger'];
        }
    }

    private function exportTables()
    {
        foreach ($this->tables as $table) {
            $this->getTableStructure($table);
            $this->listValues($table);
        }
    }

    private function exportViews()
    {
        foreach ($this->views as $view) {
            $this->getViewStructure($view);
        }
    }

    private function exportTriggers()
    {
        foreach ($this->triggers as $trigger) {
            $this->getTriggerStructure($trigger);
        }
    }

    private function getTableStructure($tableName)
    {
        if ($this->dumpSettings['no-create-info']) {
            $ret = '';
            if ($this->dumpSettings['skip-comments']) {
                $ret = PHP_EOL . "--" . PHP_EOL . "-- Table structure for table `$tableName`" . PHP_EOL . "--" . PHP_EOL . PHP_EOL;
            }
            foreach ($this->dbHandler->query("SHOW CREATE TABLE `$tableName`") as $r) {
                $this->compressManager->write($ret);
                if ($this->dumpSettings['add-drop-table']) {
                    $this->compressManager->write("DROP TABLE IF EXISTS `$tableName`;" . PHP_EOL);
                }
                $this->compressManager->write($r['Create Table'] . ";" . PHP_EOL . PHP_EOL);
                break;
            }
        }
        $this->tableColumnTypes[$tableName] = $this->getTableColumnTypes($tableName);
    }

    private function getTableColumnTypes($tableName)
    {
        $columnTypes = [];
        $columns = $this->dbHandler->query("SHOW COLUMNS FROM `$tableName`");
        $columns->setFetchMode(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            $type = strtolower($col['Type']);
            $columnTypes[$col['Field']] = [
                'is_numeric' => preg_match('/^(int|tinyint|smallint|mediumint|bigint|float|double|decimal|numeric|bit)/i', $type),
                'is_blob' => preg_match('/^(blob|tinyblob|mediumblob|longblob|binary|varbinary)/i', $type),
                'type' => $type,
            ];
        }
        return $columnTypes;
    }

    private function getViewStructure($viewName)
    {
        if ($this->dumpSettings['skip-comments']) {
            $this->compressManager->write(PHP_EOL . "--" . PHP_EOL . "-- View structure for `$viewName`" . PHP_EOL . "--" . PHP_EOL . PHP_EOL);
        }
        foreach ($this->dbHandler->query("SHOW CREATE VIEW `$viewName`") as $r) {
            $this->compressManager->write("DROP VIEW IF EXISTS `$viewName`;" . PHP_EOL);
            $this->compressManager->write($r['Create View'] . ";" . PHP_EOL . PHP_EOL);
            break;
        }
    }

    private function getTriggerStructure($triggerName)
    {
        foreach ($this->dbHandler->query("SHOW CREATE TRIGGER `$triggerName`") as $r) {
            if ($this->dumpSettings['add-drop-trigger']) {
                $this->compressManager->write("DROP TRIGGER IF EXISTS `$triggerName`;" . PHP_EOL);
            }
            $this->compressManager->write("DELIMITER ;;" . PHP_EOL . $r['SQL Original Statement'] . ";;" . PHP_EOL . "DELIMITER ;" . PHP_EOL . PHP_EOL);
            break;
        }
    }

    private function listValues($tableName)
    {
        if ($this->dumpSettings['skip-comments']) {
            $this->compressManager->write("--" . PHP_EOL . "-- Dumping data for table `$tableName`" . PHP_EOL . "--" . PHP_EOL . PHP_EOL);
        }

        if ($this->dumpSettings['single-transaction']) {
            $this->dbHandler->exec("SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ");
            $this->dbHandler->exec("START TRANSACTION WITH CONSISTENT SNAPSHOT");
        }

        if ($this->dumpSettings['add-locks']) {
            $this->compressManager->write("LOCK TABLES `$tableName` WRITE;" . PHP_EOL);
        }
        if ($this->dumpSettings['disable-keys']) {
            $this->compressManager->write("/*!40000 ALTER TABLE `$tableName` DISABLE KEYS */;" . PHP_EOL);
        }

        $colNames = array_keys($this->tableColumnTypes[$tableName]);
        $colStmt = array_map(fn($c) => "`$c`", $colNames);
        $stmt = "SELECT " . implode(",", $colStmt) . " FROM `$tableName`";
        $resultSet = $this->dbHandler->query($stmt);
        $resultSet->setFetchMode(PDO::FETCH_ASSOC);

        $onlyOnce = true;
        $lineSize = 0;
        $ignore = $this->dumpSettings['insert-ignore'] ? ' IGNORE' : '';

        foreach ($resultSet as $row) {
            $vals = $this->prepareColumnValues($tableName, $row);
            if ($onlyOnce || $this->dumpSettings['extended-insert']) {
                $lineSize += $this->compressManager->write("INSERT$ignore INTO `$tableName` VALUES (" . implode(",", $vals) . ")");
                $onlyOnce = false;
            } else {
                $lineSize += $this->compressManager->write(",(" . implode(",", $vals) . ")");
            }
            if ($lineSize > $this->dumpSettings['net_buffer_length'] || $this->dumpSettings['extended-insert']) {
                $onlyOnce = true;
                $lineSize = $this->compressManager->write(";" . PHP_EOL);
            }
        }
        $resultSet->closeCursor();

        if ($onlyOnce) {
            $this->compressManager->write(";" . PHP_EOL);
        }

        if ($this->dumpSettings['disable-keys']) {
            $this->compressManager->write("/*!40000 ALTER TABLE `$tableName` ENABLE KEYS */;" . PHP_EOL);
        }
        if ($this->dumpSettings['add-locks']) {
            $this->compressManager->write("UNLOCK TABLES;" . PHP_EOL);
        }
        if ($this->dumpSettings['single-transaction']) {
            $this->dbHandler->exec("COMMIT");
        }
        $this->compressManager->write(PHP_EOL);
    }

    private function prepareColumnValues($tableName, array $row)
    {
        $ret = [];
        $columnTypes = $this->tableColumnTypes[$tableName];
        foreach ($row as $colName => $colValue) {
            $ret[] = $this->escape($colValue, $columnTypes[$colName]);
        }
        return $ret;
    }

    private function escape($colValue, $colType)
    {
        if (is_null($colValue)) {
            return "NULL";
        } elseif ($this->dumpSettings['hex-blob'] && $colType['is_blob'] && !empty($colValue)) {
            return "0x" . bin2hex($colValue);
        } elseif ($colType['is_numeric']) {
            return $colValue;
        }
        return $this->dbHandler->quote($colValue);
    }
}

class CompressNone
{
    private $fileHandler = null;

    public function open($filename)
    {
        $this->fileHandler = fopen($filename, "wb");
        if (false === $this->fileHandler) {
            throw new Exception("Output file is not writable");
        }
        return true;
    }

    public function write($str)
    {
        $bytesWritten = fwrite($this->fileHandler, $str);
        if (false === $bytesWritten) {
            throw new Exception("Writing to file failed");
        }
        return $bytesWritten;
    }

    public function close()
    {
        return fclose($this->fileHandler);
    }
}

class TypeAdapterMysql
{
    protected $dbHandler;
    protected $dumpSettings;

    public function __construct($dbHandler, $dumpSettings = [])
    {
        $this->dbHandler = $dbHandler;
        $this->dumpSettings = $dumpSettings;
    }

    public function backup_parameters()
    {
        $ret = "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;" . PHP_EOL .
            "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;" . PHP_EOL .
            "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;" . PHP_EOL .
            "/*!40101 SET NAMES " . $this->dumpSettings['default-character-set'] . " */;" . PHP_EOL .
            "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;" . PHP_EOL .
            "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;" . PHP_EOL .
            "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;" . PHP_EOL . PHP_EOL;
        return $ret;
    }

    public function restore_parameters()
    {
        return "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;" . PHP_EOL .
            "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;" . PHP_EOL .
            "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;" . PHP_EOL .
            "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;" . PHP_EOL .
            "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;" . PHP_EOL .
            "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;" . PHP_EOL . PHP_EOL;
    }
}
