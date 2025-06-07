<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/google-sheets.php';

use Google\Client;
use Google\Service\Sheets;
use Google\Service\Drive;

class GoogleSheetsService
{
    private $client;
    private $sheetsService;
    private $driveService;

    public function __construct()
    {
        $this->client = new Client();
        $this->setupClient();
        $this->sheetsService = new Sheets($this->client);
        $this->driveService = new Drive($this->client);
    }

    private function setupClient()
    {
        if (!GoogleSheetsConfig::credentialsExist()) {
            throw new Exception('Google Sheets credentials file not found. Please check the configuration.');
        }

        $this->client->setAuthConfig(GoogleSheetsConfig::getCredentialsPath());
        $this->client->addScope([
            Sheets::SPREADSHEETS,
            Drive::DRIVE_FILE
        ]);
    }

    public function createSpreadsheet($title, $data)
    {
        try {
            // Debug: log data structure if needed
            if (empty($data)) {
                throw new Exception("No data provided for spreadsheet creation");
            }

            // Validate data structure
            $this->validateDataStructure($data);

            // Create spreadsheet
            $spreadsheet = new \Google\Service\Sheets\Spreadsheet([
                'properties' => [
                    'title' => $title
                ]
            ]);

            $spreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);
            $spreadsheetId = $spreadsheet->spreadsheetId;

            // Add data to the spreadsheet
            $this->addDataToSheet($spreadsheetId, $data);

            // Move to specific folder if configured
            if (GoogleSheetsConfig::getDefaultFolderId()) {
                $this->moveToFolder($spreadsheetId, GoogleSheetsConfig::getDefaultFolderId());
            }

            // Make it shareable
            $this->makeShareable($spreadsheetId);

            return [
                'success' => true,
                'spreadsheetId' => $spreadsheetId,
                'url' => "https://docs.google.com/spreadsheets/d/{$spreadsheetId}/edit",
                'title' => $title
            ];
        } catch (Exception $e) {
            error_log("Google Sheets API Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function addDataToSheet($spreadsheetId, $data)
    {
        if (empty($data)) {
            return;
        }

        // Ensure data is properly formatted
        $formattedData = [];
        foreach ($data as $row) {
            if (is_array($row)) {
                // Ensure it's a proper indexed array
                $formattedData[] = array_values($row);
            }
        }

        // Prepare the data range
        $range = 'Sheet1!A1';
        $valueRange = new \Google\Service\Sheets\ValueRange();
        $valueRange->setValues($formattedData);

        $params = [
            'valueInputOption' => 'USER_ENTERED' // Changed from RAW to USER_ENTERED for better handling
        ];

        try {
            $this->sheetsService->spreadsheets_values->update(
                $spreadsheetId,
                $range,
                $valueRange,
                $params
            );

            // Format the header row
            $this->formatHeaderRow($spreadsheetId, count($formattedData[0]));
        } catch (Exception $e) {
            error_log("Error adding data to sheet: " . $e->getMessage());
            throw new Exception("Failed to add data to spreadsheet: " . $e->getMessage());
        }
    }

    private function formatHeaderRow($spreadsheetId, $columnCount)
    {
        $columnLetter = chr(64 + $columnCount); // A=65, so 64+1=A

        $requests = [
            [
                'repeatCell' => [
                    'range' => [
                        'sheetId' => 0,
                        'startRowIndex' => 0,
                        'endRowIndex' => 1,
                        'startColumnIndex' => 0,
                        'endColumnIndex' => $columnCount
                    ],
                    'cell' => [
                        'userEnteredFormat' => [
                            'textFormat' => [
                                'bold' => true,
                                'foregroundColor' => [
                                    'red' => 1.0,
                                    'green' => 1.0,
                                    'blue' => 1.0
                                ]
                            ],
                            'backgroundColor' => [
                                'red' => 0.2,
                                'green' => 0.4,
                                'blue' => 0.8
                            ]
                        ]
                    ],
                    'fields' => 'userEnteredFormat.textFormat.bold,userEnteredFormat.textFormat.foregroundColor,userEnteredFormat.backgroundColor'
                ]
            ],
            [
                'autoResizeDimensions' => [
                    'dimensions' => [
                        'sheetId' => 0,
                        'dimension' => 'COLUMNS',
                        'startIndex' => 0,
                        'endIndex' => $columnCount
                    ]
                ]
            ]
        ];

        $batchUpdateRequest = new \Google\Service\Sheets\BatchUpdateSpreadsheetRequest([
            'requests' => $requests
        ]);

        $this->sheetsService->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
    }

    private function moveToFolder($spreadsheetId, $folderId)
    {
        try {
            // Get current parents
            $file = $this->driveService->files->get($spreadsheetId, ['fields' => 'parents']);
            $previousParents = join(',', $file->parents);

            // Move to new folder
            $this->driveService->files->update($spreadsheetId, new \Google\Service\Drive\DriveFile(), [
                'addParents' => $folderId,
                'removeParents' => $previousParents,
                'fields' => 'id, parents'
            ]);
        } catch (Exception $e) {
            // If moving fails, it's not critical
            error_log("Failed to move file to folder: " . $e->getMessage());
        }
    }

    private function makeShareable($spreadsheetId)
    {
        try {
            $permission = new \Google\Service\Drive\Permission();
            $permission->setRole('reader');
            $permission->setType('anyone');

            $this->driveService->permissions->create($spreadsheetId, $permission);
        } catch (Exception $e) {
            // If sharing fails, it's not critical
            error_log("Failed to make file shareable: " . $e->getMessage());
        }
    }

    public function prepareDataFromSuggestions($suggestions)
    {
        $data = [];

        // Check if suggestions is empty
        if (empty($suggestions)) {
            throw new Exception("No suggestions data provided for export.");
        }

        // Headers
        $data[] = [
            'ID',
            'Title',
            'Description',
            'Category',
            'Status',
            'Anonymous',
            'Author Name',
            'Author Email',
            'Upvotes',
            'EVSU Response',
            'Admin Name',
            'Created Date',
            'Updated Date'
        ];

        // Data rows - ensure all values are properly formatted
        foreach ($suggestions as $suggestion) {
            // Handle PostgreSQL boolean values properly
            $isAnonymous = ($suggestion['is_anonymous'] == 't' || $suggestion['is_anonymous'] === true);

            $row = [
                $this->sanitizeValue($suggestion['id'] ?? ''),
                $this->sanitizeValue($suggestion['title'] ?? ''),
                $this->sanitizeValue($suggestion['description'] ?? ''),
                $this->sanitizeValue($suggestion['category'] ?? ''),
                $this->sanitizeValue(ucfirst(str_replace('_', ' ', $suggestion['status'] ?? ''))),
                $isAnonymous ? 'Yes' : 'No',
                $this->sanitizeValue($suggestion['author_name'] ?? ''),
                $isAnonymous ? '' : $this->sanitizeValue($suggestion['author_email'] ?? ''),
                $this->sanitizeValue($suggestion['upvotes_count'] ?? '0'),
                $this->sanitizeValue($suggestion['admin_response'] ?? ''),
                $this->sanitizeValue($suggestion['admin_name'] ?? ''),
                $this->sanitizeValue($suggestion['created_at'] ? date('Y-m-d H:i:s', strtotime($suggestion['created_at'])) : ''),
                $this->sanitizeValue($suggestion['updated_at'] ? date('Y-m-d H:i:s', strtotime($suggestion['updated_at'])) : '')
            ];

            // Ensure this is a proper indexed array
            $data[] = array_values($row);
        }

        // Ensure all rows have the same number of columns
        $headerCount = count($data[0]);
        for ($i = 1; $i < count($data); $i++) {
            while (count($data[$i]) < $headerCount) {
                $data[$i][] = '';
            }
            // Trim any extra columns
            $data[$i] = array_slice($data[$i], 0, $headerCount);
        }

        return $data;
    }

    /**
     * Sanitize values for Google Sheets API
     */
    private function sanitizeValue($value)
    {
        // Handle null values
        if ($value === null || $value === false) {
            return '';
        }

        // Handle boolean values
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        // Convert to string and handle special characters
        $value = (string) $value;

        // Remove null bytes which can cause issues
        $value = str_replace("\0", '', $value);

        // Replace problematic characters that might break CSV/Sheets
        $value = str_replace(["\r\n", "\r", "\n"], ' ', $value);

        // Handle quotes and special characters
        $value = str_replace('"', '""', $value);

        // Trim whitespace
        $value = trim($value);

        // Ensure the value doesn't start with special characters that might be interpreted as formulas
        if (strlen($value) > 0 && in_array($value[0], ['=', '+', '-', '@'])) {
            $value = "'" . $value;
        }

        return $value;
    }

    public function isConfigured()
    {
        return GoogleSheetsConfig::credentialsExist();
    }

    /**
     * Validate data structure before sending to Google Sheets API
     */
    private function validateDataStructure($data)
    {
        if (!is_array($data)) {
            throw new Exception("Data must be an array");
        }

        if (empty($data)) {
            throw new Exception("Data array cannot be empty");
        }

        foreach ($data as $rowIndex => $row) {
            if (!is_array($row)) {
                throw new Exception("Row {$rowIndex} is not an array");
            }

            // Check for associative arrays which can cause issues
            if (array_keys($row) !== range(0, count($row) - 1)) {
                error_log("Warning: Row {$rowIndex} appears to be an associative array, converting to indexed array");
            }
        }
    }
}
