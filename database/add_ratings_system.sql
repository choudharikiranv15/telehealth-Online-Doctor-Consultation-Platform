-- ===================================================================
-- ADD DOCTOR RATINGS SYSTEM
-- Migration script to add/verify ratings table and update doctor profiles
-- ===================================================================

USE telehealth_db;

-- Check if reviews table exists, if not create it
CREATE TABLE IF NOT EXISTS reviews (
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
    INDEX idx_rating (rating),
    INDEX idx_patient (patient_id),
    INDEX idx_created (created_at)
);

-- Ensure doctor_profiles has rating columns
-- (These should already exist, but we'll add them if they don't)
ALTER TABLE doctor_profiles
ADD COLUMN IF NOT EXISTS rating DECIMAL(3,2) DEFAULT 0.00 COMMENT 'Average rating (0.00-5.00)',
ADD COLUMN IF NOT EXISTS total_reviews INT DEFAULT 0 COMMENT 'Total number of reviews',
ADD INDEX IF NOT EXISTS idx_rating (rating);

-- Create a trigger to automatically update doctor ratings when a review is added
DELIMITER $$

DROP TRIGGER IF EXISTS after_review_insert$$
CREATE TRIGGER after_review_insert
AFTER INSERT ON reviews
FOR EACH ROW
BEGIN
    UPDATE doctor_profiles
    SET
        rating = (SELECT AVG(rating) FROM reviews WHERE doctor_id = NEW.doctor_id),
        total_reviews = (SELECT COUNT(*) FROM reviews WHERE doctor_id = NEW.doctor_id)
    WHERE user_id = NEW.doctor_id;
END$$

-- Create a trigger to update doctor ratings when a review is updated
DROP TRIGGER IF EXISTS after_review_update$$
CREATE TRIGGER after_review_update
AFTER UPDATE ON reviews
FOR EACH ROW
BEGIN
    UPDATE doctor_profiles
    SET
        rating = (SELECT AVG(rating) FROM reviews WHERE doctor_id = NEW.doctor_id),
        total_reviews = (SELECT COUNT(*) FROM reviews WHERE doctor_id = NEW.doctor_id)
    WHERE user_id = NEW.doctor_id;
END$$

-- Create a trigger to update doctor ratings when a review is deleted
DROP TRIGGER IF EXISTS after_review_delete$$
CREATE TRIGGER after_review_delete
AFTER DELETE ON reviews
FOR EACH ROW
BEGIN
    UPDATE doctor_profiles
    SET
        rating = COALESCE((SELECT AVG(rating) FROM reviews WHERE doctor_id = OLD.doctor_id), 0.00),
        total_reviews = (SELECT COUNT(*) FROM reviews WHERE doctor_id = OLD.doctor_id)
    WHERE user_id = OLD.doctor_id;
END$$

DELIMITER ;

-- Initialize existing doctor ratings (if any reviews exist)
UPDATE doctor_profiles dp
SET
    rating = COALESCE((SELECT AVG(rating) FROM reviews WHERE doctor_id = dp.user_id), 0.00),
    total_reviews = (SELECT COUNT(*) FROM reviews WHERE doctor_id = dp.user_id);

-- Success message
SELECT 'Doctor ratings system tables and triggers created/updated successfully!' as message;
