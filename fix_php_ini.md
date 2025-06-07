# Quick Fix: Temporarily Disable Problematic Extension

## Step 1: Edit php.ini

1. Open `C:\xampp\php\php.ini`
2. Find the line: `extension=pdo_pgsql`
3. Comment it out by adding a semicolon: `;extension=pdo_pgsql`
4. Keep this line active: `extension=pgsql`
5. Save the file

## Step 2: Restart Apache

1. Stop Apache in XAMPP Control Panel
2. Start Apache again

## Step 3: Test

```bash
php temp_fix_pdo.php
```

## Why This Works:

- Your `pgsql` extension is working perfectly ✅
- We're temporarily disabling the broken `pdo_pgsql`
- Your app can still connect to PostgreSQL
- You can fix PDO later or use alternatives

## After This Fix:

1. Test the connection
2. Run the SQL schema in Supabase dashboard
3. Your app should work!

---

**This is a temporary solution to get you running quickly!**
