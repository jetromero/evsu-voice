# Google Sheets API Integration Setup Guide

This guide will help you set up Google Sheets API integration for your EVSU Voice application.

## Prerequisites

- Google Account
- Access to Google Cloud Console
- EVSU Voice application with admin access

## Step-by-Step Setup

### 1. Create Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click on "Select a project" dropdown at the top
3. Click "New Project"
4. Enter project name: `EVSU-Voice-Sheets` (or any name you prefer)
5. Click "Create"

### 2. Enable Required APIs

1. In your project dashboard, go to "APIs & Services" > "Library"
2. Search for and enable these APIs:
   - **Google Sheets API**
   - **Google Drive API**

For each API:

- Click on the API name
- Click "Enable" button
- Wait for activation

### 3. Create Service Account

1. Go to "APIs & Services" > "Credentials"
2. Click "Create Credentials" > "Service Account"
3. Fill in the details:
   - **Service Account Name**: `evsu-voice-sheets-service`
   - **Service Account ID**: (auto-generated)
   - **Description**: `Service account for EVSU Voice Google Sheets integration`
4. Click "Create and Continue"
5. For roles, add:
   - **Editor** (or you can be more specific with "Drive File Editor" and "Sheets Editor")
6. Click "Continue" then "Done"

### 4. Generate Service Account Key

1. In the Credentials page, find your service account
2. Click on the service account email
3. Go to the "Keys" tab
4. Click "Add Key" > "Create New Key"
5. Select "JSON" format
6. Click "Create"
7. The JSON file will be downloaded automatically

### 5. Configure Your Application

1. **Upload the JSON file**:

   - Rename the downloaded JSON file to `google-service-account.json`
   - Upload it to your `config/` directory
   - The path should be: `/config/google-service-account.json`

2. **Secure the file** (Important!):
   ```bash
   # Make sure the file is not publicly accessible
   # Add to your .htaccess in config/ directory:
   ```
   Create a file `config/.htaccess` with:
   ```
   deny from all
   ```

### 6. Optional: Set up Google Drive Folder

If you want all exported sheets to be organized in a specific folder:

1. Create a folder in your Google Drive
2. Right-click the folder > "Share"
3. Add your service account email (found in the JSON file) with "Editor" permissions
4. Copy the folder ID from the URL (the long string after `/folders/`)
5. Update `config/google-sheets.php`:
   ```php
   private static $defaultFolderId = 'YOUR_FOLDER_ID_HERE';
   ```

### 7. Test the Integration

1. Go to your admin panel > Reports page
2. You should now see:
   - Google Sheets export buttons (green buttons with Google icon)
   - No error messages about missing configuration
   - The ability to create Google Sheets directly

### 8. Security Considerations

1. **Protect the service account file**:

   - Never commit it to version control
   - Ensure it's not publicly accessible via web
   - Consider using environment variables in production

2. **Limit service account permissions**:

   - Only grant necessary Google API scopes
   - Consider creating a dedicated Google account for this service

3. **Monitor usage**:
   - Check Google Cloud Console for API usage
   - Set up alerts for unusual activity

## Troubleshooting

### Common Issues

**Error: "Google Sheets credentials file not found"**

- Check that `google-service-account.json` is in the `config/` directory
- Verify file permissions

**Error: "The caller does not have permission"**

- Ensure APIs are enabled in Google Cloud Console
- Check service account has proper roles
- If using a specific folder, ensure service account has access

**Error: "Invalid credentials"**

- Re-download the service account JSON file
- Ensure the JSON file is valid and not corrupted

**Sheets created but not visible**

- The sheet is created in the service account's drive
- Check if you've set up folder sharing correctly
- The sheet should be publicly viewable (read-only)

## File Structure

After setup, your file structure should look like:

```
your-project/
├── config/
│   ├── google-service-account.json (your service account key)
│   ├── google-sheets.php (configuration)
│   └── .htaccess (security)
├── includes/
│   └── GoogleSheetsService.php (service class)
├── assets/css/
│   └── google-sheets-addon.css (styles)
└── admin/
    └── export-data.php (updated export page)
```

## Features

Once set up, you'll have:

✅ **CSV Export** (existing functionality)
✅ **Google Sheets Export** with:

- Formatted headers with colors
- Auto-resized columns
- Public read-only access
- Organized in Drive folders (optional)

✅ **Enhanced UI** with:

- Success/error notifications
- Loading states for better UX
- Quick export buttons for both formats

## Support

If you encounter issues:

1. Check the browser console for JavaScript errors
2. Check server error logs for PHP errors
3. Verify Google Cloud Console for API quota limits
4. Ensure all file permissions are correct

## Notes

- Google Sheets has daily quotas (usually generous for normal use)
- Each export creates a new spreadsheet
- Sheets are automatically made publicly viewable (read-only)
- The service account will be the owner of created sheets
