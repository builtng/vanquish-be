<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Induction Invitation</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
    <!-- Email Container -->
    <div style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <!-- Header -->
        <div style="background-color: #6f1d56; color: #ffffff; padding: 30px 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 0.5px;">Vanquish Therapies</h1>
            <p style="margin: 10px 0 0 0; font-size: 16px; opacity: 0.95;">Induction Invitation</p>
    </div>
    
        <!-- Content -->
        <div style="background-color: #ffffff; padding: 40px 30px; border-top: none;">
            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.7;">Dear {{ $attendee->trainingCounsellor->name }},</p>
        
            <p style="margin: 0 0 25px 0; color: #333333; font-size: 16px; line-height: 1.7;">You have been invited to attend an induction session:</p>
        
            <!-- Event Details Card -->
            <div style="background-color: #f9f9f9; padding: 25px; border-radius: 8px; margin: 25px 0; border-left: 4px solid #6f1d56;">
                <p style="margin: 0 0 12px 0; color: #333333; font-size: 15px;"><strong style="color: #6f1d56;">Date & Time:</strong> {{ $induction->scheduled_at->format('l, F j, Y \a\t g:i A') }}@if($induction->scheduled_end_at) - {{ $induction->scheduled_end_at->format('g:i A') }}@endif</p>
            @if($induction->location)
                <p style="margin: 0 0 12px 0; color: #333333; font-size: 15px;"><strong style="color: #6f1d56;">Location:</strong> {{ $induction->location }}</p>
            @endif
            @if($induction->notes)
                <p style="margin: 12px 0 0 0; color: #333333; font-size: 15px; padding-top: 12px; border-top: 1px solid #e5e5e5;"><strong style="color: #6f1d56;">Notes:</strong> {{ $induction->notes }}</p>
            @endif
        </div>
        
            <div style="background-color: #fff9e6; border-left: 4px solid #eab308; padding: 20px; margin: 25px 0; border-radius: 4px;">
                <p style="margin: 0; color: #333333; font-size: 15px; line-height: 1.7;"><strong>⏰ Important:</strong> Please confirm your attendance by clicking the button below. You have 72 hours to respond.</p>
            </div>
            
            <!-- CTA Button -->
            <div style="text-align: center; margin: 35px 0;">
                <a href="{{ $acceptanceUrl }}" 
                   style="display: inline-block; background-color: #6f1d56; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; letter-spacing: 0.3px; box-shadow: 0 2px 4px rgba(111, 29, 86, 0.3);">
                    Accept Invitation
                </a>
        </div>
        
            <p style="margin: 25px 0 15px 0; color: #666666; font-size: 14px; line-height: 1.6;">
                If you cannot attend, please contact us as soon as possible. This invitation will expire on <strong style="color: #333333;">{{ $attendee->expires_at->format('l, F j, Y \a\t g:i A') }}</strong>.
        </p>
        
            <p style="margin: 20px 0; color: #666666; font-size: 14px; line-height: 1.6;">If the button above doesn't work, copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #6f1d56; font-size: 13px; margin: 10px 0 25px 0; padding: 12px; background-color: #f9f9f9; border-radius: 4px; border: 1px solid #e5e5e5;">{{ $acceptanceUrl }}</p>
            
            <p style="margin: 30px 0 0 0; color: #333333; font-size: 16px; line-height: 1.7;">Best regards,<br>
            <strong style="color: #6f1d56;">Vanquish Therapies Team</strong></p>
    </div>
    
        <!-- Footer -->
        <div style="background-color: #f9f9f9; padding: 25px 30px; text-align: center; border-top: 1px solid #e5e5e5;">
            <p style="margin: 0 0 8px 0; color: #666666; font-size: 12px; line-height: 1.5;">This is an automated email. Please do not reply to this message.</p>
            <p style="margin: 0; color: #666666; font-size: 12px; line-height: 1.5;">If you have any questions or concerns, please contact us directly.</p>
        </div>
    </div>
</body>
</html>

