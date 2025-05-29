<?php

/**
 * PHP Extensions Checker for Supabase Migration
 * This script checks if the required PHP extensions are installed
 */

echo "PHP Extensions Checker for Supabase\n";
echo "====================================\n\n";

echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHP Configuration File (php.ini): " . php_ini_loaded_file() . "\n\n";

// Check required extensions
$required_extensions = [
    'pdo' => 'PDO (PHP Data Objects)',
    'pdo_pgsql' => 'PDO PostgreSQL Driver',
    'pgsql' => 'PostgreSQL Extension',
    'openssl' => 'OpenSSL (for SSL connections)'
];

echo "🔍 Checking Required Extensions:\n";
echo "=================================\n";

$missing_extensions = [];
foreach ($required_extensions as $extension => $description) {
    if (extension_loaded($extension)) {
        echo "✅ $extension - $description\n";
    } else {
        echo "❌ $extension - $description (MISSING)\n";
        $missing_extensions[] = $extension;
    }
}

if (empty($missing_extensions)) {
    echo "\n🎉 All required extensions are installed!\n";
    echo "You can now test the Supabase connection.\n";
} else {
    echo "\n⚠️  Missing Extensions Found!\n";
    echo "============================\n\n";

    echo "📝 To Fix This (XAMPP on Windows):\n";
    echo "1. Open: C:\\xampp\\php\\php.ini\n";
    echo "2. Find and uncomment these lines:\n";

    foreach ($missing_extensions as $ext) {
        if ($ext === 'pdo_pgsql' || $ext === 'pgsql') {
            echo "   ;extension=$ext  →  extension=$ext\n";
        }
    }

    echo "3. Save the file\n";
    echo "4. Restart Apache in XAMPP Control Panel\n";
    echo "5. Run this script again to verify\n\n";

    echo "🐧 For Ubuntu/Debian:\n";
    echo "sudo apt-get install php-pgsql php-pdo-pgsql\n\n";

    echo "🎩 For CentOS/RHEL:\n";
    echo "sudo yum install php-pgsql\n\n";
}

// Check PDO drivers specifically
echo "\n📊 Available PDO Drivers:\n";
echo "=========================\n";
$pdo_drivers = PDO::getAvailableDrivers();
foreach ($pdo_drivers as $driver) {
    echo "✅ $driver\n";
}

if (!in_array('pgsql', $pdo_drivers)) {
    echo "❌ pgsql (PostgreSQL) - MISSING\n";
}

// Show loaded extensions for reference
echo "\n📋 All Loaded Extensions:\n";
echo "========================\n";
$loaded_extensions = get_loaded_extensions();
sort($loaded_extensions);
foreach ($loaded_extensions as $ext) {
    echo "- $ext\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "💡 Next Steps:\n";
if (empty($missing_extensions)) {
    echo "1. Run: php test_connection.php\n";
    echo "2. If connection works, your migration is complete!\n";
} else {
    echo "1. Enable missing extensions in php.ini\n";
    echo "2. Restart Apache\n";
    echo "3. Run this script again\n";
    echo "4. Then test: php test_connection.php\n";
}
