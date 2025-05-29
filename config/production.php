<?php
// Production configuration settings

// Error reporting for production
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Security settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

// Database connection timeout
ini_set('default_socket_timeout', 30);

// Production database configuration
$production_config = [
    'database' => [
        'host' => $_ENV['SUPABASE_DB_HOST'] ?? getenv('SUPABASE_DB_HOST'),
        'port' => $_ENV['SUPABASE_DB_PORT'] ?? getenv('SUPABASE_DB_PORT') ?? '5432',
        'database' => $_ENV['SUPABASE_DB_NAME'] ?? getenv('SUPABASE_DB_NAME'),
        'username' => $_ENV['SUPABASE_DB_USER'] ?? getenv('SUPABASE_DB_USER'),
        'password' => $_ENV['SUPABASE_DB_PASSWORD'] ?? getenv('SUPABASE_DB_PASSWORD'),
    ]
];

return $production_config;
