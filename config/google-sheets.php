<?php

/**
 * Google Sheets API Configuration
 * 
 * Instructions:
 * 1. Go to https://console.developers.google.com/
 * 2. Create a new project or select existing one
 * 3. Enable Google Sheets API
 * 4. Create credentials (Service Account)
 * 5. Download the JSON file and place it in config/ folder
 * 6. Update the path below to match your JSON file name
 */

class GoogleSheetsConfig
{
    // Path to your service account JSON file
    private static $credentialsPath = __DIR__ . '/google-service-account.json';

    // Default folder ID where sheets will be created (optional)
    private static $defaultFolderId = ''; // Set to a Google Drive folder ID if you want

    public static function getCredentialsPath()
    {
        return self::$credentialsPath;
    }

    public static function getDefaultFolderId()
    {
        return self::$defaultFolderId;
    }

    public static function setCredentialsPath($path)
    {
        self::$credentialsPath = $path;
    }

    public static function setDefaultFolderId($folderId)
    {
        self::$defaultFolderId = $folderId;
    }

    public static function credentialsExist()
    {
        return file_exists(self::$credentialsPath);
    }
}
