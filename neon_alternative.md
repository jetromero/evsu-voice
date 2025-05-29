# Quick Alternative: Neon.tech PostgreSQL

Since you're having DNS issues with Supabase, let's try Neon.tech:

## Step 1: Create Neon Account (2 minutes)

1. Go to: https://neon.tech
2. Click "Sign up"
3. Use GitHub/Google to sign up (fastest)
4. Create a new project
5. Choose a project name: `evsu-voice`

## Step 2: Get Connection Details

After creating the project:

1. You'll see a connection string like:
   ```
   postgresql://username:password@hostname/database?sslmode=require
   ```
2. Copy this connection string

## Step 3: Update Your Config

1. Open `config/supabase.php`
2. Parse your connection string and update:

```php
return [
    'database' => [
        'host' => 'your-neon-hostname',     // from connection string
        'password' => 'your-neon-password', // from connection string
        'username' => 'your-neon-username', // from connection string
        'database' => 'your-neon-database', // from connection string
        'port' => '5432',
    ],
    'api' => [
        'url' => 'https://neon.tech', // not needed for now
        'anon_key' => 'not-needed',
        'service_role_key' => 'not-needed',
    ]
];
```

## Step 4: Run Schema

1. In Neon dashboard, go to "SQL Editor"
2. Copy contents of `database/supabase_schema.sql`
3. Paste and run

## Step 5: Test

```bash
php temp_fix_pdo.php
```

## Why Neon is Easier:

- ✅ Better network connectivity
- ✅ Simpler setup
- ✅ Same PostgreSQL features
- ✅ Free tier generous

---

**This should work around your DNS issues!**
