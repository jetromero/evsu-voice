<?php
session_start();
require_once __DIR__ . '/../config/database_native.php';

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
            if ($password === $user['password']) {
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

        // Insert user with plain text password
        $query = "INSERT INTO users (email, password, first_name, last_name) VALUES ($1, $2, $3, $4)";
        $result = $this->database->query($query, [$email, $password, $first_name, $last_name]);

        if ($result) {
            return ['success' => true, 'message' => 'Registration successful'];
        }

        return ['success' => false, 'message' => 'Registration failed'];
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
