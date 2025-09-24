// TeleHealth Video Call JavaScript

class VideoCall {
    constructor(roomId, options = {}) {
        this.roomId = roomId;
        this.options = {
            width: 800,
            height: 600,
            parentNode: document.body,
            ...options
        };
        
        this.api = null;
        this.room = null;
        this.isInitialized = false;
        this.localStream = null;
        this.remoteStreams = [];
        
        this.init();
    }
    
    /**
     * Initialize video call
     */
    async init() {
        try {
            // Load Jitsi Meet API
            await this.loadJitsiAPI();
            
            // Initialize the API
            this.api = new JitsiMeetExternalAPI('meet.jit.si', {
                roomName: this.roomId,
                width: this.options.width,
                height: this.options.height,
                parentNode: this.options.parentNode,
                configOverwrite: {
                    startWithAudioMuted: false,
                    startWithVideoMuted: false,
                    prejoinPageEnabled: false,
                    disableModeratorIndicator: true,
                    enableClosePage: false,
                    enableWelcomePage: false,
                    enableLobbyChat: false,
                    enableKnocking: false,
                    enableInsecureRoomNameWarning: false,
                    enableNoAudioDetection: false,
                    enableNoisyMicDetection: false,
                    enableRemb: true,
                    enableTcc: true,
                    openBridgeChannel: 'websocket',
                    clientHeight: this.options.height,
                    clientWidth: this.options.width
                },
                interfaceConfigOverwrite: {
                    TOOLBAR_BUTTONS: [
                        'microphone', 'camera', 'closedcaptions', 'desktop', 'fullscreen',
                        'fodeviceselection', 'hangup', 'chat', 'recording',
                        'livestreaming', 'etherpad', 'sharedvideo', 'settings', 'raisehand',
                        'videoquality', 'filmstrip', 'feedback', 'stats', 'shortcuts',
                        'tileview', 'select-background', 'download', 'help', 'mute-everyone', 'security'
                    ],
                    SHOW_JITSI_WATERMARK: false,
                    SHOW_WATERMARK_FOR_GUESTS: false,
                    SHOW_POWERED_BY: false,
                    SHOW_BRAND_WATERMARK: false,
                    SHOW_PROMOTIONAL_CLOSE_PAGE: false,
                    GENERATE_ROOMNAMES_ON_WELCOME_PAGE: false,
                    AUTHENTICATION_ENABLE: false,
                    TOOLBAR_ALWAYS_VISIBLE: true,
                    TOOLBAR_BUTTONS_WIDTH: 40,
                    TOOLBAR_BUTTONS_HEIGHT: 40,
                    TOOLBAR_BUTTONS_SPACING: 10,
                    TOOLBAR_BUTTONS_BORDER_RADIUS: 5,
                    TOOLBAR_BUTTONS_BORDER_WIDTH: 1,
                    TOOLBAR_BUTTONS_BORDER_COLOR: '#ffffff',
                    TOOLBAR_BUTTONS_BACKGROUND_COLOR: 'transparent',
                    TOOLBAR_BUTTONS_HOVER_BACKGROUND_COLOR: 'rgba(255, 255, 255, 0.1)',
                    TOOLBAR_BUTTONS_ACTIVE_BACKGROUND_COLOR: 'rgba(255, 255, 255, 0.2)',
                    TOOLBAR_BUTTONS_TEXT_COLOR: '#ffffff',
                    TOOLBAR_BUTTONS_HOVER_TEXT_COLOR: '#ffffff',
                    TOOLBAR_BUTTONS_ACTIVE_TEXT_COLOR: '#ffffff'
                },
                userInfo: {
                    displayName: this.options.displayName || 'User',
                    email: this.options.email || ''
                }
            });
            
            // Set up event listeners
            this.setupEventListeners();
            
            this.isInitialized = true;
            console.log('Video call initialized successfully');
            
        } catch (error) {
            console.error('Failed to initialize video call:', error);
            this.showError('Failed to initialize video call. Please try again.');
        }
    }
    
