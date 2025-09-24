<?php
$page_title = "Video Consultation - Jitsi";
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: " . getPageUrl('login.php'));
    exit();
}

require_once 'includes/db_connect.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

if (!$appointment_id) {
    header("Location: " . getPageUrl('dashboard.php'));
    exit();
}

// Get appointment details and verify access
try {
    $stmt = $db->prepare("
        SELECT a.*,
               u.first_name, u.last_name, u.profile_picture,
               s.name as specialization, dp.consultation_fee,
               p.first_name as patient_first_name, p.last_name as patient_last_name, p.profile_picture as patient_picture
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        LEFT JOIN specializations s ON dp.specialization_id = s.id
        JOIN users p ON a.patient_id = p.id
        WHERE a.id = ? AND (a.doctor_id = ? OR a.patient_id = ?) AND a.status = 'confirmed'
    ");
    $stmt->execute([$appointment_id, $user_id, $user_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        header("Location: " . getPageUrl('dashboard.php'));
        exit();
    }

    // Determine if current user is doctor or patient
    $is_doctor = ($user_id == $appointment['doctor_id']);
    $other_party_name = $is_doctor ?
        $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'] :
        'Dr. ' . $appointment['first_name'] . ' ' . $appointment['last_name'];

} catch (PDOException $e) {
    header("Location: " . getPageUrl('dashboard.php'));
    exit();
}

// Generate unique room ID for this consultation
$room_id = 'ihealth_consultation_' . $appointment_id;
$user_display_name = $is_doctor ?
    'Dr. ' . $_SESSION['first_name'] . ' ' . $_SESSION['last_name'] :
    $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Update appointment status to active
try {
    $stmt = $db->prepare("UPDATE appointments SET status = 'active' WHERE id = ?");
    $stmt->execute([$appointment_id]);
} catch (PDOException $e) {
    // Log error but continue
}

require_once 'includes/header.php';
?>

<style>
.jitsi-container {
    height: 100vh;
    width: 100%;
    position: relative;
}

.consultation-header {
    position: fixed;
    top: 70px;
    left: 20px;
    right: 20px;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 15px 20px;
    border-radius: 8px;
    z-index: 1000;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.consultation-info h5 {
    margin: 0;
    color: #fff;
}

.consultation-info small {
    opacity: 0.8;
}

.end-consultation-btn {
    background: #dc3545;
    border: none;
    color: white;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.end-consultation-btn:hover {
    background: #c82333;
}

.loading-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #0d6efd, #6f42c1);
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: white;
    z-index: 2000;
}

.loading-spinner {
    width: 50px;
    height: 50px;
    border: 4px solid rgba(255,255,255,0.3);
    border-top: 4px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 20px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.pre-call-screen {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #0d6efd, #6f42c1);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 1500;
}

.pre-call-card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    text-align: center;
    max-width: 400px;
    margin: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
}

@media (max-width: 768px) {
    .consultation-header {
        position: relative;
        top: 0;
        margin-bottom: 10px;
    }

    .jitsi-container {
        height: calc(100vh - 80px);
    }
}
</style>

<!-- Loading Screen -->
<div id="loadingScreen" class="loading-screen">
    <div class="loading-spinner"></div>
    <h4>Setting up your consultation...</h4>
    <p>Please wait while we prepare the video call</p>
</div>

<!-- Pre-call Screen -->
<div id="preCallScreen" class="pre-call-screen" style="display: none;">
    <div class="pre-call-card">
        <h4 class="mb-3">📹 Video Consultation Ready</h4>
        <div class="mb-3">
            <strong>Appointment:</strong> #<?php echo $appointment_id; ?><br>
            <strong>With:</strong> <?php echo htmlspecialchars($other_party_name); ?><br>
            <strong>Date:</strong> <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?><br>
            <strong>Time:</strong> <?php echo date('h:i A', strtotime($appointment['appointment_time'])); ?>
        </div>
        <div class="alert alert-info">
            <small><i class="fas fa-info-circle me-1"></i>
            Your browser may ask for camera and microphone permissions. Please allow them to join the call.
            </small>
        </div>
        <button class="btn btn-primary btn-lg" onclick="joinCall()">
            <i class="fas fa-video me-2"></i>Join Video Call
        </button>
    </div>
</div>

<!-- Consultation Header -->
<div class="consultation-header" id="consultationHeader" style="display: none;">
    <div class="consultation-info">
        <h5>Video Consultation - Appointment #<?php echo $appointment_id; ?></h5>
        <small>with <?php echo htmlspecialchars($other_party_name); ?> • <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></small>
    </div>
    <button class="end-consultation-btn" onclick="endConsultation()">
        <i class="fas fa-phone-slash me-1"></i>End Consultation
    </button>
</div>

<!-- Jitsi Container -->
<div id="jitsi-container" class="jitsi-container" style="display: none;"></div>

<!-- End Call Modal -->
<div class="modal fade" id="endCallModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">End Consultation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to end this consultation?</p>
                <div class="alert alert-warning">
                    <small><i class="fas fa-exclamation-triangle me-1"></i>
                    This will end the video call for both participants.
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmEndCall()">End Consultation</button>
            </div>
        </div>
    </div>
</div>

<!-- Jitsi Meet API -->
<script src="https://meet.jit.si/external_api.js"></script>

<script>
// Configuration
const ROOM_NAME = "<?php echo $room_id; ?>";
const USER_NAME = "<?php echo htmlspecialchars($user_display_name); ?>";
const APPOINTMENT_ID = <?php echo $appointment_id; ?>;
const IS_DOCTOR = <?php echo $is_doctor ? 'true' : 'false'; ?>;

let jitsiApi = null;

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Show pre-call screen after loading
    setTimeout(() => {
        document.getElementById('loadingScreen').style.display = 'none';
        document.getElementById('preCallScreen').style.display = 'flex';
    }, 2000);
});

function joinCall() {
    document.getElementById('preCallScreen').style.display = 'none';
    document.getElementById('jitsi-container').style.display = 'block';
    document.getElementById('consultationHeader').style.display = 'flex';

    // Initialize Jitsi Meet
    initializeJitsi();
}

function initializeJitsi() {
    const domain = 'meet.jit.si';
    const options = {
        roomName: ROOM_NAME,
        width: '100%',
        height: '100%',
        parentNode: document.querySelector('#jitsi-container'),
        userInfo: {
            displayName: USER_NAME
        },
        configOverwrite: {
            startWithAudioMuted: false,
            startWithVideoMuted: false,
            enableEmailInStats: false,
            enableCalendarIntegration: false,
            enableClosePage: false,
            disableInviteFunctions: true,
            doNotStoreRoom: true,
            enableInsecureRoomNameWarning: false,
            enableLipSync: false,
            disableModeratorIndicator: false,
            startScreenSharing: false,
            enableUserRolesBasedOnToken: false,
            enableFeaturesBasedOnToken: false,
            requireDisplayName: true,
            disableProfile: true,
            hideConferenceSubject: false,
            hideConferenceTimer: false,
            hideParticipantsStats: true,
            hideDisplayName: false,
            disableResponsiveTiles: false,
            disableTileEnlargement: false,
            disableModeratorIndicator: true,
            channelLastN: 2, // Only show 2 participants (doctor + patient)
            enableLayerSuspension: true
        },
        interfaceConfigOverwrite: {
            TOOLBAR_BUTTONS: [
                'microphone', 'camera', 'desktop', 'fullscreen',
                'fodeviceselection', 'hangup', 'profile',
                'recording', 'livestreaming', 'etherpad', 'sharedvideo',
                'settings', 'raisehand', 'videoquality', 'filmstrip',
                'invite', 'feedback', 'stats', 'shortcuts',
                'tileview', 'select-background', 'help', 'mute-everyone',
                'mute-video-everyone', 'security'
            ],
            SETTINGS_SECTIONS: ['devices', 'language', 'moderator', 'profile', 'calendar'],
            SHOW_JITSI_WATERMARK: false,
            SHOW_WATERMARK_FOR_GUESTS: false,
            SHOW_BRAND_WATERMARK: false,
            BRAND_WATERMARK_LINK: "",
            SHOW_POWERED_BY: false,
            SHOW_PROMOTIONAL_CLOSE_PAGE: false,
            HIDE_INVITE_MORE_HEADER: true,
            DISABLE_VIDEO_BACKGROUND: false,
            DISABLE_BLUR_BACKGROUND: false
        }
    };

    jitsiApi = new JitsiMeetExternalAPI(domain, options);

    // Event listeners
    jitsiApi.addEventListener('videoConferenceJoined', onVideoConferenceJoined);
    jitsiApi.addEventListener('videoConferenceLeft', onVideoConferenceLeft);
    jitsiApi.addEventListener('participantJoined', onParticipantJoined);
    jitsiApi.addEventListener('participantLeft', onParticipantLeft);
    jitsiApi.addEventListener('readyToClose', onReadyToClose);
}

// Event handlers
function onVideoConferenceJoined(event) {
    console.log('User joined the conference:', event);
    updateAppointmentStatus('active');
}

function onVideoConferenceLeft(event) {
    console.log('🚪 [JITSI] User left the conference:', event);
    // Don't auto-update status when someone leaves - let them use the "End Consultation" button
    // This prevents premature status updates if someone accidentally leaves
}

function onParticipantJoined(event) {
    console.log('Participant joined:', event);
}

function onParticipantLeft(event) {
    console.log('Participant left:', event);
}

function onReadyToClose() {
    console.log('🔔 [JITSI] Ready to close event triggered');
    // Don't auto-close, let user decide with the end consultation button
    // This prevents accidental status updates
}

// Control functions
function endConsultation() {
    const modal = new bootstrap.Modal(document.getElementById('endCallModal'));
    modal.show();
}

async function confirmEndCall() {
    console.log('🏁 [JITSI] Starting call end process...');

    // Close the modal if it's open
    const modal = bootstrap.Modal.getInstance(document.getElementById('endCallModal'));
    if (modal) {
        modal.hide();
    }

    // Show loading indicator
    const loadingDiv = document.createElement('div');
    loadingDiv.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center; color: white;">
            <div style="text-align: center;">
                <div class="spinner-border mb-3" role="status"></div>
                <p>Ending consultation and updating status...</p>
            </div>
        </div>
    `;
    document.body.appendChild(loadingDiv);

    try {
        console.log('🔄 [JITSI] Updating appointment status to completed...');

        // Update appointment status to completed - wait for it to complete
        const statusResult = await updateAppointmentStatus('completed');

        if (statusResult.success) {
            console.log('✅ [JITSI] Appointment status updated successfully');
        } else {
            console.warn('⚠️ [JITSI] Status update failed but continuing:', statusResult.message);
        }

        // Leave the call
        if (jitsiApi) {
            console.log('🔌 [JITSI] Disposing Jitsi API...');
            jitsiApi.dispose();
            jitsiApi = null;
        }

        // Small delay to ensure status update is processed
        await new Promise(resolve => setTimeout(resolve, 1000));

        console.log('🏠 [JITSI] Redirecting user...');

        // Remove loading indicator
        document.body.removeChild(loadingDiv);

        // Redirect based on user role and ask doctor if they want to write prescription
        <?php if ($is_doctor): ?>
        if (confirm('Consultation completed successfully!\n\nWould you like to write a prescription for this patient?')) {
            window.location.href = '<?php echo getPageUrl('doctor/write_prescription.php?appointment_id=' . $appointment_id); ?>';
        } else {
            window.location.href = '<?php echo getPageUrl('doctor/'); ?>';
        }
        <?php else: ?>
        alert('Consultation completed successfully!');
        window.location.href = '<?php echo getPageUrl('patient/'); ?>';
        <?php endif; ?>

    } catch (error) {
        console.error('❌ [JITSI] Error ending consultation:', error);

        // Remove loading indicator
        if (loadingDiv.parentNode) {
            document.body.removeChild(loadingDiv);
        }

        // Still try to update status and redirect even if there was an error
        try {
            await updateAppointmentStatus('completed');
        } catch (statusError) {
            console.error('❌ [JITSI] Final status update attempt failed:', statusError);
        }

        alert('There was an issue ending the consultation, but it has been marked as completed.');

        // Redirect anyway
        <?php if ($is_doctor): ?>
        if (confirm('Would you like to write a prescription for this consultation?')) {
            window.location.href = '<?php echo getPageUrl('doctor/write_prescription.php?appointment_id=' . $appointment_id); ?>';
        } else {
            window.location.href = '<?php echo getPageUrl('doctor/'); ?>';
        }
        <?php else: ?>
        window.location.href = '<?php echo getPageUrl('patient/'); ?>';
        <?php endif; ?>
    }
}

async function updateAppointmentStatus(status) {
    console.log('🔄 [JITSI] Attempting to update appointment status to:', status);
    console.log('📝 [JITSI] Appointment ID:', APPOINTMENT_ID);

    try {
        const requestData = {
            appointment_id: APPOINTMENT_ID,
            status: status
        };

        console.log('📤 [JITSI] Sending request:', requestData);

        const response = await fetch('update_appointment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(requestData)
        });

        console.log('📡 [JITSI] Response status:', response.status, response.statusText);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ [JITSI] HTTP Error:', response.status, errorText);
            throw new Error('Failed to update appointment status: HTTP ' + response.status);
        }

        const result = await response.json();
        console.log('✅ [JITSI] Response data:', result);

        if (result.success) {
            console.log('🎉 [JITSI] Appointment status updated successfully!');
        } else {
            console.log('⚠️ [JITSI] Status update failed:', result.message);
        }

        return result;
    } catch (error) {
        console.error('❌ [JITSI] Error updating appointment status:', error);
        throw error;
    }
}

// Handle page unload
window.addEventListener('beforeunload', function(e) {
    if (jitsiApi) {
        jitsiApi.dispose();
    }
});

// Mobile optimization
if (window.innerWidth <= 768) {
    document.addEventListener('DOMContentLoaded', function() {
        // Hide header on mobile for more screen space
        setTimeout(() => {
            const header = document.getElementById('consultationHeader');
            if (header) {
                header.style.display = 'none';
            }
        }, 5000);
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>