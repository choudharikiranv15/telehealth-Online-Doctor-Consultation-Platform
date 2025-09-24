-- ===================================================================
-- FIXED IHEALTH MEDICARE DATABASE SCHEMA
-- Designed for Online Doctor Consultation with Video Call
-- Supports: Admin, Doctor, Patient roles with proper relationships
-- ===================================================================

-- Drop existing database and recreate (Fixed approach)
DROP DATABASE IF EXISTS telehealth_db;
CREATE DATABASE telehealth_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE telehealth_db;

-- ===================================================================
-- 1. USERS TABLE (Core authentication and user management)
-- ===================================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'doctor', 'patient') NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    phone VARCHAR(20),
    profile_picture VARCHAR(255),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100) DEFAULT 'India',
    postal_code VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_email (email)
);

-- ===================================================================
-- 2. SPECIALIZATIONS TABLE (Medical specializations)
-- ===================================================================
CREATE TABLE specializations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'fas fa-stethoscope',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ===================================================================
-- 3. DOCTOR PROFILES TABLE (Extended info for doctors)
-- ===================================================================
CREATE TABLE doctor_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    specialization_id INT NOT NULL,
    license_number VARCHAR(100) UNIQUE NOT NULL,
    experience_years INT DEFAULT 0,
    qualification VARCHAR(500) NOT NULL,
    consultation_fee DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    bio TEXT,
    languages VARCHAR(200) DEFAULT 'English, Hindi',
    availability_start TIME DEFAULT '09:00:00',
    availability_end TIME DEFAULT '18:00:00',
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_reviews INT DEFAULT 0,
    clinic_name VARCHAR(200),
    clinic_address TEXT,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE RESTRICT,
    INDEX idx_specialization (specialization_id),
    INDEX idx_rating (rating),
    INDEX idx_fee (consultation_fee)
);

-- ===================================================================
-- 4. PATIENT PROFILES TABLE (Extended info for patients)
-- ===================================================================
CREATE TABLE patient_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    emergency_contact_name VARCHAR(100),
    emergency_contact_phone VARCHAR(20),
    blood_group ENUM('A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'),
    medical_history TEXT,
    allergies TEXT,
    current_medications TEXT,
    insurance_provider VARCHAR(100),
    insurance_number VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ===================================================================
-- 5. APPOINTMENTS TABLE (Core booking system)
-- ===================================================================
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration INT DEFAULT 30,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    booking_type ENUM('consultation', 'follow_up', 'emergency') DEFAULT 'consultation',
    symptoms TEXT NOT NULL,
    notes TEXT,
    doctor_notes TEXT,
    consultation_fee DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'completed', 'refunded', 'failed') DEFAULT 'pending',
    payment_method ENUM('online', 'upi', 'card', 'cash') DEFAULT 'online',
    payment_amount DECIMAL(10,2),
    transaction_id VARCHAR(100),
    cancellation_reason TEXT,
    cancelled_by ENUM('patient', 'doctor', 'admin'),
    cancelled_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_appointment (doctor_id, appointment_date, appointment_time),
    INDEX idx_patient (patient_id),
    INDEX idx_doctor (doctor_id),
    INDEX idx_date (appointment_date),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status)
);

-- ===================================================================
-- 6. VIDEO CALLS TABLE (Jitsi integration)
-- ===================================================================
CREATE TABLE video_calls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT UNIQUE NOT NULL,
    room_id VARCHAR(100) UNIQUE NOT NULL,
    meeting_url VARCHAR(500),
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    duration_minutes INT DEFAULT 0,
    status ENUM('scheduled', 'active', 'completed', 'failed') DEFAULT 'scheduled',
    recording_url VARCHAR(500),
    participants_joined TEXT,
    quality_rating INT DEFAULT 0,
    technical_issues TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_room_id (room_id)
);

-- ===================================================================
-- 7. PRESCRIPTIONS TABLE (Medical prescriptions)
-- ===================================================================
CREATE TABLE prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    prescription_number VARCHAR(50) UNIQUE NOT NULL,
    diagnosis TEXT NOT NULL,
    medications TEXT NOT NULL,
    dosage_instructions TEXT,
    precautions TEXT,
    follow_up_date DATE,
    follow_up_instructions TEXT,
    valid_until DATE,
    is_digital_signature BOOLEAN DEFAULT TRUE,
    prescription_pdf_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    INDEX idx_prescription_number (prescription_number),
    INDEX idx_appointment (appointment_id)
);

-- ===================================================================
-- 8. REVIEWS TABLE (Doctor ratings and feedback)
-- ===================================================================
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review_text TEXT,
    is_anonymous BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT TRUE,
    helpful_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_doctor_rating (doctor_id, rating),
    INDEX idx_rating (rating)
);

-- ===================================================================
-- 9. NOTIFICATIONS TABLE (System notifications)
-- ===================================================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type ENUM('appointment', 'payment', 'prescription', 'reminder', 'system') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    is_email_sent BOOLEAN DEFAULT FALSE,
    is_sms_sent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_unread (user_id, is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at)
);

-- ===================================================================
-- 10. SYSTEM SETTINGS TABLE (App configuration)
-- ===================================================================
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_key (setting_key),
    INDEX idx_public (is_public)
);

-- ===================================================================
-- INSERT INITIAL DATA
-- ===================================================================

