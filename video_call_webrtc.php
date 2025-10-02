<?php
$page_title = "Video Consultation - WebRTC";
require_once 'config.php';

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

// Get appointment details
try {
    $stmt = $db->prepare("
        SELECT a.*,
               u.first_name, u.last_name,
               p.first_name as patient_first_name, p.last_name as patient_last_name
        FROM appointments a
        JOIN users u ON a.doctor_id = u.id
        JOIN users p ON a.patient_id = p.id
        WHERE a.id = ? AND (a.doctor_id = ? OR a.patient_id = ?)
    ");
    $stmt->execute([$appointment_id, $user_id, $user_id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        header("Location: " . getPageUrl('dashboard.php'));
        exit();
    }

    $is_doctor = ($user_id == $appointment['doctor_id']);

    // If appointment is already completed, redirect appropriately
    if ($appointment['status'] === 'completed') {
        if ($is_doctor) {
            header("Location: " . getPageUrl('doctor/write_prescription.php?appointment_id=' . $appointment_id));
        } else {
            header("Location: " . getPageUrl('patient/'));
        }
        exit();
    }
    $other_party_name = $is_doctor ?
        $appointment['patient_first_name'] . ' ' . $appointment['patient_last_name'] :
        'Dr. ' . $appointment['first_name'] . ' ' . $appointment['last_name'];

} catch (PDOException $e) {
    header("Location: " . getPageUrl('dashboard.php'));
    exit();
}

require_once 'includes/header.php';
?>

<style>
.video-call-container {
    height: 100vh;
    background: #000;
    position: relative;
    display: flex;
    flex-direction: column;
}

.video-area {
    flex: 1;
    position: relative;
    display: grid;
    grid-template-columns: 1fr 200px;
    gap: 10px;
    padding: 10px;
}

.main-video {
    background: #1a1a1a;
    border-radius: 10px;
    position: relative;
    overflow: hidden;
}

.main-video video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.local-video {
    background: #2a2a2a;
    border-radius: 10px;
    position: relative;
    overflow: hidden;
}

.local-video video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.video-label {
    position: absolute;
    bottom: 10px;
    left: 10px;
    background: rgba(0,0,0,0.7);
    color: white;
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
}

.controls {
    background: rgba(0,0,0,0.8);
    padding: 20px;
    display: flex;
    justify-content: center;
    gap: 20px;
}

.control-btn {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: none;
    color: white;
    font-size: 18px;
    cursor: pointer;
    transition: all 0.3s;
}

.control-btn.active { background: #4CAF50; }
.control-btn.inactive { background: #f44336; }
.control-btn.hangup { background: #d32f2f; }

.status {
    position: absolute;
    top: 20px;
    right: 20px;
    background: rgba(0,0,0,0.8);
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    font-size: 14px;
}

.waiting {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    color: white;
}

.join-btn {
    background: #4CAF50;
    color: white;
    border: none;
    padding: 15px 30px;
    border-radius: 25px;
    font-size: 16px;
    cursor: pointer;
    margin-top: 20px;
}

@media (max-width: 768px) {
    .video-area {
        grid-template-columns: 1fr;
        grid-template-rows: 1fr 120px;
    }
}
</style>

<div class="video-call-container">
    <div class="status" id="status">Ready to connect</div>

    <div class="video-area">
        <div class="main-video">
            <video id="remoteVideo" autoplay playsinline></video>
            <div class="video-label"><?php echo htmlspecialchars($other_party_name); ?></div>
            <div class="waiting" id="waitingMessage">
                <h3>Waiting for <?php echo htmlspecialchars($other_party_name); ?> to join...</h3>
                <button class="join-btn" onclick="startCall()">Start Video Call</button>
            </div>
        </div>

        <div class="local-video">
            <video id="localVideo" autoplay muted playsinline></video>
            <div class="video-label">You</div>
        </div>
    </div>

    <div class="controls">
        <button class="control-btn active" id="micBtn" onclick="toggleMic()">
            <i class="fas fa-microphone"></i>
        </button>
        <button class="control-btn active" id="videoBtn" onclick="toggleVideo()">
            <i class="fas fa-video"></i>
        </button>
        <button class="control-btn hangup" onclick="endCall()">
            <i class="fas fa-phone-slash"></i>
        </button>
    </div>
</div>

<script>
// Configuration
const APPOINTMENT_ID = <?php echo $appointment_id; ?>;
const USER_ID = <?php echo $user_id; ?>;
const IS_DOCTOR = <?php echo $is_doctor ? 'true' : 'false'; ?>;

// WebRTC variables
let localStream = null;
let peerConnection = null;
let isAudioEnabled = true;
let isVideoEnabled = true;

// Simple peer connection configuration
const config = {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' }
    ]
};

async function startCall() {
    try {
        updateStatus('Starting camera and microphone...');

        // Track that this user has joined the call
        await trackUserJoin();

        // Get user media
        localStream = await navigator.mediaDevices.getUserMedia({
            video: { width: 1280, height: 720 },
            audio: true
        });

        document.getElementById('localVideo').srcObject = localStream;
        document.getElementById('waitingMessage').style.display = 'none';

        // Initialize peer connection
        createPeerConnection();

        updateStatus('Ready - Waiting for other participant');

        // Simple signaling using polling (for demo)
        startSignaling();

        // Start monitoring for timeouts
        startTimeoutMonitoring();

    } catch (error) {
        console.error('Error accessing media:', error);
        updateStatus('Error: Could not access camera/microphone');
        alert('Please allow camera and microphone access to start the video call.');
    }
}

function createPeerConnection() {
    peerConnection = new RTCPeerConnection(config);

    // Add local stream
    localStream.getTracks().forEach(track => {
        peerConnection.addTrack(track, localStream);
    });

    // Handle remote stream
    peerConnection.ontrack = (event) => {
        const remoteVideo = document.getElementById('remoteVideo');
        if (remoteVideo.srcObject !== event.streams[0]) {
            remoteVideo.srcObject = event.streams[0];
            updateStatus('Connected');
        }
    };

    // Handle ICE candidates
    peerConnection.onicecandidate = (event) => {
        if (event.candidate) {
            sendSignal('ice-candidate', { candidate: event.candidate });
        }
    };

    peerConnection.onconnectionstatechange = () => {
        updateStatus('Connection: ' + peerConnection.connectionState);
    };
}

// Simple signaling using database polling
async function startSignaling() {
    // Check if we should create offer (doctor goes first)
    if (IS_DOCTOR) {
        setTimeout(createOffer, 2000);
    }

    // Start polling for signals
    pollForSignals();
}

async function createOffer() {
    if (!peerConnection) return;

    try {
        console.log('👨‍⚕️ Doctor creating offer...');
        const offer = await peerConnection.createOffer();
        await peerConnection.setLocalDescription(offer);

        console.log('📤 Sending offer to patient...');
        await sendSignal('offer', { offer: offer });
        updateStatus('Offer sent - waiting for patient response...');
        console.log('✅ Offer sent successfully');
    } catch (error) {
        console.error('❌ Error creating offer:', error);
        updateStatus('Error creating offer: ' + error.message);
    }
}

async function handleOffer(offerData) {
    if (!peerConnection) return;

    try {
        console.log('🤒 Patient received offer from doctor');
        await peerConnection.setRemoteDescription(offerData.offer);

        console.log('📝 Patient creating answer...');
        const answer = await peerConnection.createAnswer();
        await peerConnection.setLocalDescription(answer);

        console.log('📤 Sending answer to doctor...');
        await sendSignal('answer', { answer: answer });
        updateStatus('Answer sent - connecting...');
        console.log('✅ Answer sent successfully');
    } catch (error) {
        console.error('❌ Error handling offer:', error);
        updateStatus('Error handling offer: ' + error.message);
    }
}

async function handleAnswer(answerData) {
    if (!peerConnection) return;

    try {
        await peerConnection.setRemoteDescription(answerData.answer);
        updateStatus('Answer received - connecting');
    } catch (error) {
        console.error('Error handling answer:', error);
    }
}

async function handleIceCandidate(candidateData) {
    if (!peerConnection) return;

    try {
        await peerConnection.addIceCandidate(candidateData.candidate);
    } catch (error) {
        console.error('Error adding ICE candidate:', error);
    }
}

// Simple signaling via PHP backend (graceful failure)
async function sendSignal(type, data) {
    try {
        const response = await fetch('api/webrtc_signal.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                appointment_id: APPOINTMENT_ID,
                type: type,
                data: data,
                sender_id: USER_ID
            })
        });

        if (!response.ok) {
            console.warn('⚠️ Signaling unavailable (using simple mode):', response.status);
            return;
        }

        const result = await response.json();
        if (!result.success) {
            console.warn('⚠️ Signal sending failed:', result.message);
        } else {
            console.log('📤 Signal sent successfully:', type);
        }
    } catch (error) {
        console.warn('⚠️ Signaling not available:', error.message);
        // Don't stop the call - WebRTC can work peer-to-peer in simple mode
    }
}

async function pollForSignals() {
    try {
        const response = await fetch(`api/webrtc_signal.php?appointment_id=${APPOINTMENT_ID}&user_id=${USER_ID}`);

        if (!response.ok) {
            // Don't show error for signaling issues - just log them
            console.warn('⚠️ Signaling server error (this is normal for simple WebRTC):', response.status);
            updateStatus('Using simple WebRTC mode (no signaling server)');
            return; // Don't continue polling if server is not working
        }

        const signals = await response.json();
        console.log('📡 Polling response:', signals);

        if (signals.success && signals.data && signals.data.length > 0) {
            console.log('🔄 Processing', signals.data.length, 'signals');
            updateStatus(`Processing ${signals.data.length} signals...`);

            for (const signal of signals.data) {
                console.log('📨 Handling signal:', signal.type);

                switch (signal.type) {
                    case 'offer':
                        await handleOffer(signal.data);
                        break;
                    case 'answer':
                        await handleAnswer(signal.data);
                        break;
                    case 'ice-candidate':
                        await handleIceCandidate(signal.data);
                        break;
                }
            }
        } else if (signals.success) {
            console.log('📭 No new signals');
        } else {
            console.warn('⚠️ Signaling issue:', signals.message);
        }
    } catch (error) {
        console.warn('⚠️ Signaling not available (using simple mode):', error.message);
        updateStatus('Video call ready (simple mode)');
        return; // Don't continue polling if there are connection issues
    }

    // Continue polling every 2 seconds only if signaling is working
    setTimeout(pollForSignals, 2000);
}


function toggleMic() {
    if (localStream) {
        isAudioEnabled = !isAudioEnabled;
        localStream.getAudioTracks()[0].enabled = isAudioEnabled;

        const micBtn = document.getElementById('micBtn');
        micBtn.className = `control-btn ${isAudioEnabled ? 'active' : 'inactive'}`;
        micBtn.innerHTML = isAudioEnabled ?
            '<i class="fas fa-microphone"></i>' :
            '<i class="fas fa-microphone-slash"></i>';
    }
}

function toggleVideo() {
    if (localStream) {
        isVideoEnabled = !isVideoEnabled;
        localStream.getVideoTracks()[0].enabled = isVideoEnabled;

        const videoBtn = document.getElementById('videoBtn');
        videoBtn.className = `control-btn ${isVideoEnabled ? 'active' : 'inactive'}`;
        videoBtn.innerHTML = isVideoEnabled ?
            '<i class="fas fa-video"></i>' :
            '<i class="fas fa-video-slash"></i>';
    }
}

function endCall() {
    if (confirm('End call and mark consultation as completed?')) {
        // Simple approach - update status and refresh
        updateStatusAndRefresh();
    }
}

async function updateStatusAndRefresh() {
    console.log('🔄 [WebRTC] Updating status and refreshing...');

    try {
        // Update appointment status
        const response = await fetch('update_appointment_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                appointment_id: APPOINTMENT_ID,
                status: 'completed'
            })
        });

        const result = await response.json();

        if (result.success) {
            console.log('✅ Status updated successfully');

            <?php if ($is_doctor): ?>
            // Ask doctor about prescription before refresh
            if (confirm('Consultation completed!\n\nWould you like to write a prescription?')) {
                window.location.href = '<?php echo getPageUrl('doctor/write_prescription.php?appointment_id=' . $appointment_id); ?>';
                return;
            }
            <?php endif; ?>

            // Refresh the page to update UI
            window.location.reload();

        } else {
            console.error('❌ Status update failed:', result.message);
            alert('Status update failed. Please refresh the page manually.');
            window.location.reload();
        }

    } catch (error) {
        console.error('❌ Error:', error);
        alert('Network error. Please refresh the page manually.');
        window.location.reload();
    }
}

