<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Vanquish Therapies</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f5f5f5;">
    <!-- Email Container -->
    <div style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #6f1d56 0%, #8b2469 100%); color: #ffffff; padding: 40px 20px; text-align: center;">
            <h1 style="margin: 0; font-size: 28px; font-weight: 600; letter-spacing: 0.5px;">Welcome to Vanquish Therapies</h1>
            <p style="margin: 15px 0 0 0; font-size: 16px; opacity: 0.95;">Join our team of dedicated trainee counsellors</p>
        </div>
        
        <!-- Content -->
        <div style="background-color: #ffffff; padding: 40px 30px; border-top: none;">
            <p style="margin: 0 0 20px 0; color: #333333; font-size: 18px; line-height: 1.7; font-weight: 600;">Dear {{ $tcName }},</p>
            
            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.7;">Congratulations and welcome to Vanquish Therapies! We're thrilled to have you join our team of trainee counsellors.</p>
            
            <p style="margin: 0 0 25px 0; color: #333333; font-size: 16px; line-height: 1.7;">Your registration has been successfully completed. This is an exciting step in your professional development, and we're committed to supporting you throughout your training journey.</p>
            
            <!-- Welcome Info Card -->
            <div style="background-color: #f0fdf4; border-left: 4px solid #22c55e; padding: 20px; margin: 25px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; color: #333333; font-size: 15px; font-weight: 600;">✅ Your Account Details</p>
                <p style="margin: 5px 0 0 0; color: #333333; font-size: 15px; line-height: 1.7;">
                    <strong style="color: #6f1d56;">Counsellor ID:</strong> {{ $tcId }}<br>
                    <strong style="color: #6f1d56;">Email:</strong> {{ $tcEmail }}<br>
                    @if(isset($modality) && $modality)
                    <strong style="color: #6f1d56;">Modality:</strong> {{ $modality }}
                    @endif
                </p>
            </div>
            
            <!-- Next Steps -->
            <h2 style="color: #6f1d56; margin: 35px 0 20px 0; font-size: 20px; font-weight: 600;">Your Journey Begins</h2>
            
            <div style="background-color: #f9f9f9; padding: 25px; border-radius: 8px; margin: 20px 0;">
                <div style="margin-bottom: 20px;">
                    <p style="margin: 0 0 8px 0; color: #6f1d56; font-size: 16px; font-weight: 600;">1. Induction Session</p>
                    <p style="margin: 0; color: #333333; font-size: 15px; line-height: 1.7;">You'll receive an invitation to attend an induction session where we'll introduce you to our processes, policies, and support systems.</p>
                </div>
                
                <div style="margin-bottom: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                    <p style="margin: 0 0 8px 0; color: #6f1d56; font-size: 16px; font-weight: 600;">2. Access Your Portal</p>
                    <p style="margin: 0; color: #333333; font-size: 15px; line-height: 1.7;">Once your induction is complete, you'll gain access to your counsellor portal where you can manage clients, sessions, and documentation.</p>
                </div>
                
                <div style="margin-bottom: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                    <p style="margin: 0 0 8px 0; color: #6f1d56; font-size: 16px; font-weight: 600;">3. Client Matching</p>
                    <p style="margin: 0; color: #333333; font-size: 15px; line-height: 1.7;">Our team will carefully match you with clients based on your experience, modality, and availability.</p>
                </div>
                
                <div style="margin-bottom: 20px; padding-top: 20px; border-top: 1px solid #e5e5e5;">
                    <p style="margin: 0 0 8px 0; color: #6f1d56; font-size: 16px; font-weight: 600;">4. Ongoing Supervision</p>
                    <p style="margin: 0; color: #333333; font-size: 15px; line-height: 1.7;">You'll receive regular supervision and support from qualified counsellors to ensure the highest quality of care.</p>
                </div>
                
                <div style="padding-top: 20px; border-top: 1px solid #e5e5e5;">
                    <p style="margin: 0 0 8px 0; color: #6f1d56; font-size: 16px; font-weight: 600;">5. Professional Development</p>
                    <p style="margin: 0; color: #333333; font-size: 15px; line-height: 1.7;">Access ongoing training, resources, and development opportunities to enhance your skills and knowledge.</p>
                </div>
            </div>
            
            <!-- Important Information -->
            <div style="background-color: #fef3c7; border-left: 4px solid #eab308; padding: 20px; margin: 25px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; color: #333333; font-size: 15px; font-weight: 600;">📌 Key Information</p>
                <ul style="margin: 10px 0 0 0; padding-left: 20px; color: #333333; font-size: 14px; line-height: 1.8;">
                    <li>All client work must be documented within 24 hours of each session</li>
                    <li>Supervision sessions are mandatory and scheduled regularly</li>
                    <li>Maintain professional boundaries and ethical standards at all times</li>
                    <li>Report any concerns or safeguarding issues immediately</li>
                    <li>Keep your availability calendar updated in the portal</li>
                </ul>
            </div>
            
            <!-- Resources Section -->
            <div style="background-color: #e0f2fe; border-left: 4px solid #0ea5e9; padding: 20px; margin: 25px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; color: #333333; font-size: 15px; font-weight: 600;">📚 Resources Available</p>
                <p style="margin: 0; color: #333333; font-size: 14px; line-height: 1.7;">
                    You'll have access to our comprehensive resource library, including clinical guidelines, documentation templates, and professional development materials.
                </p>
            </div>
            
            <!-- Support Section -->
            <div style="background-color: #fce7f3; border-left: 4px solid #ec4899; padding: 20px; margin: 25px 0; border-radius: 4px;">
                <p style="margin: 0 0 10px 0; color: #333333; font-size: 15px; font-weight: 600;">💬 Support & Guidance</p>
                <p style="margin: 0; color: #333333; font-size: 14px; line-height: 1.7;">
                    Our clinical team is here to support you. If you have any questions, concerns, or need guidance, please don't hesitate to reach out.
                </p>
            </div>
            
            <p style="margin: 30px 0 0 0; color: #333333; font-size: 16px; line-height: 1.7;">We're excited to have you as part of our team and look forward to supporting your professional growth.</p>
            
            <p style="margin: 25px 0 0 0; color: #333333; font-size: 16px; line-height: 1.7;">Best regards,<br>
            <strong style="color: #6f1d56;">The Vanquish Therapies Team</strong></p>
        </div>
        
        <!-- Footer -->
        <div style="background-color: #f9f9f9; padding: 25px 30px; text-align: center; border-top: 1px solid #e5e5e5;">
            <p style="margin: 0 0 8px 0; color: #666666; font-size: 12px; line-height: 1.5;">This is an automated email. Please do not reply to this message.</p>
            <p style="margin: 0; color: #666666; font-size: 12px; line-height: 1.5;">If you have any questions or concerns, please contact us directly.</p>
        </div>
    </div>
</body>
</html>