    /**
     * Load Jitsi Meet API
     */
    loadJitsiAPI() {
        return new Promise((resolve, reject) => {
            if (window.JitsiMeetExternalAPI) {
                resolve();
                return;
            }
            
            const script = document.createElement('script');
            script.src = 'https://meet.jit.si/external_api.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    
    /**
     * Set up event listeners
     */
    setupEventListeners() {
        if (!this.api) return;
        
        // Participant joined
        this.api.addEventListeners({
            participantJoined: (event) => {
                console.log('Participant joined:', event);
                this.onParticipantJoined(event);
            },
            
            participantLeft: (event) => {
                console.log('Participant left:', event);
                this.onParticipantLeft(event);
            },
            
            audioMuteStatusChanged: (event) => {
                console.log('Audio mute status changed:', event);
                this.onAudioMuteStatusChanged(event);
            },
            
            videoMuteStatusChanged: (event) => {
                console.log('Video mute status changed:', event);
                this.onVideoMuteStatusChanged(event);
            },
            
            participantKickedOut: (event) => {
                console.log('Participant kicked out:', event);
                this.onParticipantKickedOut(event);
            },
            
            conferenceJoined: (event) => {
                console.log('Conference joined:', event);
                this.onConferenceJoined(event);
            },
            
            conferenceLeft: (event) => {
                console.log('Conference left:', event);
                this.onConferenceLeft(event);
            },
            
            readyToClose: (event) => {
                console.log('Ready to close:', event);
                this.onReadyToClose(event);
            }
        });
    }
    
    /**
     * Handle participant joined
     */
    onParticipantJoined(event) {
        const participant = event.participant;
        this.showNotification(`${participant.displayName || 'A participant'} joined the call`, 'info');
        
        // Update UI
        this.updateParticipantList();
    }
    
    /**
     * Handle participant left
     */
    onParticipantLeft(event) {
        const participant = event.participant;
        this.showNotification(`${participant.displayName || 'A participant'} left the call`, 'warning');
        
        // Update UI
        this.updateParticipantList();
    }
    
    /**
     * Handle audio mute status changed
     */
    onAudioMuteStatusChanged(event) {
        const participant = event.participant;
        const isMuted = event.muted;
        
        if (isMuted) {
            this.showNotification(`${participant.displayName || 'A participant'} muted their microphone`, 'info');
        } else {
            this.showNotification(`${participant.displayName || 'A participant'} unmuted their microphone`, 'info');
        }
    }
    
    /**
     * Handle video mute status changed
     */
    onVideoMuteStatusChanged(event) {
        const participant = event.participant;
        const isMuted = event.muted;
        
        if (isMuted) {
            this.showNotification(`${participant.displayName || 'A participant'} turned off their camera`, 'info');
        } else {
            this.showNotification(`${participant.displayName || 'A participant'} turned on their camera`, 'info');
        }
    }
    
    /**
     * Handle participant kicked out
     */
    onParticipantKickedOut(event) {
        const participant = event.participant;
        this.showNotification(`${participant.displayName || 'A participant'} was removed from the call`, 'danger');
    }
    
    /**
     * Handle conference joined
     */
    onConferenceJoined(event) {
        this.showNotification('Successfully joined the video call', 'success');
        this.updateParticipantList();
    }
    
    /**
     * Handle conference left
     */
    onConferenceLeft(event) {
        this.showNotification('Left the video call', 'info');
        this.updateAppointmentStatus('completed');
        this.cleanup();
    }

    /**
     * Handle ready to close
     */
    onReadyToClose(event) {
        this.showNotification('Video call ended', 'info');
        this.updateAppointmentStatus('completed');
        this.cleanup();
    }
    
    /**
     * Update participant list
     */
    updateParticipantList() {
        if (!this.api) return;
        
        try {
            const participants = this.api.getNumberOfParticipants();
            const participantList = document.getElementById('participant-list');
            
            if (participantList) {
                participantList.innerHTML = `<strong>Participants: ${participants + 1}</strong>`;
            }
        } catch (error) {
            console.error('Failed to update participant list:', error);
        }
    }
    
    /**
     * Show notification
     */
    showNotification(message, type = 'info') {
        if (window.TeleHealth && window.TeleHealth.showNotification) {
            window.TeleHealth.showNotification(message, type);
        } else {
            // Fallback notification
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show`;
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            const container = document.querySelector('.video-call-container');
            if (container) {
                container.insertBefore(notification, container.firstChild);
                
                // Auto-dismiss after 3 seconds
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 3000);
            }
        }
    }
    
    /**
     * Show error message
     */
    showError(message) {
        this.showNotification(message, 'danger');
    }
    
    /**
     * Mute/unmute microphone
     */
    toggleMute() {
        if (!this.api) return;
        
        try {
            this.api.executeCommand('toggleAudio');
        } catch (error) {
            console.error('Failed to toggle mute:', error);
        }
    }
    
    /**
     * Turn on/off camera
     */
    toggleVideo() {
        if (!this.api) return;
        
        try {
            this.api.executeCommand('toggleVideo');
        } catch (error) {
            console.error('Failed to toggle video:', error);
        }
    }
    
    /**
     * Share screen
     */
    shareScreen() {
        if (!this.api) return;
        
        try {
            this.api.executeCommand('toggleShareScreen');
        } catch (error) {
            console.error('Failed to share screen:', error);
        }
    }
    
    /**
     * Start recording
     */
    startRecording() {
        if (!this.api) return;
        
        try {
            this.api.executeCommand('startRecording');
            this.showNotification('Recording started', 'info');
        } catch (error) {
            console.error('Failed to start recording:', error);
            this.showError('Failed to start recording');
        }
    }
    
    /**
     * Stop recording
     */
    stopRecording() {
        if (!this.api) return;
        
        try {
            this.api.executeCommand('stopRecording');
            this.showNotification('Recording stopped', 'info');
        } catch (error) {
            console.error('Failed to stop recording:', error);
            this.showError('Failed to stop recording');
        }
    }
    
    /**
     * Raise hand
     */
    raiseHand() {
        if (!this.api) return;
        
        try {
            this.api.executeCommand('raiseHand');
        } catch (error) {
            console.error('Failed to raise hand:', error);
        }
    }
    
    /**
     * Lower hand
     */
    lowerHand() {
        if (!this.api) return;
        
        try {
            this.api.executeCommand('lowerHand');
        } catch (error) {
            console.error('Failed to lower hand:', error);
        }
    }
    
    /**
     * End call
     */
    endCall() {
        if (!this.api) return;

        if (confirm('Are you sure you want to end the call?')) {
            try {
                this.updateAppointmentStatus('completed');
                this.api.executeCommand('hangup');
            } catch (error) {
                console.error('Failed to end call:', error);
                this.updateAppointmentStatus('completed');
                this.cleanup();
            }
        }
    }
    
    /**
     * Leave call
     */
    leaveCall() {
        if (!this.api) return;
        
        if (confirm('Are you sure you want to leave the call?')) {
            try {
                this.api.executeCommand('hangup');
            } catch (error) {
                console.error('Failed to leave call:', error);
                this.cleanup();
            }
        }
    }
    
    /**
     * Get call statistics
     */
    getStats() {
        if (!this.api) return null;
        
        try {
            return this.api.getStats();
        } catch (error) {
            console.error('Failed to get stats:', error);
            return null;
        }
    }
    
    /**
     * Update appointment status
     */
    async updateAppointmentStatus(status) {
        if (!this.options.appointmentId) {
            console.log('No appointment ID available for status update');
            return;
        }

        try {
            const response = await fetch('update_appointment_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    appointment_id: this.options.appointmentId,
                    status: status
                })
            });

            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }

            const result = await response.json();

            if (result.success) {
                console.log('Appointment status updated to:', status);
                this.showNotification('Appointment status updated', 'success');
            } else {
                console.log('Failed to update appointment status:', result.message);
            }

            return result;
        } catch (error) {
            console.error('Error updating appointment status:', error);
            // Don't show error notification to user, just log it
            return { success: false, error: error.message };
        }
    }

    /**
     * Cleanup resources
     */
    cleanup() {
        if (this.api) {
            try {
                this.api.dispose();
            } catch (error) {
                console.error('Failed to dispose API:', error);
            }
            this.api = null;
        }

        this.isInitialized = false;
        this.localStream = null;
        this.remoteStreams = [];

        console.log('Video call cleaned up');
    }
}

// Export to global scope
window.VideoCall = VideoCall;

// Initialize video call when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Auto-initialize if video call container exists
    const videoContainer = document.querySelector('.video-call-container');
    if (videoContainer && videoContainer.dataset.roomId) {
        const roomId = videoContainer.dataset.roomId;
        const options = {
            displayName: videoContainer.dataset.displayName || 'User',
            email: videoContainer.dataset.email || '',
            parentNode: videoContainer
        };
        
        window.currentVideoCall = new VideoCall(roomId, options);
    }
});

// Video Call Utilities and Notifications
class VideoCallManager {
    constructor() {
        this.notifications = [];
        this.maxNotifications = 3;
    }

    // Show notification
    showNotification(message, type = 'info', duration = 5000) {
        const notification = this.createNotification(message, type);
        document.body.appendChild(notification);

        // Remove notification after duration
        setTimeout(() => {
            this.removeNotification(notification);
        }, duration);

        // Store notification reference
        this.notifications.push(notification);

        // Limit number of notifications
        if (this.notifications.length > this.maxNotifications) {
            this.removeNotification(this.notifications.shift());
        }

        return notification;
    }

    // Create notification element
    createNotification(message, type) {
        const notification = document.createElement('div');
        notification.className = `video-notification ${type}`;
        notification.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas ${this.getIconForType(type)} me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;

        // Add animation classes
        notification.classList.add('video-fade-in', 'video-slide-up');

        return notification;
    }

    // Get icon for notification type
    getIconForType(type) {
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        return icons[type] || icons.info;
    }

    // Remove notification
    removeNotification(notification) {
        if (notification && notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }

    // Clear all notifications
    clearAllNotifications() {
        this.notifications.forEach(notification => {
            this.removeNotification(notification);
        });
        this.notifications = [];
    }

    // Show connection status
    showConnectionStatus(status, message) {
        const statusIndicator = document.getElementById('connectionStatus') || this.createStatusIndicator();
        statusIndicator.className = `video-status-indicator ${status}`;
        statusIndicator.innerHTML = `
            <i class="fas ${status === 'connected' ? 'fa-wifi' : 'fa-wifi-slash'} me-2"></i>
            ${message}
        `;
    }

    // Create status indicator
    createStatusIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'connectionStatus';
        indicator.className = 'video-status-indicator';
        document.querySelector('.video-main-area').appendChild(indicator);
        return indicator;
    }

    // Handle video call errors
    handleError(error, context = '') {
        console.error(`Video Call Error ${context}:`, error);
        this.showNotification(
            `Video call error: ${error.message || 'Unknown error occurred'}`, 
            'error'
        );
    }

    // Check browser compatibility
    checkBrowserCompatibility() {
        const issues = [];

        // Check WebRTC support
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            issues.push('WebRTC not supported');
        }

        // Check HTTPS requirement for production
        if (location.protocol !== 'https:' && location.hostname !== 'localhost') {
            issues.push('HTTPS required for video calls');
        }

        // Check camera and microphone permissions
        if (navigator.permissions) {
            navigator.permissions.query({ name: 'camera' }).then(result => {
                if (result.state === 'denied') {
                    issues.push('Camera access denied');
                }
            });

            navigator.permissions.query({ name: 'microphone' }).then(result => {
                if (result.state === 'denied') {
                    issues.push('Microphone access denied');
                }
            });
        }

        return issues;
    }

    // Request camera and microphone permissions
    async requestPermissions() {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: true,
                audio: true
            });
            
            // Stop the stream immediately after getting permissions
            stream.getTracks().forEach(track => track.stop());
            
            this.showNotification('Camera and microphone permissions granted', 'success');
            return true;
        } catch (error) {
            this.showNotification('Failed to get camera/microphone permissions', 'error');
            return false;
        }
    }

    // Test audio and video devices
    async testDevices() {
        const devices = await navigator.mediaDevices.enumerateDevices();
        const audioDevices = devices.filter(device => device.kind === 'audioinput');
        const videoDevices = devices.filter(device => device.kind === 'videoinput');

        if (audioDevices.length === 0) {
            this.showNotification('No audio input devices found', 'warning');
        }

        if (videoDevices.length === 0) {
            this.showNotification('No video input devices found', 'warning');
        }

        return {
            audio: audioDevices.length > 0,
            video: videoDevices.length > 0
        };
    }

    // Format duration
    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;

        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }

    // Show call timer
    startCallTimer() {
        let seconds = 0;
        this.callTimer = setInterval(() => {
            seconds++;
            const timerElement = document.getElementById('callTimer');
            if (timerElement) {
                timerElement.textContent = this.formatDuration(seconds);
            }
        }, 1000);
    }

    // Stop call timer
    stopCallTimer() {
        if (this.callTimer) {
            clearInterval(this.callTimer);
            this.callTimer = null;
        }
    }

    // Handle network quality changes
    handleNetworkQuality(quality) {
        const qualityIndicator = document.getElementById('networkQuality') || this.createNetworkQualityIndicator();
        
        const qualityLevels = {
            excellent: { class: 'text-success', icon: 'fa-wifi', text: 'Excellent' },
            good: { class: 'text-info', icon: 'fa-wifi', text: 'Good' },
            fair: { class: 'text-warning', icon: 'fa-wifi', text: 'Fair' },
            poor: { class: 'text-danger', icon: 'fa-wifi', text: 'Poor' }
        };

        const level = qualityLevels[quality] || qualityLevels.fair;
        qualityIndicator.className = `video-status-indicator ${level.class}`;
        qualityIndicator.innerHTML = `
            <i class="fas ${level.icon} me-2"></i>
            Network: ${level.text}
        `;
    }

    // Create network quality indicator
    createNetworkQualityIndicator() {
        const indicator = document.createElement('div');
        indicator.id = 'networkQuality';
        indicator.className = 'video-status-indicator';
        indicator.style.top = '60px';
        document.querySelector('.video-main-area').appendChild(indicator);
        return indicator;
    }
}

// Global video call manager instance
window.videoCallManager = new VideoCallManager();

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = VideoCallManager;
}
