<?php
/**
 * Simple PDF Generator for Prescriptions
 * Uses HTML to PDF conversion with CSS styling
 */

class PrescriptionPDF {
    private $db;

    public function __construct($database) {
        $this->db = $database;
    }

    /**
     * Generate prescription PDF
     */
    public function generatePrescription($prescription_id) {
        // Get prescription data
        $prescription_data = $this->getPrescriptionData($prescription_id);

        if (!$prescription_data) {
            throw new Exception("Prescription not found");
        }

        // Generate HTML content
        $html = $this->generateHTML($prescription_data);

        // Return HTML for now - can be converted to PDF using libraries like TCPDF or wkhtmltopdf
        return $html;
    }

    /**
     * Get prescription data with patient and doctor details
     */
    private function getPrescriptionData($prescription_id) {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                a.appointment_date,
                a.appointment_time,
                a.symptoms,
                -- Patient details
                pt.first_name as patient_first_name,
                pt.last_name as patient_last_name,
                pt.email as patient_email,
                pt.phone as patient_phone,
                pt.date_of_birth as patient_dob,
                pt.gender as patient_gender,
                pt.address as patient_address,
                pt.city as patient_city,
                pt.state as patient_state,
                pp.blood_group,
                pp.medical_history,
                pp.allergies,
                -- Doctor details
                dt.first_name as doctor_first_name,
                dt.last_name as doctor_last_name,
                dt.email as doctor_email,
                dt.phone as doctor_phone,
                dp.license_number,
                dp.qualification,
                dp.experience_years,
                s.name as specialization
            FROM prescriptions p
            JOIN appointments a ON p.appointment_id = a.id
            JOIN users pt ON a.patient_id = pt.id
            JOIN users dt ON a.doctor_id = dt.id
            LEFT JOIN patient_profiles pp ON pt.id = pp.user_id
            LEFT JOIN doctor_profiles dp ON dt.id = dp.user_id
            LEFT JOIN specializations s ON dp.specialization_id = s.id
            WHERE p.id = ?
        ");

        $stmt->execute([$prescription_id]);
        return $stmt->fetch();
    }

    /**
     * Generate HTML template for prescription
     */
    private function generateHTML($data) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Prescription - <?php echo htmlspecialchars($data['prescription_number']); ?></title>
            <style>
                @media print {
                    body { margin: 0; }
                    .no-print { display: none !important; }
                }

                body {
                    font-family: 'Helvetica', Arial, sans-serif;
                    line-height: 1.4;
                    color: #333;
                    margin: 0;
                    padding: 20px;
                    background: white;
                }

                .prescription-header {
                    border-bottom: 3px solid #0284c7;
                    padding-bottom: 20px;
                    margin-bottom: 30px;
                    position: relative;
                }

                .clinic-info {
                    text-align: center;
                    margin-bottom: 20px;
                }

                .clinic-name {
                    font-size: 24px;
                    font-weight: bold;
                    color: #0284c7;
                    margin-bottom: 5px;
                }

                .clinic-tagline {
                    font-size: 12px;
                    color: #666;
                    margin-bottom: 10px;
                }

                .prescription-number {
                    position: absolute;
                    top: 0;
                    right: 0;
                    background: #0284c7;
                    color: white;
                    padding: 8px 15px;
                    border-radius: 4px;
                    font-size: 12px;
                    font-weight: bold;
                }

                .doctor-info {
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                }

                .doctor-name {
                    font-size: 18px;
                    font-weight: bold;
                    color: #0284c7;
                    margin-bottom: 5px;
                }

