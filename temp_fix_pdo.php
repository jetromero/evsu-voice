<?php

/**
 * Temporary Database Connection Test using pgsql extension directly
 * This bypasses PDO and uses the native pgsql extension which is working
 */

require_once 'config/supabase.php';

echo "Testing PostgreSQL connection with native pgsql extension...\n";
echo "==========================================================\n\n";

$config = require 'config/supabase.php';

$connection_string = "host={$config['database']['host']} " .
    "port={$config['database']['port']} " .
    "dbname={$config['database']['database']} " .
    "user={$config['database']['username']} " .
    "password={$config['database']['password']} " .
    "sslmode=require";

echo "Connecting to: {$config['database']['host']}\n";

$connection = pg_connect($connection_string);

if ($connection) {
    echo "✅ PostgreSQL connection successful using native pgsql!\n\n";

    // Test basic query
    $result = pg_query($connection, "SELECT version()");
    if ($result) {
        $row = pg_fetch_row($result);
        echo "📊 PostgreSQL Version: " . $row[0] . "\n\n";
    }

    // Test if our tables exist
    $tables_query = "SELECT tablename FROM pg_tables WHERE schemaname = 'public'";
    $result = pg_query($connection, $tables_query);

    echo "📋 Tables in database:\n";
    $tables = [];
    while ($row = pg_fetch_row($result)) {
        echo "   ✅ " . $row[0] . "\n";
        $tables[] = $row[0];
    }

    $required_tables = ['users', 'suggestions', 'votes', 'categories', 'archived_suggestions'];
    $missing_tables = array_diff($required_tables, $tables);

    if (empty($missing_tables)) {
        echo "\n🎉 All required tables found!\n";
        echo "🚀 Your Supabase database is ready!\n";
    } else {
        echo "\n⚠️  Missing tables: " . implode(', ', $missing_tables) . "\n";
        echo "📝 You need to run the SQL schema in Supabase dashboard.\n";
    }

    pg_close($connection);
} else {
    echo "❌ Connection failed: " . pg_last_error() . "\n";
    echo "\n🔧 Check your credentials in config/supabase.php\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "💡 Next Steps:\n";
echo "1. If connection works, you can use the app (PDO not required for basic testing)\n";
echo "2. For full functionality, fix the pdo_pgsql issue by downloading ZTS DLL\n";
echo "3. Or switch to an alternative like Neon.tech for easier setup\n";
