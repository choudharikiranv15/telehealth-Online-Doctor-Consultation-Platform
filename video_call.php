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

// Simple signaling via PHP backend
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
            throw new Error('Failed to send signal: HTTP ' + response.status);
        }

        const result = await response.json();
        if (!result.success) {
            throw new Error(result.message || 'Signal sending failed');
        }
    } catch (error) {
        console.error('Error sending signal:', error);
        updateStatus('Signaling error (database issue): ' + error.message);
        // Don't stop the call - WebRTC can work peer-to-peer
    }
}

async function pollForSignals() {
    try {
        const response = await fetch(`api/webrtc_signal.php?appointment_id=${APPOINTMENT_ID}&user_id=${USER_ID}`);

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`);
        }

        const signals = await response.json();
        console.log('📡 Polling response:', signals); // Debug

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
            // No new signals - this is normal
            console.log('📭 No new signals');
        } else {
            console.error('❌ Signaling error:', signals.message);
            updateStatus('Signaling error: ' + (signals.message || 'Unknown error'));
        }
    } catch (error) {
        console.error('❌ Error polling signals:', error);
        updateStatus('Connection error: ' + error.message);
    }

    // Continue polling every 2 seconds
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

async function endCall() {
    if (!confirm('Are you sure you want to end the call?')) {
        return;
    }

    console.log('🏁 [WebRTC] Starting call end process...');

    // Show loading indicator
    const loadingDiv = document.createElement('div');
    loadingDiv.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 9999; display: flex; align-items: center; justify-content: center; color: white;">
            <div style="text-align: center;">
                <div style="border: 4px solid #f3f3f3; border-top: 4px solid #3498db; border-radius: 50%; width: 40px; height: 40px; animation: spin 2s linear infinite; margin: 0 auto 20px;"></div>
                <p>Ending call and updating status...</p>
            </div>
        </div>
        <style>
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        </style>
    `;
    document.body.appendChild(loadingDiv);

    try {
        // Clean up media streams
        if (localStream) {
            console.log('🎥 [WebRTC] Stopping local stream...');
            localStream.getTracks().forEach(track => track.stop());
        }
        if (peerConnection) {
            console.log('🔌 [WebRTC] Closing peer connection...');
            peerConnection.close();
        }

        console.log('🔄 [WebRTC] Updating appointment status to completed...');

        // Update appointment status to completed - wait for it to complete
        const statusResult = await updateAppointmentStatus('completed');

        if (statusResult.success) {
            console.log('✅ [WebRTC] Appointment status updated successfully');
        } else {
            console.warn('⚠️ [WebRTC] Status update failed but continuing:', statusResult.message);
        }

        // Small delay to ensure status update is processed
        await new Promise(resolve => setTimeout(resolve, 1000));

        console.log('🏠 [WebRTC] Redirecting user...');

        // Remove loading indicator
        document.body.removeChild(loadingDiv);

        // Redirect based on user role and ask doctor if they want to write prescription
        <?php if ($is_doctor): ?>
        if (confirm('Call ended successfully!\n\nWould you like to write a prescription for this patient?')) {
            window.location.href = '<?php echo getPageUrl('doctor/write_prescription.php?appointment_id=' . $appointment_id); ?>';
        } else {
            window.location.href = '<?php echo getPageUrl('doctor/'); ?>';
        }
        <?php else: ?>
        alert('Call ended successfully!');
        window.location.href = '<?php echo getPageUrl('patient/'); ?>';
        <?php endif; ?>

    } catch (error) {
        console.error('❌ [WebRTC] Error ending call:', error);

        // Remove loading indicator
        if (loadingDiv.parentNode) {
            document.body.removeChild(loadingDiv);
        }

        // Still try to update status and redirect even if there was an error
        try {
            await updateAppointmentStatus('completed');
        } catch (statusError) {
            console.error('❌ [WebRTC] Final status update attempt failed:', statusError);
        }

        alert('There was an issue ending the call, but it has been marked as completed.');

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

// Optional appointment status update (graceful failure)
async function updateAppointmentStatus(status) {
    console.log('🔄 Attempting to update appointment status to:', status);
    console.log('📝 Appointment ID:', APPOINTMENT_ID);

    try {
        const requestData = {
            appointment_id: APPOINTMENT_ID,
            status: status
        };

        console.log('📤 Sending request:', requestData);

        const response = await fetch('update_appointment_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(requestData)
        });

        console.log('📡 Response status:', response.status, response.statusText);

        if (!response.ok) {
            const errorText = await response.text();
            console.error('❌ HTTP Error:', response.status, errorText);
            throw new Error('HTTP ' + response.status + ': ' + errorText);
        }

        const result = await response.json();
        console.log('✅ Response data:', result);

        if (result.success) {
            console.log('🎉 Appointment status updated successfully!');
        } else {
            console.log('⚠️ Status update failed:', result.message);
        }

        return result;
    } catch (error) {
        console.error('❌ Appointment status update failed:', error);
        // Don't throw - this is optional
        return { success: false, error: error.message };
    }
}

function updateStatus(message) {
    document.getElementById('status').textContent = message;
}

// Clean up on page unload
window.addEventListener('beforeunload', () => {
    if (localStream) {
        localStream.getTracks().forEach(track => track.stop());
    }
    if (peerConnection) {
        peerConnection.close();
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>