                .info-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 30px;
                    margin-bottom: 30px;
                }

                .info-section h3 {
                    font-size: 16px;
                    color: #0284c7;
                    border-bottom: 1px solid #e5e7eb;
                    padding-bottom: 5px;
                    margin-bottom: 15px;
                }

                .info-row {
                    display: flex;
                    margin-bottom: 8px;
                }

                .info-label {
                    font-weight: bold;
                    min-width: 100px;
                    color: #374151;
                }

                .info-value {
                    color: #6b7280;
                }

                .prescription-content {
                    background: white;
                    border: 2px solid #e5e7eb;
                    border-radius: 8px;
                    padding: 25px;
                    margin: 30px 0;
                }

                .rx-symbol {
                    font-size: 36px;
                    color: #0284c7;
                    float: left;
                    margin-right: 15px;
                    font-weight: bold;
                }

                .diagnosis-section, .medication-section, .instructions-section {
                    margin-bottom: 25px;
                }

                .section-title {
                    font-size: 14px;
                    font-weight: bold;
                    color: #0284c7;
                    text-transform: uppercase;
                    margin-bottom: 8px;
                    letter-spacing: 0.5px;
                }

                .section-content {
                    background: #f9fafb;
                    padding: 12px;
                    border-radius: 4px;
                    border-left: 4px solid #0284c7;
                }

                .medication-list {
                    white-space: pre-line;
                    font-family: monospace;
                    font-size: 13px;
                }

                .footer {
                    margin-top: 40px;
                    border-top: 1px solid #e5e7eb;
                    padding-top: 20px;
                    font-size: 12px;
                    color: #6b7280;
                }

                .signature-section {
                    text-align: right;
                    margin-top: 50px;
                }

                .signature-line {
                    border-top: 1px solid #333;
                    width: 200px;
                    margin: 40px 0 10px auto;
                }

                .validity {
                    background: #fef3c7;
                    border: 1px solid #f59e0b;
                    padding: 10px;
                    border-radius: 4px;
                    text-align: center;
                    font-size: 12px;
                    margin-top: 20px;
                }

                .download-buttons {
                    text-align: center;
                    margin: 20px 0;
                    padding: 20px;
                    background: #f3f4f6;
                    border-radius: 8px;
                }

                .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    margin: 5px;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: bold;
                    text-align: center;
                    border: none;
                    cursor: pointer;
                }

                .btn-primary {
                    background: #0284c7;
                    color: white;
                }

                .btn-secondary {
                    background: #6b7280;
                    color: white;
                }

                .btn:hover {
                    opacity: 0.9;
                }
            </style>
        </head>
        <body>
            <!-- Download Buttons (only show in browser, not print) -->
            <div class="download-buttons no-print">
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print"></i> Print Prescription
                </button>
                <button onclick="downloadAsPDF()" class="btn btn-secondary">
                    <i class="fas fa-download"></i> Save as PDF
                </button>
            </div>

            <!-- Prescription Header -->
            <div class="prescription-header">
                <div class="prescription-number">
                    <?php echo htmlspecialchars($data['prescription_number']); ?>
                </div>

                <div class="clinic-info">
                    <div class="clinic-name">iHealth MediCare</div>
                    <div class="clinic-tagline">Your Health, Our Priority - Digital Healthcare Solutions</div>
                    <div style="font-size: 12px; color: #666;">
                        📧 admin@ihealth.com | ☎️ +1 (555) 123-4567 | 🌐 www.ihealth.com
                    </div>
                </div>
            </div>

            <!-- Doctor Information -->
            <div class="doctor-info">
                <div class="doctor-name">
                    Dr. <?php echo htmlspecialchars($data['doctor_first_name'] . ' ' . $data['doctor_last_name']); ?>
                </div>
                <div style="font-size: 14px; color: #666;">
                    <?php echo htmlspecialchars($data['specialization'] ?? 'General Medicine'); ?>
                    | License: <?php echo htmlspecialchars($data['license_number']); ?>
                    | Experience: <?php echo htmlspecialchars($data['experience_years']); ?> years
                </div>
                <div style="font-size: 12px; color: #888; margin-top: 5px;">
                    <?php echo htmlspecialchars($data['qualification']); ?>
                </div>
            </div>

            <!-- Patient and Appointment Info -->
            <div class="info-grid">
                <div class="info-section">
                    <h3>Patient Information</h3>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($data['patient_first_name'] . ' ' . $data['patient_last_name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Gender:</span>
                        <span class="info-value"><?php echo ucfirst($data['patient_gender'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">DOB:</span>
                        <span class="info-value"><?php echo $data['patient_dob'] ? date('M d, Y', strtotime($data['patient_dob'])) : 'N/A'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Blood Group:</span>
                        <span class="info-value"><?php echo htmlspecialchars($data['blood_group'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?php echo htmlspecialchars($data['patient_phone']); ?></span>
                    </div>
                    <?php if ($data['allergies']): ?>
                    <div class="info-row">
                        <span class="info-label">Allergies:</span>
                        <span class="info-value"><?php echo htmlspecialchars($data['allergies']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="info-section">
                    <h3>Appointment Details</h3>
                    <div class="info-row">
                        <span class="info-label">Date:</span>
                        <span class="info-value"><?php echo date('M d, Y', strtotime($data['appointment_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Time:</span>
                        <span class="info-value"><?php echo date('g:i A', strtotime($data['appointment_time'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Symptoms:</span>
                        <span class="info-value"><?php echo htmlspecialchars($data['symptoms']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created:</span>
                        <span class="info-value"><?php echo date('M d, Y g:i A', strtotime($data['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <!-- Prescription Content -->
            <div class="prescription-content">
                <div class="rx-symbol">℞</div>
                <div style="margin-left: 60px;">

                    <!-- Diagnosis -->
                    <div class="diagnosis-section">
                        <div class="section-title">Diagnosis</div>
                        <div class="section-content">
                            <?php echo nl2br(htmlspecialchars($data['diagnosis'])); ?>
                        </div>
                    </div>

                    <!-- Medications -->
                    <div class="medication-section">
                        <div class="section-title">Medications Prescribed</div>
                        <div class="section-content">
                            <div class="medication-list"><?php echo nl2br(htmlspecialchars($data['medications'])); ?></div>
                        </div>
                    </div>

                    <!-- Dosage Instructions -->
                    <?php if ($data['dosage_instructions']): ?>
                    <div class="instructions-section">
                        <div class="section-title">Dosage Instructions</div>
                        <div class="section-content">
                            <?php echo nl2br(htmlspecialchars($data['dosage_instructions'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Precautions -->
                    <?php if ($data['precautions']): ?>
                    <div class="instructions-section">
                        <div class="section-title">Precautions & Warnings</div>
                        <div class="section-content">
                            <?php echo nl2br(htmlspecialchars($data['precautions'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Follow-up -->
                    <?php if ($data['follow_up_date'] || $data['follow_up_instructions']): ?>
                    <div class="instructions-section">
                        <div class="section-title">Follow-up Instructions</div>
                        <div class="section-content">
                            <?php if ($data['follow_up_date']): ?>
                                <strong>Next Visit:</strong> <?php echo date('M d, Y', strtotime($data['follow_up_date'])); ?><br>
                            <?php endif; ?>
                            <?php if ($data['follow_up_instructions']): ?>
                                <?php echo nl2br(htmlspecialchars($data['follow_up_instructions'])); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Validity Notice -->
            <div class="validity">
                <strong>⚠️ Important:</strong> This prescription is valid until <?php echo date('M d, Y', strtotime($data['valid_until'])); ?>.
                Please use medications as directed and consult your doctor if you experience any adverse effects.
            </div>

            <!-- Signature -->
            <div class="signature-section">
                <div style="margin-bottom: 10px; color: #666; font-size: 12px;">
                    Digitally signed prescription
                </div>
                <div class="signature-line"></div>
                <div style="font-weight: bold; margin-top: 10px;">
                    Dr. <?php echo htmlspecialchars($data['doctor_first_name'] . ' ' . $data['doctor_last_name']); ?>
                </div>
                <div style="font-size: 12px; color: #666;">
                    <?php echo htmlspecialchars($data['specialization'] ?? 'General Medicine'); ?>
                </div>
                <div style="font-size: 12px; color: #666;">
                    License: <?php echo htmlspecialchars($data['license_number']); ?>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                <div style="text-align: center;">
                    <div>This is a computer-generated prescription from iHealth MediCare</div>
                    <div>For verification, contact: admin@ihealth.com | Prescription ID: <?php echo htmlspecialchars($data['prescription_number']); ?></div>
                    <div style="margin-top: 10px; font-size: 10px; color: #999;">
                        Generated on <?php echo date('M d, Y g:i A'); ?>
                    </div>
                </div>
            </div>

            <script>
                function downloadAsPDF() {
                    // Hide download buttons for clean PDF
                    const buttons = document.querySelector('.download-buttons');
                    buttons.style.display = 'none';

                    // Use browser's print to PDF functionality
                    window.print();

                    // Restore buttons after print dialog
                    setTimeout(() => {
                        buttons.style.display = 'block';
                    }, 1000);
                }
            </script>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
?>