<?php
require_once '../config.php';
require_once '../includes/db_connect.php';

class AuthController {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Authenticate user login
     */
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, password, role, first_name, last_name, email 
                FROM users 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Check if doctor is pending approval
                if ($user['role'] === 'doctor' && $user['status'] === 'pending_approval') {
                    return [
                        'success' => false,
                        'message' => 'Your doctor profile is pending admin approval. You will be notified once approved.'
                    ];
                }

                // Check if user account is inactive
                if ($user['status'] === 'inactive') {
                    return [
                        'success' => false,
                        'message' => 'Your account has been deactivated. Please contact support for assistance.'
                    ];
                }

                // Check if user is suspended
                if ($user['status'] === 'suspended') {
                    return [
                        'success' => false,
                        'message' => 'Your account has been suspended. Please contact support for assistance.'
                    ];
                }

                // Set session variables for active users
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['first_name'] = $user['first_name'];
                $_SESSION['last_name'] = $user['last_name'];
                $_SESSION['email'] = $user['email'];

                return [
                    'success' => true,
                    'user' => $user,
                    'message' => 'Login successful'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Invalid username/email or password'
                ];
            }
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Register new user
     */
    public function register($userData) {
        try {
            // Check if username or email already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$userData['username'], $userData['email']]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'Username or email already exists'
                ];
            }
            
            // Hash password
            $hashed_password = password_hash($userData['password'], PASSWORD_DEFAULT);
            
            $this->db->beginTransaction();
            
            // Insert user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, email, password, role, first_name, last_name, phone)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $userData['username'],
                $userData['email'],
                $hashed_password,
                $userData['role'],
                $userData['first_name'],
                $userData['last_name'],
                $userData['phone'] ?? null
            ]);
            
            $user_id = $this->db->lastInsertId();
            
            // Create profile based on role
            if ($userData['role'] === 'doctor') {
                $stmt = $this->db->prepare("
                    INSERT INTO doctor_profiles (user_id, specialization, license_number, experience_years, qualification, bio, consultation_fee)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $userData['specialization'] ?? null,
                    $userData['license_number'] ?? null,
                    $userData['experience_years'] ?? null,
                    $userData['qualification'] ?? null,
                    $userData['bio'] ?? null,
                    $userData['consultation_fee'] ?? null
                ]);
            } elseif ($userData['role'] === 'patient') {
                $stmt = $this->db->prepare("
                    INSERT INTO patient_profiles (user_id, date_of_birth, gender, address, emergency_contact, emergency_phone)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    $userData['date_of_birth'] ?? null,
                    $userData['gender'] ?? null,
                    $userData['address'] ?? null,
                    $userData['emergency_contact'] ?? null,
                    $userData['emergency_phone'] ?? null
                ]);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'user_id' => $user_id,
                'message' => 'Registration successful'
            ];
            
        } catch (PDOException $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Logout user
     */
    public function logout() {
        session_destroy();
        return [
            'success' => true,
            'message' => 'Logout successful'
        ];
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    /**
     * Check if user has specific role
     */
    public function hasRole($role) {
        return $this->isLoggedIn() && $_SESSION['role'] === $role;
    }
    
    /**
     * Get current user data
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role'],
            'first_name' => $_SESSION['first_name'],
            'last_name' => $_SESSION['last_name'],
            'email' => $_SESSION['email']
        ];
    }
    
    /**
     * Change password
     */
    public function changePassword($user_id, $current_password, $new_password) {
        try {
            // Verify current password
            $stmt = $this->db->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                return [
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ];
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Reset password (forgot password functionality)
     */
    public function resetPassword($email) {
        try {
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Email not found'
                ];
            }
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store reset token (you'll need to create a password_resets table)
            // For now, we'll just return success
            // In a real application, you'd store this token and send an email
            
            return [
                'success' => true,
                'message' => 'Password reset instructions sent to your email',
                'token' => $token // In production, don't return this
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
}

// Initialize controller
$auth = new AuthController($db);
?>
