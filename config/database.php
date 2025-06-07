<?php
class Database
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
            // PostgreSQL connection string for Supabase
            $dsn = "pgsql:host=" . $this->config['database']['host'] .
                ";port=" . $this->config['database']['port'] .
                ";dbname=" . $this->config['database']['database'] .
                ";sslmode=require";

            $this->conn = new PDO(
                $dsn,
                $this->config['database']['username'],
                $this->config['database']['password']
            );

            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Set timezone for PostgreSQL
            $this->conn->exec("SET timezone = 'UTC'");
        } catch (PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
