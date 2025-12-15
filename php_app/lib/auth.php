<?php
require_once 'database.php';
require_once 'utils.php';

class Auth {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function register($username, $email, $password, $role) {
        // Validate input
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            throw new Exception("All fields are required");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        if (strlen($password) < 6) {
            throw new Exception("Password must be at least 6 characters long");
        }

        if (!in_array($role, ['student', 'lecturer', 'admin'])) {
            throw new Exception("Invalid role selected");
        }

        // Check if user already exists
        $existingUser = $this->db->fetchOne(
            "SELECT id FROM users WHERE username = ? OR email = ?", 
            [$username, $email]
        );

        if ($existingUser) {
            throw new Exception("Username or email already exists");
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $this->db->query(
            "INSERT INTO users (username, email, password, role, is_verified) VALUES (?, ?, ?, ?, ?)",
            [$username, $email, $hashedPassword, $role, $role === 'student' ? 1 : 0]
        );

        $userId = $this->db->lastInsertId();

        // If lecturer, add to lecturers table for verification
        if ($role === 'lecturer') {
            $this->db->query(
                "INSERT INTO lecturers (user_id, institution, department, verification_status) VALUES (?, ?, ?, 'pending')",
                [$userId, 'Not specified', 'Not specified']
            );
        }

        return $userId;
    }

    public function login($username, $password) {
        // Find user
        $user = $this->db->fetchOne(
            "SELECT u.*, l.verification_status 
             FROM users u 
             LEFT JOIN lecturers l ON u.id = l.user_id 
             WHERE u.username = ? OR u.email = ?",
            [$username, $username]
        );

        if (!$user) {
            throw new Exception("Invalid username or password");
        }

        // Verify password
        if (!password_verify($password, $user['password'])) {
            throw new Exception("Invalid username or password");
        }

        // Check if lecturer is verified
        if ($user['role'] === 'lecturer' && $user['verification_status'] !== 'approved') {
            throw new Exception("Your lecturer account is pending verification by admin");
        }

        // Update session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_verified'] = $user['is_verified'];

        // Update last login
        $this->db->query(
            "UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = ?",
            [$user['id']]
        );

        return true;
    }

    public function logout() {
        session_destroy();
        session_start(); // Start fresh session for potential new login
    }

    public function getUser($userId) {
        return $this->db->fetchOne(
            "SELECT u.*, l.institution, l.department, l.staff_id, l.verification_status 
             FROM users u 
             LEFT JOIN lecturers l ON u.id = l.user_id 
             WHERE u.id = ?",
            [$userId]
        );
    }

    public function updateProfile($userId, $data) {
        $allowedFields = ['email'];
        $updates = [];
        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields)) {
                $updates[] = "$key = ?";
                $params[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $params[] = $userId;
        $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE id = ?";
        
        return $this->db->query($sql, $params);
    }

    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->db->fetchOne("SELECT password FROM users WHERE id = ?", [$userId]);
        
        if (!password_verify($currentPassword, $user['password'])) {
            throw new Exception("Current password is incorrect");
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        return $this->db->query(
            "UPDATE users SET password = ? WHERE id = ?",
            [$hashedPassword, $userId]
        );
    }

    public static function requireAuth() {
        if (!Utils::isLoggedIn()) {
            Utils::redirect('login.php');
        }
    }

    public static function requireRole($allowedRoles) {
        self::requireAuth();
        
        if (!in_array($_SESSION['role'], (array)$allowedRoles)) {
            $_SESSION['error'] = "Access denied. Insufficient permissions.";
            Utils::redirect('dashboard.php');
        }
    }

    public static function isLecturerVerified() {
        if ($_SESSION['role'] === 'lecturer' && !$_SESSION['is_verified']) {
            return false;
        }
        return true;
    }
}
?>