<?php

class SupabaseSync
{
    private $supabase_url = 'https://tlpllfglbtjxjwdvqxmc.supabase.co';
    private $supabase_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRscGxsZmdsYnRqeGp3ZHZxeG1jIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc0ODUzNzQxNywiZXhwIjoyMDY0MTEzNDE3fQ.C0aXhl7u8dfTJPXvtu7i9KGJpfJKBWxfvAqnMYmBH2Q';

    /**
     * Sync password change to remote Supabase database
     * 
     * @param int $user_id The user ID
     * @param string $hashed_password The new hashed password
     * @return array Result of the sync operation
     */
    public function syncPasswordChange($user_id, $hashed_password)
    {
        try {
            // Prepare the data for update
            $data = [
                'password' => $hashed_password
            ];

            // Initialize cURL
            $ch = curl_init();

            // Set cURL options
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->supabase_url . '/rest/v1/users?id=eq.' . $user_id,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'PATCH',
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'apikey: ' . $this->supabase_key,
                    'Authorization: Bearer ' . $this->supabase_key,
                    'Prefer: return=minimal'
                ],
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            // Execute the request
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);

            curl_close($ch);

            // Check for cURL errors
            if ($curl_error) {
                error_log("Supabase sync cURL error: " . $curl_error);
                return [
                    'success' => false,
                    'message' => 'Network error during sync: ' . $curl_error
                ];
            }

            // Check HTTP response code
            if ($http_code >= 200 && $http_code < 300) {
                return [
                    'success' => true,
                    'message' => 'Password synced to remote database successfully'
                ];
            } else {
                error_log("Supabase sync HTTP error: " . $http_code . " - Response: " . $response);
                return [
                    'success' => false,
                    'message' => 'Remote sync failed with HTTP code: ' . $http_code
                ];
            }

        } catch (Exception $e) {
            error_log("Supabase sync exception: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Exception during sync: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test connection to remote Supabase database
     * 
     * @return array Result of the connection test
     */
    public function testConnection()
    {
        try {
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $this->supabase_url . '/rest/v1/users?limit=1',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'apikey: ' . $this->supabase_key,
                    'Authorization: Bearer ' . $this->supabase_key
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);

            curl_close($ch);

            if ($curl_error) {
                return [
                    'success' => false,
                    'message' => 'Connection test failed: ' . $curl_error
                ];
            }

            if ($http_code >= 200 && $http_code < 300) {
                return [
                    'success' => true,
                    'message' => 'Connection to remote database successful'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Connection test failed with HTTP code: ' . $http_code
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection test exception: ' . $e->getMessage()
            ];
        }
    }
} 