<?php
// Production Supabase configuration using environment variables
// This file is safe to commit as it doesn't contain sensitive data

return [
    'database' => [
        'host' => $_ENV['SUPABASE_DB_HOST'] ?? getenv('SUPABASE_DB_HOST'),
        'port' => $_ENV['SUPABASE_DB_PORT'] ?? getenv('SUPABASE_DB_PORT') ?? '5432',
        'database' => $_ENV['SUPABASE_DB_NAME'] ?? getenv('SUPABASE_DB_NAME') ?? 'postgres',
        'username' => $_ENV['SUPABASE_DB_USER'] ?? getenv('SUPABASE_DB_USER') ?? 'postgres',
        'password' => $_ENV['SUPABASE_DB_PASSWORD'] ?? getenv('SUPABASE_DB_PASSWORD'),
    ]
];
