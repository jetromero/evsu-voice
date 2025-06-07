<?php
class DatabaseNative
{
    private $config;
    public $conn;

    public function __construct()
    {
        // Load Supabase configuration
        $configFile = __DIR__ . '/supabase.php';
        if (!file_exists($configFile)) {
            throw new Exception("Configuration file not found: $configFile");
        }

        $this->config = include $configFile;

        // Validate configuration
        if (!is_array($this->config) || !isset($this->config['database'])) {
            throw new Exception("Invalid configuration format");
        }
    }

    public function getConnection()
    {
        $this->conn = null;
        try {
            // PostgreSQL connection string for native functions
            $connection_string = sprintf(
                "host=%s port=%s dbname=%s user=%s password=%s sslmode=require",
                $this->config['database']['host'],
                $this->config['database']['port'],
                $this->config['database']['database'],
                $this->config['database']['username'],
                $this->config['database']['password']
            );

            $this->conn = pg_connect($connection_string);

            if (!$this->conn) {
                throw new Exception("Failed to connect to PostgreSQL database");
            }

            // Set timezone for PostgreSQL
            pg_query($this->conn, "SET timezone = 'UTC'");
        } catch (Exception $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }

    public function query($sql, $params = [])
    {
        if (!$this->conn) {
            $this->getConnection();
        }

        if (!$this->conn) {
            error_log("Database connection failed before executing query: " . $sql);
            return false;
        }

        if (empty($params)) {
            $result = pg_query($this->conn, $sql);
        } else {
            $result = pg_query_params($this->conn, $sql, $params);
        }

        if (!$result) {
            $error = pg_last_error($this->conn);
            error_log("PostgreSQL query failed: " . $error . " | Query: " . $sql);
        }

        return $result;
    }

    public function fetchAll($result)
    {
        if (!$result) {
            return [];
        }
        return pg_fetch_all($result) ?: [];
    }

    public function fetchAssoc($result)
    {
        if (!$result) {
            return false;
        }
        return pg_fetch_assoc($result);
    }

    public function numRows($result)
    {
        return pg_num_rows($result);
    }

    public function escape($string)
    {
        return pg_escape_string($this->conn, $string);
    }

    public function lastInsertId($table, $column = 'id')
    {
        $result = pg_query($this->conn, "SELECT currval(pg_get_serial_sequence('$table', '$column'))");
        $row = pg_fetch_row($result);
        return $row[0];
    }

    public function close()
    {
        if ($this->conn) {
            pg_close($this->conn);
        }
    }
}
