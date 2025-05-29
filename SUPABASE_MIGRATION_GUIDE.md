# EVSU Voice: MySQL to Supabase Migration Guide

This guide will help you migrate your EVSU Voice application from MySQL to Supabase (PostgreSQL).

## Prerequisites

- A Supabase account (sign up at https://supabase.com)
- Basic understanding of databases and PHP

## Step 1: Create Supabase Project

1. Go to [Supabase Dashboard](https://app.supabase.com)
2. Click "New project"
3. Choose your organization
4. Enter project details:
   - Name: `evsu-voice`
   - Database Password: Choose a strong password
   - Region: Choose closest to your location
5. Click "Create new project"
6. Wait for the project to be created (this may take a few minutes)

## Step 2: Get Connection Details

Once your project is ready:

1. Go to Settings → Database
2. Copy the following information:
   - Host (something like `db.abcdefghijklmnop.supabase.co`)
   - Database password (the one you created)
   - Port: `5432`
   - Database: `postgres`
   - User: `postgres`

## Step 3: Configure Your Application

1. Open `config/supabase.php`
2. Replace the placeholder values with your actual Supabase credentials:

```php
return [
    'database' => [
        'host' => 'db.your-project-ref.supabase.co', // Replace with your host
        'password' => 'your-database-password',       // Replace with your password
        'username' => 'postgres',
        'database' => 'postgres',
        'port' => '5432',
    ],
    'api' => [
        'url' => 'https://your-project-ref.supabase.co',    // Replace with your URL
        'anon_key' => 'your-anon-key',                      // Get from Settings → API
        'service_role_key' => 'your-service-role-key',     // Get from Settings → API
    ]
];
```

## Step 4: Create Database Schema

1. In your Supabase dashboard, go to the SQL Editor
2. Copy the entire contents of `database/supabase_schema.sql`
3. Paste it into the SQL Editor
4. Click "Run" to execute the schema

This will create:
- All tables (users, suggestions, votes, categories, archived_suggestions)
- Proper indexes for performance
- Row Level Security (RLS) policies
- Triggers for automatic timestamp updates
- Default categories and admin user

## Step 5: Install Required PHP Extensions

Ensure your PHP installation has the PostgreSQL extension:

```bash
# For Ubuntu/Debian
sudo apt-get install php-pgsql

# For CentOS/RHEL
sudo yum install php-pgsql

# For Windows with XAMPP
# The extension is usually included, just uncomment in php.ini:
# extension=pgsql
```

## Step 6: Update Remaining Files

Several files still need to be updated with PostgreSQL-compatible queries. Here are the key changes needed:

### Files that need updating:

1. **admin/archive.php** - Update date functions
2. **admin/export-data.php** - Update date functions and export queries
3. **browse-suggestions.php** - Update CONCAT functions
4. **my-suggestions.php** - Update date and CONCAT functions
5. **my-trash.php** - Update date functions

### Key Changes Required:

#### Date Functions:
- `NOW()` → `CURRENT_TIMESTAMP`
- `CURDATE()` → `CURRENT_DATE`
- `DATE(column)` → `DATE(column)` (same)
- `DATE_SUB(NOW(), INTERVAL 1 WEEK)` → `CURRENT_TIMESTAMP - INTERVAL '1 week'`

#### String Functions:
- `CONCAT(a, ' ', b)` → `(a || ' ' || b)`

#### Boolean Values:
- MySQL: `1` and `0` for boolean
- PostgreSQL: `true` and `false` or `1` and `0` both work

## Step 7: Test the Migration

1. **Test Database Connection:**
   - Create a simple test file:
   ```php
   <?php
   require_once 'config/database.php';
   $db = new Database();
   $conn = $db->getConnection();
   if ($conn) {
       echo "Connection successful!";
   } else {
       echo "Connection failed!";
   }
   ?>
   ```

2. **Test Basic Functionality:**
   - Try logging in with admin credentials: `admin@evsu.edu.ph` / `admin`
   - Submit a test suggestion
   - Test voting functionality
   - Test admin features

## Step 8: Data Migration (Optional)

If you have existing data in MySQL, you can migrate it:

1. **Export from MySQL:**
   ```sql
   SELECT * FROM users;
   SELECT * FROM suggestions;
   SELECT * FROM votes;
   -- etc.
   ```

2. **Import to Supabase:**
   - Use the Supabase dashboard or pgAdmin
   - Adjust date formats and boolean values as needed

## Step 9: Environment Variables (Recommended)

For production, consider using environment variables:

1. Create a `.env` file:
   ```
   SUPABASE_HOST=db.your-project-ref.supabase.co
   SUPABASE_PASSWORD=your-password
   SUPABASE_URL=https://your-project-ref.supabase.co
   SUPABASE_ANON_KEY=your-anon-key
   ```

2. Use `getenv()` in your config files to read these values.

## Step 10: Row Level Security (RLS)

The schema includes basic RLS policies. For production, you may want to:

1. Restrict user access to their own data
2. Add admin-only policies for management features
3. Test all access patterns thoroughly

## Troubleshooting

### Common Issues:

1. **Connection fails:**
   - Check your credentials in `config/supabase.php`
   - Ensure SSL is enabled in your PHP configuration
   - Verify your IP is not blocked by Supabase

2. **Date/time issues:**
   - PostgreSQL is stricter about date formats
   - Use ISO format: `YYYY-MM-DD HH:MM:SS`

3. **Query errors:**
   - Check for MySQL-specific syntax
   - Use PostgreSQL documentation for reference

4. **Boolean values:**
   - Use `true`/`false` instead of `1`/`0` in queries

### Getting Help:

- Supabase Documentation: https://supabase.com/docs
- PostgreSQL Documentation: https://www.postgresql.org/docs/
- PHP PDO Documentation: https://www.php.net/manual/en/book.pdo.php

## Benefits of Supabase

After migration, you'll have:

- **Automatic scaling** - No need to manage server resources
- **Real-time features** - Built-in real-time subscriptions
- **Automatic backups** - Daily backups included
- **API access** - REST and GraphQL APIs out of the box
- **Authentication** - Built-in user management (can replace current auth)
- **Security** - Row Level Security and built-in protections

## Next Steps

1. Complete the migration following this guide
2. Test thoroughly in a development environment
3. Consider using Supabase Auth for user management
4. Explore real-time features for live updates
5. Set up proper backups and monitoring

---

**Note:** This migration changes your database from MySQL to PostgreSQL. While the core functionality remains the same, some advanced features and queries may need adjustment. Always test thoroughly before deploying to production. 