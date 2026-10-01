<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Qualified Counsellor Form Required</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
    <!-- Email Container -->
    <div style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <!-- Header -->
        <div style="background-color: #6f1d56; color: #ffffff; padding: 30px 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 0.5px;">Vanquish Therapies</h1>
    </div>
    
        <!-- Content -->
        <div style="background-color: #ffffff; padding: 40px 30px; border-top: none;">
            <h2 style="color: #6f1d56; margin-top: 0; margin-bottom: 20px; font-size: 22px; font-weight: 600;">Action Required: Complete Your Qualified Counsellor Form</h2>
        
            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.7;">Dear {{ $tcName }},</p>
        
            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.7;">Congratulations on your transition to Qualified Counsellor status! To complete your registration, we need you to fill out the Qualified Counsellor Application Form.</p>
        
            <div style="background-color: #fff9e6; border-left: 4px solid #eab308; padding: 20px; margin: 25px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; color: #333333; font-size: 15px; font-weight: 600;">🔑 Your Unique Identifier (UUID):</p>
                <p style="margin: 5px 0 0 0; font-family: 'Courier New', monospace; font-size: 14px; word-break: break-all; color: #6f1d56; background-color: #ffffff; padding: 10px; border-radius: 4px; border: 1px solid #e5e5e5;">{{ $tcUuid }}</p>
        </div>
        
            <p style="margin: 25px 0 20px 0; color: #333333; font-size: 16px; line-height: 1.7;">Please click the button below to access and complete the form:</p>
        
            <!-- CTA Button -->
            <div style="text-align: center; margin: 35px 0;">
            <a href="{{ $formUrl }}" 
                   style="display: inline-block; background-color: #6f1d56; color: #ffffff; padding: 16px 40px; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 16px; letter-spacing: 0.3px; box-shadow: 0 2px 4px rgba(111, 29, 86, 0.3);">
                Complete Qualified Counsellor Form
            </a>
        </div>
        
            <p style="margin: 20px 0; color: #666666; font-size: 14px; line-height: 1.6;">Or copy and paste this link into your browser:</p>
            <p style="word-break: break-all; color: #6f1d56; font-size: 13px; margin: 10px 0 25px 0; padding: 12px; background-color: #f9f9f9; border-radius: 4px; border: 1px solid #e5e5e5;">{{ $formUrl }}</p>
        
            <p style="margin: 25px 0 15px 0; color: #333333; font-size: 16px; line-height: 1.7; font-weight: 600;">📋 What you'll need:</p>
            <ul style="margin: 0 0 25px 0; padding-left: 20px; color: #333333; font-size: 16px; line-height: 1.8;">
            <li>Your legal first and last name (as per your ID)</li>
            <li>Your registered address</li>
            <li>Information about your supervisor status</li>
            <li>Details about your training and experience</li>
            <li>Qualification documents</li>
            <li>DBS certificate</li>
            <li>Insurance certificate</li>
            <li>Proof of self-employment</li>
            <li>Professional membership certificate</li>
        </ul>
        
            <p style="margin: 25px 0 0 0; color: #333333; font-size: 16px; line-height: 1.7;">If you have any questions or need assistance, please don't hesitate to contact us.</p>
        
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

