# Video Call Integration - TeleHealth Platform

## Overview

This document describes the complete video call integration using Jitsi Meet API for the TeleHealth platform. The system provides secure, high-quality video consultations between doctors and patients.

## Features

### 🎥 Core Video Call Features

- **High-Quality Video/Audio**: WebRTC-based communication
- **Screen Sharing**: Share medical documents, test results, or desktop
- **Chat System**: Text-based communication during calls
- **Raise Hand**: Non-verbal communication feature
- **Mute/Unmute**: Audio control for both parties
- **Video On/Off**: Camera control for privacy
- **Call Recording**: Optional recording with consent

### 🔒 Security Features

- **Unique Room IDs**: Each consultation gets a unique, secure room
- **Authentication Required**: Only authenticated users can access calls
- **HTTPS Enforcement**: Secure communication protocol
- **Session Management**: Proper session handling and cleanup

### 📱 User Experience

- **Responsive Design**: Works on desktop, tablet, and mobile
- **Real-time Status**: Connection quality and call duration indicators
- **Notifications**: User-friendly notification system
- **Loading States**: Clear feedback during call initialization

## Technical Implementation

### Frontend Components

#### 1. Video Call Interface (`video_call.php`)

- Main video call page
- Jitsi Meet integration
- Control buttons and chat panel
- Responsive design for all devices

#### 2. Video Call Manager (`assets/js/video_call.js`)

- Notification system
- Browser compatibility checks
- Device testing utilities
- Network quality monitoring

#### 3. Styling (`assets/css/style.css`)

- Video call specific styles
- Responsive controls
- Animation effects
- Status indicators

### Backend APIs

#### 1. Appointment Status Update (`update_appointment_status.php`)

- Updates appointment status during calls
- Tracks video call completion
- Maintains call history

#### 2. Appointment Approval (`approve_appointment.php`)

- Allows doctors to approve appointments
- Changes status from 'pending' to 'confirmed'
- Enables video call initiation

### Database Integration

#### Video Calls Table

```sql
CREATE TABLE video_calls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    room_id VARCHAR(255) UNIQUE NOT NULL,
    status ENUM('active', 'completed', 'failed') DEFAULT 'active',
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    duration INT NULL, -- in seconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE
);
```

#### Appointments Table Updates

- Added `status` field with values: 'pending', 'confirmed', 'active', 'completed', 'cancelled'
- Added `payment_status` field for payment tracking
- Added `payment_method` and `transaction_id` fields

## User Workflow

### Patient Journey

1. **Book Appointment**: Patient books appointment with doctor
2. **Payment**: Completes fake payment process
3. **Wait for Confirmation**: Doctor approves appointment
4. **Join Video Call**: Patient joins consultation at scheduled time
5. **Consultation**: Video call with doctor
6. **Prescription**: Receives prescription after consultation

### Doctor Journey

1. **View Appointments**: See pending appointments
2. **Approve Appointment**: Approve patient appointments
3. **Start Video Call**: Initiate consultation
4. **Consultation**: Conduct video consultation
5. **Generate Prescription**: Create prescription for patient

## Setup Instructions

### 1. Prerequisites

- PHP 7.4+ with PDO extension
- MySQL 5.7+
- Modern web browser with WebRTC support
- HTTPS enabled (required for production)

### 2. Jitsi Meet Integration

The system uses Jitsi Meet's free public server (`meet.jit.si`). No API key required.

### 3. File Structure

```
telehealth/
├── video_call.php                 # Main video call interface
├── update_appointment_status.php  # Status update API
├── approve_appointment.php        # Appointment approval API
├── assets/
│   ├── css/
│   │   └── style.css             # Video call styles
│   └── js/
│       └── video_call.js         # Video call utilities
└── includes/
    ├── header.php                 # Updated with video call support
    └── footer.php                 # Includes video call scripts
```

### 4. Database Setup

Run the updated `telehealth_db.sql` file to create necessary tables and fields.

## Browser Compatibility

### Supported Browsers

- **Chrome**: 60+ (Full support)
- **Firefox**: 55+ (Full support)
- **Safari**: 11+ (Full support)
- **Edge**: 79+ (Full support)

### Required Features

- WebRTC support
- getUserMedia API
- MediaDevices API
- HTTPS (production requirement)

## Security Considerations

### 1. Authentication

- All video calls require user authentication
- Session-based access control
- Role-based permissions (doctor/patient)

### 2. Room Security

- Unique room IDs for each consultation
- No persistent room access
- Automatic room cleanup

### 3. Data Privacy

- No call content recording (unless explicitly enabled)
- Secure transmission using WebRTC
- Minimal data logging

## Troubleshooting

### Common Issues

#### 1. Camera/Microphone Not Working

- Check browser permissions
- Ensure HTTPS is enabled
- Verify device connections

#### 2. Video Call Not Loading

- Check internet connection
- Verify Jitsi API loading
- Check browser console for errors

#### 3. Poor Video Quality

- Check network connection
- Verify camera resolution
- Check browser settings

### Debug Information

Enable browser console logging for detailed error information:

```javascript
// In browser console
localStorage.setItem("debug", "true");
```

## Performance Optimization

### 1. Video Quality

- Adaptive bitrate based on network
- Automatic quality adjustment
- Bandwidth monitoring

### 2. Resource Management

- Efficient memory usage
- Proper cleanup on call end
- Optimized rendering

### 3. Network Optimization

- WebRTC peer-to-peer when possible
- Fallback to relay servers
- Connection quality monitoring

## Future Enhancements

### Planned Features

- **Call Recording**: With patient consent
- **File Sharing**: Medical document exchange
- **Multi-party Calls**: Group consultations
- **Mobile App**: Native mobile support
- **AI Integration**: Symptom analysis
- **Analytics**: Call quality metrics

### Technical Improvements

- **Custom Jitsi Server**: Self-hosted solution
- **Advanced Security**: End-to-end encryption
- **Performance Monitoring**: Real-time metrics
- **Scalability**: Load balancing support

## Support and Maintenance

### Regular Maintenance

- Monitor call quality metrics
- Update browser compatibility
- Security patch updates
- Performance optimization

### Monitoring

- Call success rates
- Network quality metrics
- User experience feedback
- Error rate tracking

## Conclusion

The video call integration provides a robust, secure, and user-friendly solution for telehealth consultations. Built on industry-standard WebRTC technology and Jitsi Meet, it ensures high-quality communication while maintaining security and privacy standards.

For technical support or questions, refer to the main project documentation or contact the development team.
