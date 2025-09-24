<?php
require_once '../config.php';
require_once '../includes/db_connect.php';

class AppointmentController {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Book a new appointment
     */
    public function bookAppointment($patient_id, $doctor_id, $appointment_date, $appointment_time, $duration, $symptoms = '', $notes = '') {
        try {
            // Validate appointment is not in the past
            if (strtotime($appointment_date) < strtotime(date('Y-m-d'))) {
                return [
                    'success' => false,
                    'message' => 'Cannot book appointments for past dates'
                ];
            }

            // Validate time is not in the past for today's appointments
            if ($appointment_date === date('Y-m-d') && $appointment_time < date('H:i')) {
                return [
                    'success' => false,
                    'message' => 'Cannot book appointments for past times on today\'s date'
                ];
            }

            // Check if doctor is available at that time
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as conflict_count
                FROM appointments
                WHERE doctor_id = ? AND appointment_date = ? AND appointment_time = ? AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$doctor_id, $appointment_date, $appointment_time]);
            $conflict = $stmt->fetch()['conflict_count'];
            
            if ($conflict > 0) {
                return [
                    'success' => false,
                    'message' => 'Doctor is not available at the selected time'
                ];
            }
            
            // Book the appointment
            $stmt = $this->db->prepare("
                INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, duration, symptoms, notes, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$patient_id, $doctor_id, $appointment_date, $appointment_time, $duration, $symptoms, $notes]);
            
            $appointment_id = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'appointment_id' => $appointment_id,
                'message' => 'Appointment booked successfully'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get appointments for a specific user
     */
    public function getUserAppointments($user_id, $role, $filters = []) {
        try {
            $where_conditions = [];
            $params = [];
            
            if ($role === 'doctor') {
                $where_conditions[] = "a.doctor_id = ?";
                $params[] = $user_id;
            } else {
                $where_conditions[] = "a.patient_id = ?";
                $params[] = $user_id;
            }
            
            // Apply filters
            if (!empty($filters['status'])) {
                $where_conditions[] = "a.status = ?";
                $params[] = $filters['status'];
            }
            
            if (!empty($filters['date'])) {
                $where_conditions[] = "a.appointment_date = ?";
                $params[] = $filters['date'];
            }
            
            if (!empty($filters['date_from'])) {
                $where_conditions[] = "a.appointment_date >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $where_conditions[] = "a.appointment_date <= ?";
                $params[] = $filters['date_to'];
            }
            
            $where_clause = implode(" AND ", $where_conditions);
            
            $stmt = $this->db->prepare("
                SELECT a.*, 
                       u1.first_name as patient_first_name, u1.last_name as patient_last_name, u1.phone as patient_phone,
                       u2.first_name as doctor_first_name, u2.last_name as doctor_last_name, u2.specialization
                FROM appointments a
                JOIN users u1 ON a.patient_id = u1.id
                JOIN users u2 ON a.doctor_id = u2.id
                LEFT JOIN doctor_profiles dp ON u2.id = dp.user_id
                WHERE $where_clause
                ORDER BY a.appointment_date DESC, a.appointment_time DESC
            ");
            $stmt->execute($params);
            
            return [
                'success' => true,
                'appointments' => $stmt->fetchAll()
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Update appointment status
     */
    public function updateAppointmentStatus($appointment_id, $status, $user_id = null) {
        try {
            $where_clause = "id = ?";
            $params = [$status, $appointment_id];
            
            if ($user_id) {
                $where_clause .= " AND (doctor_id = ? OR patient_id = ?)";
                $params[] = $user_id;
                $params[] = $user_id;
            }
            
            $stmt = $this->db->prepare("UPDATE appointments SET status = ? WHERE $where_clause");
            $stmt->execute($params);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Appointment status updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Appointment not found or you do not have permission to update it'
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
     * Approve appointment (doctor action)
     */
    public function approveAppointment($appointment_id, $doctor_id, $notes = '') {
        try {
            // Check if appointment exists and is pending
            $stmt = $this->db->prepare("
                SELECT status, patient_id FROM appointments
                WHERE id = ? AND doctor_id = ? AND status = 'pending'
            ");
            $stmt->execute([$appointment_id, $doctor_id]);
            $appointment = $stmt->fetch();

            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found or not pending'
                ];
            }

            // Update appointment status to confirmed
            $stmt = $this->db->prepare("
                UPDATE appointments
                SET status = 'confirmed', doctor_notes = CONCAT(COALESCE(doctor_notes, ''), ?)
                WHERE id = ?
            ");
            $notes_text = !empty($notes) ? "Doctor approved appointment: " . $notes . "\n" : "Appointment approved by doctor.\n";
            $stmt->execute([$notes_text, $appointment_id]);

            return [
                'success' => true,
                'message' => 'Appointment approved successfully'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Reject appointment (doctor action)
     */
    public function rejectAppointment($appointment_id, $doctor_id, $reason = '') {
        try {
            // Check if appointment exists and is pending
            $stmt = $this->db->prepare("
                SELECT status, patient_id FROM appointments
                WHERE id = ? AND doctor_id = ? AND status = 'pending'
            ");
            $stmt->execute([$appointment_id, $doctor_id]);
            $appointment = $stmt->fetch();

            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found or not pending'
                ];
            }

            // Update appointment status to cancelled with reason
            $stmt = $this->db->prepare("
                UPDATE appointments
                SET status = 'cancelled', doctor_notes = CONCAT(COALESCE(doctor_notes, ''), ?)
                WHERE id = ?
            ");
            $reason_text = !empty($reason) ? "Appointment rejected: " . $reason . "\n" : "Appointment rejected by doctor.\n";
            $stmt->execute([$reason_text, $appointment_id]);

            return [
                'success' => true,
                'message' => 'Appointment rejected successfully'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Cancel appointment
     */
    public function cancelAppointment($appointment_id, $user_id) {
        try {
            // Check if user has permission to cancel
            $stmt = $this->db->prepare("
                SELECT status FROM appointments 
                WHERE id = ? AND (patient_id = ? OR doctor_id = ?)
            ");
            $stmt->execute([$appointment_id, $user_id, $user_id]);
            $appointment = $stmt->fetch();
            
            if (!$appointment) {
                return [
                    'success' => false,
                    'message' => 'Appointment not found or you do not have permission to cancel it'
                ];
            }
            
            if ($appointment['status'] === 'completed') {
                return [
                    'success' => false,
                    'message' => 'Cannot cancel completed appointments'
                ];
            }
            
            // Cancel the appointment
            $stmt = $this->db->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ?");
            $stmt->execute([$appointment_id]);
            
            return [
                'success' => true,
                'message' => 'Appointment cancelled successfully'
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get available time slots for a doctor on a specific date
     */
    public function getAvailableTimeSlots($doctor_id, $date) {
        try {
            // Get doctor's working hours
            $stmt = $this->db->prepare("
                SELECT availability_start, availability_end, available_days
                FROM doctor_profiles WHERE user_id = ?
            ");
            $stmt->execute([$doctor_id]);
            $profile = $stmt->fetch();

            if (!$profile) {
                return [
                    'success' => false,
                    'message' => 'Doctor profile not found'
                ];
            }

            // Check if doctor works on this day
            $day_name = strtolower(date('l', strtotime($date)));
            $available_days = explode(',', $profile['available_days']);
            if (!in_array($day_name, $available_days)) {
                return [
                    'success' => false,
                    'message' => 'Doctor does not work on this day'
                ];
            }
            
            // Get booked time slots
            $stmt = $this->db->prepare("
                SELECT appointment_time, duration
                FROM appointments
                WHERE doctor_id = ? AND appointment_date = ? AND status IN ('pending', 'confirmed')
            ");
            $stmt->execute([$doctor_id, $date]);
            $booked_slots = $stmt->fetchAll();
            
            // Generate available time slots
            $available_slots = [];
            $start_time = strtotime($profile['availability_start']);
            $end_time = strtotime($profile['availability_end']);
            
            for ($time = $start_time; $time <= $end_time - 1800; $time += 1800) { // 30-minute intervals
                $time_slot = date('H:i:s', $time);
                $is_available = true;
                
                // Check if this time slot conflicts with any booked appointments
                foreach ($booked_slots as $booked) {
                    $booked_start = strtotime($booked['appointment_time']);
                    $booked_end = $booked_start + ($booked['duration'] * 60);
                    
                    if ($time >= $booked_start && $time < $booked_end) {
                        $is_available = false;
                        break;
                    }
                }
                
                if ($is_available) {
                    $available_slots[] = $time_slot;
                }
            }
            
            return [
                'success' => true,
                'available_slots' => $available_slots
            ];
            
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => 'Database error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get appointment statistics
     */
    public function getAppointmentStats($user_id, $role) {
        try {
            $where_clause = $role === 'doctor' ? "doctor_id = ?" : "patient_id = ?";
            
            // Total appointments
            $stmt = $this->db->prepare("SELECT COUNT(*) as total FROM appointments WHERE $where_clause");
            $stmt->execute([$user_id]);
            $total = $stmt->fetch()['total'];
            
            // Today's appointments
            $stmt = $this->db->prepare("SELECT COUNT(*) as today FROM appointments WHERE $where_clause AND appointment_date = CURDATE()");
            $stmt->execute([$user_id]);
            $today = $stmt->fetch()['today'];
            
            // Pending appointments
            $stmt = $this->db->prepare("SELECT COUNT(*) as pending FROM appointments WHERE $where_clause AND status = 'pending'");
            $stmt->execute([$user_id]);
            $pending = $stmt->fetch()['pending'];
            
            // Completed appointments
            $stmt = $this->db->prepare("SELECT COUNT(*) as completed FROM appointments WHERE $where_clause AND status = 'completed'");
            $stmt->execute([$user_id]);
            $completed = $stmt->fetch()['completed'];
            
            return [
                'success' => true,
                'stats' => [
                    'total' => $total,
                    'today' => $today,
                    'pending' => $pending,
                    'completed' => $completed
                ]
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
$appointment = new AppointmentController($db);
?>