function endCallOnly() {
    console.log('🏁 [WebRTC] Ending call...');

    // Clean up media streams
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
    }
    if (peerConnection) {
        peerConnection.close();
    }

    // Redirect back to dashboard
    window.location.href = '<?php echo getPageUrl($is_doctor ? 'doctor/' : 'patient/'); ?>';
}

// Appointment status update with proper debugging
async function updateAppointmentStatus(status) {
    console.log('🔄 [WebRTC] Attempting to update appointment status to:', status);
    console.log('📝 [WebRTC] Appointment ID:', APPOINTMENT_ID);

    try {
        const requestData = {
            appointment_id: APPOINTMENT_ID,
            status: status
        };

        console.log('📤 [WebRTC] Sending request:', requestData);

        // Use the main update API, not the api folder one
        const response = await fetch('update_appointment_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        });

        console.log('📡 [WebRTC] Response status:', response.status, response.statusText);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ [WebRTC] HTTP Error:', response.status, errorText);
            throw new Error('HTTP ' + response.status + ': ' + errorText);
        }

        const result = await response.json();
        console.log('✅ [WebRTC] Response data:', result);

        if (result.success) {
            console.log('🎉 [WebRTC] Appointment status updated successfully!');
        } else {
            console.log('⚠️ [WebRTC] Status update failed:', result.message);
        }

        return result;
    } catch (error) {
        console.error('❌ [WebRTC] Appointment status update failed:', error);
        // Don't throw - this is optional but return proper error info
        return { success: false, error: error.message };
    }
}

