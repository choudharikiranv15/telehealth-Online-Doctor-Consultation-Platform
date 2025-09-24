<?php
require_once '../config.php';
require_once '../includes/db_connect.php';

class ProfileController {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Get user profile
     */
    public function getUserProfile($user_id, $role) {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*, 
                       CASE 
                           WHEN u.role = 'doctor' THEN s.name
                           WHEN u.role = 'patient' THEN pp.date_of_birth
                           ELSE NULL
                       END as profile_data
                FROM users u
                LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
                LEFT JOIN specializations s ON dp.specialization_id = s.id
                LEFT JOIN patient_profiles pp ON u.id = pp.user_id
                WHERE u.id = ?
            ");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch();
            
            if ($profile) {
                // Get additional profile data based on role
                if ($role === 'doctor') {
                    $stmt = $this->db->prepare("
                        SELECT * FROM doctor_profiles WHERE user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                    $doctorProfile = $stmt->fetch();
                    $profile = array_merge($profile, $doctorProfile ?: []);
                } elseif ($role === 'patient') {
                    $stmt = $this->db->prepare("
                        SELECT * FROM patient_profiles WHERE user_id = ?
                    ");
                    $stmt->execute([$user_id]);
                    $patientProfile = $stmt->fetch();
                    $profile = array_merge($profile, $patientProfile ?: []);
                }
                
                return [
                    'success' => true,
                    'profile' => $profile
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Profile not found'
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
     * Update user profile
     */
    public function updateProfile($user_id, $role, $data) {
        try {
            $this->db->beginTransaction();
            
            // Update basic user information
            $stmt = $this->db->prepare("
                UPDATE users 
                SET first_name = ?, last_name = ?, phone = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['phone'] ?? null,
                $user_id
            ]);
            
            // Update role-specific profile
            if ($role === 'doctor') {
                $this->updateDoctorProfile($user_id, $data);
            } elseif ($role === 'patient') {
                $this->updatePatientProfile($user_id, $data);
            }
            
            $this->db->commit();
            
            return [
                'success' => true,
                'message' => 'Profile updated successfully'
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
     * Update doctor profile
     */
    private function updateDoctorProfile($user_id, $data) {
        // Check if profile exists
        $stmt = $this->db->prepare("SELECT id FROM doctor_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing profile
            $stmt = $this->db->prepare("
                UPDATE doctor_profiles 
                SET specialization = ?, experience_years = ?, qualification = ?, bio = ?, 
                    consultation_fee = ?, available_days = ?, available_hours_start = ?, 
                    available_hours_end = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['specialization'] ?? null,
                $data['experience_years'] ?? null,
                $data['qualification'] ?? null,
                $data['bio'] ?? null,
                $data['consultation_fee'] ?? null,
                $data['available_days'] ?? null,
                $data['available_hours_start'] ?? null,
                $data['available_hours_end'] ?? null,
                $user_id
            ]);
        } else {
            // Create new profile
            $stmt = $this->db->prepare("
                INSERT INTO doctor_profiles 
                (user_id, specialization, experience_years, qualification, bio, consultation_fee, 
                 available_days, available_hours_start, available_hours_end)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $data['specialization'] ?? null,
                $data['experience_years'] ?? null,
                $data['qualification'] ?? null,
                $data['bio'] ?? null,
                $data['consultation_fee'] ?? null,
                $data['available_days'] ?? null,
                $data['available_hours_start'] ?? null,
                $data['available_hours_end'] ?? null
            ]);
        }
    }
    
    /**
     * Update patient profile
     */
    private function updatePatientProfile($user_id, $data) {
        // Check if profile exists
        $stmt = $this->db->prepare("SELECT id FROM patient_profiles WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            // Update existing profile
            $stmt = $this->db->prepare("
                UPDATE patient_profiles 
                SET date_of_birth = ?, gender = ?, address = ?, emergency_contact = ?, 
                    emergency_phone = ?, medical_history = ?, allergies = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $data['date_of_birth'] ?? null,
                $data['gender'] ?? null,
                $data['address'] ?? null,
                $data['emergency_contact'] ?? null,
                $data['emergency_phone'] ?? null,
                $data['medical_history'] ?? null,
                $data['allergies'] ?? null,
                $user_id
            ]);
        } else {
            // Create new profile
            $stmt = $this->db->prepare("
                INSERT INTO patient_profiles 
                (user_id, date_of_birth, gender, address, emergency_contact, 
                 emergency_phone, medical_history, allergies)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $data['date_of_birth'] ?? null,
                $data['gender'] ?? null,
                $data['address'] ?? null,
                $data['emergency_contact'] ?? null,
                $data['emergency_phone'] ?? null,
                $data['medical_history'] ?? null,
                $data['allergies'] ?? null
            ]);
        }
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
     * Upload profile picture
     */
    public function uploadProfilePicture($user_id, $file) {
        try {
            $upload_dir = '../assets/images/profiles/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Validate file
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($file['type'], $allowed_types)) {
                return [
                    'success' => false,
                    'message' => 'Invalid file type. Only JPEG, PNG, and GIF are allowed.'
                ];
            }
            
            if ($file['size'] > MAX_FILE_SIZE) {
                return [
                    'success' => false,
                    'message' => 'File size too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.'
                ];
            }
            
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
            $filepath = $upload_dir . $filename;
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // Update database
                $stmt = $this->db->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
                $stmt->execute(['assets/images/profiles/' . $filename, $user_id]);
                
                return [
                    'success' => true,
                    'message' => 'Profile picture uploaded successfully',
                    'filename' => 'assets/images/profiles/' . $filename
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to upload file'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error uploading file: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete profile picture
     */
    public function deleteProfilePicture($user_id) {
        try {
            // Get current profile picture
            $stmt = $this->db->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if ($user && $user['profile_picture']) {
                // Delete file
                $filepath = '../' . $user['profile_picture'];
                if (file_exists($filepath)) {
                    unlink($filepath);
                }
                
                // Update database
                $stmt = $this->db->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
                $stmt->execute([$user_id]);
                
                return [
                    'success' => true,
                    'message' => 'Profile picture deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'No profile picture found'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error deleting profile picture: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get profile statistics
     */
    public function getProfileStats($user_id, $role) {
        try {
            $stats = [];
            
            if ($role === 'doctor') {
                // Get appointment statistics
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as total_appointments,
                           COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_appointments,
                           COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_appointments
                    FROM appointments WHERE doctor_id = ?
                ");
                $stmt->execute([$user_id]);
                $appointmentStats = $stmt->fetch();
                
                $stats['appointments'] = $appointmentStats;
                
                // Get patient count
                $stmt = $this->db->prepare("
                    SELECT COUNT(DISTINCT patient_id) as total_patients
                    FROM appointments WHERE doctor_id = ?
                ");
                $stmt->execute([$user_id]);
                $patientStats = $stmt->fetch();
                
                $stats['patients'] = $patientStats;
                
            } elseif ($role === 'patient') {
                // Get appointment statistics
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as total_appointments,
                           COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_appointments,
                           COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_appointments
                    FROM appointments WHERE patient_id = ?
                ");
                $stmt->execute([$user_id]);
                $appointmentStats = $stmt->fetch();
                
                $stats['appointments'] = $appointmentStats;
                
                // Get prescription count
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) as total_prescriptions
                    FROM prescriptions p
                    JOIN appointments a ON p.appointment_id = a.id
                    WHERE a.patient_id = ?
                ");
                $stmt->execute([$user_id]);
                $prescriptionStats = $stmt->fetch();
                
                $stats['prescriptions'] = $prescriptionStats;
            }
            
            return [
                'success' => true,
                'stats' => $stats
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
$profile = new ProfileController($db);
?>
