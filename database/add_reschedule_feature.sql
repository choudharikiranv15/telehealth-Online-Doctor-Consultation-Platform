-- ===================================================================
-- ADD APPOINTMENT RESCHEDULE FEATURE
-- Migration script to add reschedule functionality
-- ===================================================================

USE telehealth_db;

-- Modify appointments table status enum to include reschedule_requested
ALTER TABLE appointments
MODIFY COLUMN status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'no_show', 'reschedule_requested', 'rejected') DEFAULT 'pending';

-- Add reschedule tracking fields
ALTER TABLE appointments
ADD COLUMN IF NOT EXISTS requested_date DATE NULL COMMENT 'Patient requested reschedule date',
ADD COLUMN IF NOT EXISTS requested_time TIME NULL COMMENT 'Patient requested reschedule time',
ADD COLUMN IF NOT EXISTS original_date DATE NULL COMMENT 'Original appointment date before reschedule',
ADD COLUMN IF NOT EXISTS original_time TIME NULL COMMENT 'Original appointment time before reschedule',
ADD COLUMN IF NOT EXISTS reschedule_reason TEXT NULL COMMENT 'Patient reason for rescheduling',
ADD COLUMN IF NOT EXISTS reschedule_requested_at TIMESTAMP NULL COMMENT 'When reschedule was requested',
ADD COLUMN IF NOT EXISTS reschedule_response TEXT NULL COMMENT 'Doctor response to reschedule request',
ADD COLUMN IF NOT EXISTS rejection_reason TEXT NULL COMMENT 'Doctor reason for rejecting appointment or reschedule';

-- Add index for reschedule status
ALTER TABLE appointments
ADD INDEX IF NOT EXISTS idx_reschedule_status (status, requested_date);

-- Success message
SELECT 'Appointment reschedule feature added successfully!' as message;
