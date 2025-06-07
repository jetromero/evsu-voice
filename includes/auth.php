<?php
session_start();
require_once __DIR__ . '/../config/database_native.php';
require_once __DIR__ . '/../config/supabase_sync.php';

class Auth
{
    private $database;

    public function __construct()
    {
        $this->database = new DatabaseNative();
        $this->database->getConnection();
    }

    public function login($email, $password)
    {
        // Validate EVSU email
        if (!$this->isValidEVSUEmail($email)) {
            return ['success' => false, 'message' => 'Please use your EVSU email address (@evsu.edu.ph)'];
        }

        $query = "SELECT id, email, password, first_name, last_name, role FROM users WHERE email = $1";
        $result = $this->database->query($query, [$email]);

        if ($this->database->numRows($result) == 1) {
            $user = $this->database->fetchAssoc($result);

            // Use password_verify for secure password checking
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['user_role'] = $user['role'];
                return ['success' => true, 'user' => $user];
            }
        }

        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    public function register($email, $password, $first_name, $last_name)
    {
        // Validate EVSU email
        if (!$this->isValidEVSUEmail($email)) {
            return ['success' => false, 'message' => 'Please use your EVSU email address (@evsu.edu.ph)'];
        }

        // Check if user already exists
        $query = "SELECT id FROM users WHERE email = $1";
        $result = $this->database->query($query, [$email]);

        if ($this->database->numRows($result) > 0) {
            return ['success' => false, 'message' => 'Email already registered'];
        }

        // Hash the password before storing
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user with hashed password
        $query = "INSERT INTO users (email, password, first_name, last_name) VALUES ($1, $2, $3, $4)";
        $result = $this->database->query($query, [$email, $hashed_password, $first_name, $last_name]);

        if ($result) {
            return ['success' => true, 'message' => 'Registration successful'];
        }

        return ['success' => false, 'message' => 'Registration failed'];
    }

    public function changePassword($user_id, $current_password, $new_password)
    {
        // Get current user's password
        $query = "SELECT password FROM users WHERE id = $1";
        $result = $this->database->query($query, [$user_id]);

        if ($this->database->numRows($result) != 1) {
            return ['success' => false, 'message' => 'User not found'];
        }

        $user = $this->database->fetchAssoc($result);

        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }

        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password
        $update_query = "UPDATE users SET password = $1 WHERE id = $2";
        $update_result = $this->database->query($update_query, [$hashed_password, $user_id]);

        if ($update_result) {
            // Try to sync to remote Supabase database
            $supabaseSync = new SupabaseSync();
            $syncResult = $supabaseSync->syncPasswordChange($user_id, $hashed_password);

            if ($syncResult['success']) {
                // Both local and remote updates successful
                return ['success' => true, 'message' => 'Password changed successfully and synced to remote database'];
            } else {
                // Local update successful but remote sync failed
                error_log("Supabase sync failed for user $user_id: " . $syncResult['message']);
                return [
                    'success' => true,
                    'message' => 'Password changed successfully locally, but remote sync failed: ' . $syncResult['message']
                ];
            }
        }

        return ['success' => false, 'message' => 'Failed to update password'];
    }

    public function adminChangePassword($user_id, $new_password)
    {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password in local database (admin bypass current password verification)
        $query = "UPDATE users SET password = $1 WHERE id = $2";
        $result = $this->database->query($query, [$hashed_password, $user_id]);

        if ($result) {
            // Try to sync to remote Supabase database
            $supabaseSync = new SupabaseSync();
            $syncResult = $supabaseSync->syncPasswordChange($user_id, $hashed_password);

            if ($syncResult['success']) {
                // Both local and remote updates successful
                return ['success' => true, 'message' => 'Password changed successfully and synced to remote database'];
            } else {
                // Local update successful but remote sync failed
                error_log("Supabase sync failed for user $user_id: " . $syncResult['message']);
                return [
                    'success' => true,
                    'message' => 'Password changed successfully locally, but remote sync failed: ' . $syncResult['message']
                ];
            }
        }

        return ['success' => false, 'message' => 'Failed to update password'];
    }

    public function logout()
    {
        session_destroy();
        header('Location: index.php');
        exit();
    }

    public function isLoggedIn()
    {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin()
    {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    public function requireLogin()
    {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
    }

    public function requireAdmin()
    {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            header('Location: index.php');
            exit();
        }
    }

    private function isValidEVSUEmail($email)
    {
        return str_ends_with(strtolower($email), '@evsu.edu.ph');
    }

    public function getCurrentUser()
    {
        if ($this->isLoggedIn()) {
            return [
                'id' => $_SESSION['user_id'],
                'email' => $_SESSION['user_email'],
                'name' => $_SESSION['user_name'],
                'role' => $_SESSION['user_role']
            ];
        }
        return null;
    }
}