-- Insert Specializations first
INSERT INTO specializations (name, description, icon) VALUES
('General Medicine', 'General health consultation and primary care', 'fas fa-stethoscope'),
('Cardiology', 'Heart and cardiovascular system specialist', 'fas fa-heart'),
('Dermatology', 'Skin, hair, and nail conditions specialist', 'fas fa-hand'),
('Pediatrics', 'Children healthcare specialist', 'fas fa-baby'),
('Orthopedics', 'Bone, joint, and muscle specialist', 'fas fa-bone'),
('Gynecology', 'Women''s health and reproductive system specialist', 'fas fa-female'),
('Psychiatry', 'Mental health and psychological disorders specialist', 'fas fa-brain'),
('ENT', 'Ear, Nose, and Throat specialist', 'fas fa-head-side-mask'),
('Neurology', 'Brain and nervous system specialist', 'fas fa-brain'),
('Ophthalmology', 'Eye and vision specialist', 'fas fa-eye');

-- Insert System Settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('site_name', 'TeleHealth Platform', 'string', 'Application name', TRUE),
('site_tagline', 'Your Health, Our Priority', 'string', 'Site tagline', TRUE),
('consultation_fee_min', '200', 'number', 'Minimum consultation fee', FALSE),
('consultation_fee_max', '2000', 'number', 'Maximum consultation fee', FALSE),
('appointment_slot_duration', '30', 'number', 'Default appointment duration in minutes', FALSE),
('working_hours_start', '09:00', 'string', 'Default working hours start', FALSE),
('working_hours_end', '18:00', 'string', 'Default working hours end', FALSE),
('max_appointments_per_day', '20', 'number', 'Maximum appointments per doctor per day', FALSE),
('video_call_provider', 'jitsi', 'string', 'Video call service provider', FALSE),
('enable_online_payment', 'true', 'boolean', 'Enable online payment gateway', FALSE),
('require_email_verification', 'false', 'boolean', 'Require email verification for new users', FALSE);

-- Insert Admin User
INSERT INTO users (username, email, password, role, first_name, last_name, phone, status) VALUES
('admin', 'admin@telehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', '+91-9876543210', 'active');

-- Insert Sample Doctors
INSERT INTO users (username, email, password, role, first_name, last_name, phone, city, gender, status) VALUES
('dr.sharma', 'dr.sharma@telehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Rajesh', 'Sharma', '+91-9876543211', 'Mumbai', 'male', 'active'),
('dr.priya', 'dr.priya@telehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Priya', 'Singh', '+91-9876543212', 'Delhi', 'female', 'active'),
('dr.patel', 'dr.patel@telehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Amit', 'Patel', '+91-9876543213', 'Ahmedabad', 'male', 'active'),
('dr.kumar', 'dr.kumar@telehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Sunita', 'Kumar', '+91-9876543214', 'Bangalore', 'female', 'active'),
('dr.jasprit', 'dr.jasprit@telehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Jasprit', 'Singh', '+91-9876543215', 'Chennai', 'male', 'active');

-- Insert Sample Patients
INSERT INTO users (username, email, password, role, first_name, last_name, phone, city, gender, status) VALUES
('abhi.kumar', 'abhi@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Abhi', 'Kumar', '+91-9876543216', 'Mumbai', 'male', 'active'),
('virat.kohli', 'virat@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Virat', 'Kohli', '+91-9876543217', 'Delhi', 'male', 'active'),
('anushka.sharma', 'anushka@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Anushka', 'Sharma', '+91-9876543218', 'Mumbai', 'female', 'active');

-- Insert Doctor Profiles
INSERT INTO doctor_profiles (user_id, specialization_id, license_number, experience_years, qualification, consultation_fee, bio, languages, rating, total_reviews, is_verified) VALUES
(2, 1, 'MED001', 10, 'MBBS, MD (General Medicine)', 600.00, 'Experienced general practitioner with 10 years of clinical experience. Specialized in treating common health conditions and preventive care.', 'English, Hindi, Marathi', 4.5, 120, TRUE),
(3, 2, 'MED002', 8, 'MBBS, DM (Cardiology)', 1200.00, 'Cardiologist with expertise in heart conditions, ECG interpretation, and cardiovascular health management.', 'English, Hindi', 4.7, 95, TRUE),
(4, 3, 'MED003', 6, 'MBBS, MD (Dermatology)', 800.00, 'Dermatologist specializing in skin conditions, acne treatment, and cosmetic dermatology.', 'English, Hindi, Gujarati', 4.3, 78, TRUE),
(5, 4, 'MED004', 12, 'MBBS, MD (Pediatrics)', 700.00, 'Pediatrician with 12 years of experience in child healthcare, vaccinations, and developmental issues.', 'English, Hindi, Tamil', 4.8, 150, TRUE),
(6, 1, 'MED005', 5, 'MBBS, MS (General Surgery)', 900.00, 'General practitioner with surgical experience. Provides comprehensive healthcare services.', 'English, Hindi, Punjabi', 4.4, 62, TRUE);

-- Insert Patient Profiles
INSERT INTO patient_profiles (user_id, emergency_contact_name, emergency_contact_phone, blood_group) VALUES
(7, 'Rohit Kumar', '+91-9876543220', 'B+'),
(8, 'Anushka Sharma', '+91-9876543221', 'A+'),
(9, 'Virat Kohli', '+91-9876543222', 'O+');

-- ===================================================================
-- CREATE INDEXES FOR PERFORMANCE (Fixed version)
-- ===================================================================

-- Composite indexes for common queries
CREATE INDEX idx_appointments_doctor_date ON appointments(doctor_id, appointment_date);
CREATE INDEX idx_appointments_patient_status ON appointments(patient_id, status);
CREATE INDEX idx_appointments_date_status ON appointments(appointment_date, status);

-- Text search indexes (only on existing columns)
CREATE FULLTEXT INDEX idx_users_name_search ON users(first_name, last_name);
CREATE FULLTEXT INDEX idx_doctor_bio_search ON doctor_profiles(bio);