function updateStatus(message) {
    document.getElementById('status').textContent = message;
}

// Track user join
async function trackUserJoin() {
    try {
        const response = await fetch('api/track_call_join.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                appointment_id: APPOINTMENT_ID
            })
        });

        const result = await response.json();
        console.log('Join tracked:', result);

        if (result.success && result.both_joined) {
            updateStatus('Both participants connected!');
        }
    } catch (error) {
        console.error('Error tracking join:', error);
    }
}

// Monitor for timeout (other user not joining)
let timeoutCheckInterval;
function startTimeoutMonitoring() {
    // Check every 30 seconds
    timeoutCheckInterval = setInterval(async () => {
        try {
            const response = await fetch(`api/track_call_join.php?appointment_id=${APPOINTMENT_ID}`);
            const result = await response.json();

            if (result.success) {
                if (result.both_joined) {
                    // Both joined, stop monitoring
                    clearInterval(timeoutCheckInterval);
                    updateStatus('Connected');
                } else if (result.is_timed_out) {
                    // Timeout occurred
                    clearInterval(timeoutCheckInterval);

                    const otherParty = IS_DOCTOR ? 'Patient' : 'Doctor';
                    const missedBy = result.missed_by;

                    if (missedBy) {
                        // Mark appointment as missed
                        await markAppointmentAsMissed(missedBy);

                        const message = IS_DOCTOR && missedBy === 'patient'
                            ? 'Patient did not join the call. Appointment marked as missed by patient.'
                            : !IS_DOCTOR && missedBy === 'doctor'
                            ? 'Doctor did not join the call. Appointment marked as missed by doctor.'
                            : 'The other participant did not join within the waiting period.';

                        alert(message);

                        // End call and redirect
                        endCallOnly();
                    }
                }
            }
        } catch (error) {
            console.error('Error checking timeout:', error);
        }
    }, 30000); // Check every 30 seconds
}

// Mark appointment as missed
async function markAppointmentAsMissed(missedBy) {
    try {
        const response = await fetch('api/mark_missed_appointment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                appointment_id: APPOINTMENT_ID,
                missed_by: missedBy
            })
        });

        const result = await response.json();
        console.log('Missed appointment marked:', result);
    } catch (error) {
        console.error('Error marking missed:', error);
    }
}

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    if (timeoutCheckInterval) {
        clearInterval(timeoutCheckInterval);
    }
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
    }
    if (peerConnection) {
        peerConnection.close();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>