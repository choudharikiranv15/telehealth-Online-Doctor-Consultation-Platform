-- iHealth MediCare Database Schema
-- Create database
CREATE DATABASE IF NOT EXISTS telehealth_db;
USE telehealth_db;

-- Users table (for both doctors and patients)
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
    country VARCHAR(100),
    postal_code VARCHAR(20),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Doctor profiles table
CREATE TABLE doctor_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    specialization VARCHAR(100) NOT NULL,
    license_number VARCHAR(100) UNIQUE NOT NULL,
    experience_years INT NOT NULL,
    education TEXT,
    bio TEXT,
    consultation_fee DECIMAL(10,2) NOT NULL,
    languages TEXT,
    working_hours TEXT,
    available_days VARCHAR(100),
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_consultations INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Patient profiles table
CREATE TABLE patient_profiles (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    emergency_contact VARCHAR(20),
    medical_history TEXT,
    allergies TEXT,
    blood_group VARCHAR(5),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Appointments table
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    patient_id INT NOT NULL,
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    duration INT DEFAULT 30, -- in minutes
    status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    symptoms TEXT,
    notes TEXT,
    consultation_notes TEXT,
    payment_status ENUM('pending', 'completed', 'refunded') DEFAULT 'pending',
    payment_amount DECIMAL(10,2),
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_appointment (doctor_id, appointment_date, appointment_time)
);

-- Prescriptions table
CREATE TABLE prescriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    diagnosis TEXT NOT NULL,
    medications TEXT NOT NULL,
    dosage_instructions TEXT,
    follow_up_date DATE,
    follow_up_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

-- Video calls table
CREATE TABLE video_calls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    room_id VARCHAR(100) UNIQUE NOT NULL,
    start_time TIMESTAMP NULL,
    end_time TIMESTAMP NULL,
    status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    recording_url VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);

-- Admin users table
CREATE TABLE admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    permissions TEXT,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Specializations table
CREATE TABLE specializations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctor specializations (many-to-many relationship)
CREATE TABLE doctor_specializations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    specialization_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (specialization_id) REFERENCES specializations(id) ON DELETE CASCADE,
    UNIQUE KEY unique_doctor_specialization (doctor_id, specialization_id)
);

-- Time slots table
CREATE TABLE time_slots (
    id INT PRIMARY KEY AUTO_INCREMENT,
    doctor_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    slot_duration INT DEFAULT 30, -- in minutes
    is_available BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default admin user
INSERT INTO users (username, email, password, role, first_name, last_name, phone, status) VALUES
('admin', 'admin@telehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', '+1234567890', 'active');

INSERT INTO admin_users (user_id, permissions) VALUES (1, 'all');

-- Insert sample doctor
INSERT INTO users (username, email, password, role, first_name, last_name, phone, address, city, state, country, postal_code, status) VALUES
('doctor1', 'doctor@telehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'doctor', 'Dr. John', 'Smith', '+1234567891', '123 Medical Center Dr', 'New York', 'NY', 'USA', '10001', 'active');

INSERT INTO doctor_profiles (user_id, specialization, license_number, experience_years, education, bio, consultation_fee, languages, working_hours, available_days, rating, total_consultations) VALUES
(2, 'Cardiology', 'MD12345', 15, 'Harvard Medical School, MD in Cardiology', 'Experienced cardiologist with expertise in heart diseases and preventive care.', 150.00, 'English, Spanish', '9:00 AM - 5:00 PM', 'monday,tuesday,wednesday,thursday,friday', 4.8, 1250);

-- Insert sample patient
INSERT INTO users (username, email, password, role, first_name, last_name, phone, address, city, state, country, postal_code, status) VALUES
('patient1', 'patient@telehealth.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'patient', 'Jane', 'Doe', '+1234567892', '456 Health Ave', 'New York', 'NY', 'USA', '10002', 'active');

INSERT INTO patient_profiles (user_id, date_of_birth, gender, emergency_contact, medical_history, allergies, blood_group) VALUES
(3, '1990-05-15', 'female', '+1234567893', 'Hypertension', 'Penicillin', 'O+');

-- Insert sample specializations
INSERT INTO specializations (name, description) VALUES
('Cardiology', 'Heart and cardiovascular system'),
('Dermatology', 'Skin, hair, and nail conditions'),
('Neurology', 'Nervous system disorders'),
('Orthopedics', 'Bones, joints, and muscles'),
('Pediatrics', 'Child and adolescent health'),
('Psychiatry', 'Mental health and behavioral disorders'),
('Oncology', 'Cancer treatment and care'),
('Gynecology', 'Women\'s reproductive health');

-- Insert sample time slots for doctor
INSERT INTO time_slots (doctor_id, day_of_week, start_time, end_time, slot_duration) VALUES
(2, 'monday', '09:00:00', '17:00:00', 30),
(2, 'tuesday', '09:00:00', '17:00:00', 30),
(2, 'wednesday', '09:00:00', '17:00:00', 30),
(2, 'thursday', '09:00:00', '17:00:00', 30),
(2, 'friday', '09:00:00', '17:00:00', 30);

-- Insert sample appointment
INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, duration, status, symptoms, payment_status, payment_amount) VALUES
(3, 2, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '10:00:00', 30, 'confirmed', 'Chest pain and shortness of breath', 'completed', 150.00);

-- Insert sample prescription
INSERT INTO prescriptions (appointment_id, diagnosis, medications, dosage_instructions, follow_up_date, follow_up_notes) VALUES
(1, 'Angina pectoris', 'Nitroglycerin 0.4mg sublingual', 'Take 1 tablet under tongue when chest pain occurs. Maximum 3 tablets in 15 minutes.', DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'Follow up for stress test and further evaluation.');

-- Create indexes for better performance
CREATE INDEX idx_appointments_doctor_date ON appointments(doctor_id, appointment_date);
CREATE INDEX idx_appointments_patient ON appointments(patient_id);
CREATE INDEX idx_appointments_status ON appointments(status);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_doctor_profiles_specialization ON doctor_profiles(specialization);
CREATE INDEX idx_doctor_profiles_city ON doctor_profiles(user_id);
CREATE INDEX idx_time_slots_doctor_day ON time_slots(doctor_id, day_of_week);
