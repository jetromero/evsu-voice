<?php

/**
 * MySQL to PostgreSQL Conversion Helper Script
 * This script helps convert MySQL-specific syntax to PostgreSQL
 * Run this script to update the remaining files automatically
 */

// List of files that need conversion
$files_to_convert = [
    'admin/archive.php',
    'admin/export-data.php',
    'browse-suggestions.php',
    'my-suggestions.php',
    'my-trash.php',
    'submit-suggestion.php',
    'vote-handler.php',
    'vote-handler-simple.php'
];

// Conversion patterns
$conversions = [
    // Date functions
    '/\bNOW\(\)/' => 'CURRENT_TIMESTAMP',
    '/\bCURDATE\(\)/' => 'CURRENT_DATE',

    // Date subtraction patterns
    '/DATE_SUB\(NOW\(\),\s*INTERVAL\s+1\s+DAY\)/' => "CURRENT_TIMESTAMP - INTERVAL '1 day'",
    '/DATE_SUB\(NOW\(\),\s*INTERVAL\s+1\s+WEEK\)/' => "CURRENT_TIMESTAMP - INTERVAL '1 week'",
    '/DATE_SUB\(NOW\(\),\s*INTERVAL\s+1\s+MONTH\)/' => "CURRENT_TIMESTAMP - INTERVAL '1 month'",
    '/DATE_SUB\(NOW\(\),\s*INTERVAL\s+1\s+YEAR\)/' => "CURRENT_TIMESTAMP - INTERVAL '1 year'",

    // Date comparisons
    '/DATE\(([^)]+)\)\s*=\s*CURDATE\(\)/' => 'DATE($1) = CURRENT_DATE',
    '/([^.]+\.created_at)\s*>=\s*DATE_SUB\(NOW\(\),\s*INTERVAL\s+1\s+WEEK\)/' => '$1 >= CURRENT_TIMESTAMP - INTERVAL \'1 week\'',
    '/([^.]+\.created_at)\s*>=\s*DATE_SUB\(NOW\(\),\s*INTERVAL\s+1\s+MONTH\)/' => '$1 >= CURRENT_TIMESTAMP - INTERVAL \'1 month\'',
    '/([^.]+\.created_at)\s*>=\s*DATE_SUB\(NOW\(\),\s*INTERVAL\s+1\s+YEAR\)/' => '$1 >= CURRENT_TIMESTAMP - INTERVAL \'1 year\'',

    // CONCAT function - this is more complex, handle simple cases
    '/CONCAT\(([^,]+),\s*\'([^\']*)\',\s*([^)]+)\)/' => '($1 || \'$2\' || $3)',

    // Auto increment
    '/AUTO_INCREMENT/' => '',

    // Engine and charset
    '/ENGINE\s*=\s*[^\s;]+/' => '',
    '/DEFAULT\s+CHARSET\s*=\s*[^\s;]+/' => '',
];

echo "MySQL to PostgreSQL Conversion Script\n";
echo "=====================================\n\n";

$backup_dir = 'backup_' . date('Y-m-d_H-i-s');
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
    echo "Created backup directory: $backup_dir\n";
}

foreach ($files_to_convert as $file) {
    if (!file_exists($file)) {
        echo "⚠️  File not found: $file\n";
        continue;
    }

    echo "Processing: $file\n";

    // Create backup
    $backup_file = $backup_dir . '/' . basename($file);
    copy($file, $backup_file);

    // Read file content
    $content = file_get_contents($file);
    $original_content = $content;

    // Apply conversions
    foreach ($conversions as $pattern => $replacement) {
        $content = preg_replace($pattern, $replacement, $content);
    }

    // Check if any changes were made
    if ($content !== $original_content) {
        file_put_contents($file, $content);
        echo "✅ Updated: $file\n";
    } else {
        echo "ℹ️  No changes needed: $file\n";
    }
}

echo "\n🔍 Manual Review Required:\n";
echo "- Check CONCAT functions with complex expressions\n";
echo "- Verify date/time handling in all contexts\n";
echo "- Test all updated functionality\n";
echo "- Review backup files in: $backup_dir\n\n";

echo "📝 Next Steps:\n";
echo "1. Update config/supabase.php with your credentials\n";
echo "2. Run the SQL schema in Supabase dashboard\n";
echo "3. Test the application thoroughly\n";
echo "4. Check the SUPABASE_MIGRATION_GUIDE.md for detailed instructions\n";

echo "\n✨ Conversion completed!\n";
