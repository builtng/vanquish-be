<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Reminder</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
    <!-- Email Container -->
    <div style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <!-- Header -->
        <div style="background-color: #6f1d56; color: #ffffff; padding: 30px 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 0.5px;">Vanquish Therapies</h1>
            <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.95;">Session Reminder</p>
        </div>
        
        <!-- Content -->
        <div style="background-color: #ffffff; padding: 40px 30px; border-top: none;">
            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.7;">Dear {{ $clientName }},</p>
            
            <p style="margin: 0 0 25px 0; color: #333333; font-size: 16px; line-height: 1.7;">This is a friendly reminder about your upcoming {{ $bookingType === 'consultation' ? 'consultation' : 'therapy session' }} tomorrow.</p>
            
            <!-- Reminder Badge -->
            <div style="background-color: #fef3c7; border-left: 4px solid #eab308; padding: 20px; margin: 25px 0; border-radius: 4px; text-align: center;">
                <p style="margin: 0; color: #eab308; font-size: 48px; line-height: 1;">🔔</p>
                <p style="margin: 10px 0 0 0; color: #333333; font-size: 16px; font-weight: 600;">Your Session is Tomorrow</p>
            </div>
            
            <!-- Session Details Card -->
            <div style="background-color: #f9f9f9; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #6f1d56;">
                <h3 style="margin: 0 0 15px 0; color: #6f1d56; font-size: 18px; font-weight: 600;">Session Details</h3>
                <p style="margin: 0 0 12px 0; color: #333333; font-size: 15px;"><strong style="color: #6f1d56;">Type:</strong> {{ ucfirst($bookingType) }}</p>
                <p style="margin: 0 0 12px 0; color: #333333; font-size: 15px;"><strong style="color: #6f1d56;">Counsellor:</strong> {{ $tcName }}</p>
                <p style="margin: 0 0 12px 0; color: #333333; font-size: 15px;"><strong style="color: #6f1d56;">Date & Time:</strong> {{ \Carbon\Carbon::parse($scheduledAt)->format('l, F j, Y \a\t g:i A') }}</p>
                @if(isset($location) && $location)
                    <p style="margin: 0 0 12px 0; color: #333333; font-size: 15px;"><strong style="color: #6f1d56;">Location:</strong> {{ $location }}</p>
                @endif
                @if(isset($duration) && $duration)
                    <p style="margin: 0; color: #333333; font-size: 15px;"><strong style="color: #6f1d56;">Duration:</strong> {{ $duration }} minutes</p>
                @endif
            </div>
            
            <!-- Quick Reminders -->
            <div style="background-color: #e0f2fe; border-left: 4px solid #0ea5e9; padding: 20px; margin: 25px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; color: #333333; font-size: 15px; font-weight: 600;">📝 Quick Reminders</p>
                <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #333333; font-size: 14px; line-height: 1.8;">
                    <li>Please arrive 5 minutes early</li>
                    <li>Bring any notes or topics you'd like to discuss</li>
                    <li>Ensure you're in a quiet, private space for your session</li>
                    @if($bookingType === 'session')
                    <li>Review any homework or reflections from your last session</li>
                    @endif
                </ul>
            </div>
            
            <!-- Need to Reschedule -->
            <div style="background-color: #fce7f3; border-left: 4px solid #ec4899; padding: 20px; margin: 25px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; color: #333333; font-size: 15px; font-weight: 600;">⚠️ Need to Reschedule?</p>
                <p style="margin: 0; color: #333333; font-size: 14px; line-height: 1.7;">
                    If you need to cancel or reschedule this appointment, please contact us immediately. Late cancellations may be subject to a fee.
                </p>
            </div>
            
            <!-- Confirmation Section -->
            <div style="text-align: center; margin: 30px 0; padding: 20px; background-color: #f3f4f6; border-radius: 8px;">
                <p style="margin: 0 0 10px 0; color: #333333; font-size: 15px; font-weight: 600;">Looking Forward to Seeing You</p>
                <p style="margin: 0; color: #666666; font-size: 14px; line-height: 1.7;">
                    No action is required. We'll see you at your scheduled time.
                </p>
            </div>
            
            <p style="margin: 30px 0 0 0; color: #333333; font-size: 16px; line-height: 1.7;">Take care and see you soon!</p>
            
            <p style="margin: 25px 0 0 0; color: #333333; font-size: 16px; line-height: 1.7;">Best regards,<br>
            <strong style="color: #6f1d56;">Vanquish Therapies Team</strong></p>
        </div>
        
        <!-- Footer -->
        <div style="background-color: #f9f9f9; padding: 25px 30px; text-align: center; border-top: 1px solid #e5e5e5;">
            <p style="margin: 0 0 8px 0; color: #666666; font-size: 12px; line-height: 1.5;">This is an automated reminder email. Please do not reply to this message.</p>
            <p style="margin: 0; color: #666666; font-size: 12px; line-height: 1.5;">If you have any questions or concerns, please contact us directly.</p>
        </div>
    </div>
</body>
</html>
