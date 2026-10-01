<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>We Value Your Feedback</title>
    <!--[if mso]>
    <style type="text/css">
        body, table, td {font-family: Arial, Helvetica, sans-serif !important;}
    </style>
    <![endif]-->
</head>

<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #f8fafc;">
        <tr>
            <td style="padding: 40px 20px;">
                <!-- Main Container -->
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);">
                    <!-- Header with Gradient -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #7c2d6f 0%, #9b3d8a 50%, #6f1d56 100%); padding: 50px 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;">We Value Your Feedback</h1>
                            <p style="margin: 12px 0 0 0; color: rgba(255, 255, 255, 0.95); font-size: 16px; font-weight: 400;">Help us serve you better 💬</p>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 48px 40px;">
                            <p style="margin: 0 0 24px 0; color: #1f2937; font-size: 18px; line-height: 1.6; font-weight: 600;">Dear {{ $clientName }},</p>

                            <p style="margin: 0 0 24px 0; color: #4b5563; font-size: 16px; line-height: 1.7;">Thank you for being a valued client of Vanquish Therapies. Your feedback is incredibly important to us as we continuously strive to improve our services and ensure the best possible experience for all our clients.</p>

                            <!-- Why Feedback Matters Card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 32px 0; background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-radius: 12px; border-left: 4px solid #3b82f6; overflow: hidden;">
                                <tr>
                                    <td style="padding: 24px 28px;">
                                        <p style="margin: 0 0 16px 0; color: #1e40af; font-size: 16px; font-weight: 700;">
                                            Your responses will help us:
                                        </p>
                                        <ul style="margin: 0; padding-left: 20px; color: #1e3a8a; font-size: 15px; line-height: 1.8;">
                                            <li style="margin-bottom: 8px;">Understand how we're meeting your needs</li>
                                            <li style="margin-bottom: 8px;">Identify areas for improvement</li>
                                            <li>Ensure we're providing the highest quality of care</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 0 0 32px 0; color: #4b5563; font-size: 16px; line-height: 1.7;">Please click the button below to complete our brief client satisfaction survey:</p>

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 32px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $formUrl }}" target="_blank" style="display: inline-block; padding: 18px 40px; background-color: #6f1d56; color: #ffffff; text-decoration: none; font-weight: 700; font-size: 16px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(111, 29, 86, 0.25); transition: background-color 0.2s, transform 0.1s;">
                                            Complete Feedback Form &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Fallback Link -->
                            <div style="background-color: #f3f4f6; border-radius: 8px; padding: 16px; border: 1px solid #e5e7eb;">
                                <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 13px;">Or copy and paste this link into your browser:</p>
                                <a href="{{ $formUrl }}" style="color: #6f1d56; text-decoration: underline; font-size: 13px; word-break: break-all; line-height: 1.5;">{{ $formUrl }}</a>
                            </div>

                            <p style="margin: 32px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6; font-style: italic;">
                                The survey should take approximately 5-10 minutes to complete. All responses are confidential and will be used solely for the purpose of improving our services.
                            </p>

                            <div style="margin: 40px 0 0 0; border-top: 1px solid #e5e7eb; padding-top: 24px;">
                                <p style="margin: 0 0 4px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">Thank you for your time,</p>
                                <p style="margin: 0; color: #6f1d56; font-size: 16px; font-weight: 700;">The Vanquish Therapies Team</p>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background: linear-gradient(to bottom, #f9fafb, #f3f4f6); padding: 32px 40px; text-align: center; border-top: 1px solid #e5e7eb;">
                            <p style="margin: 0 0 8px 0; color: #6b7280; font-size: 13px; line-height: 1.5;">This is an automated email from Vanquish Therapies. Please do not reply to this message.</p>
                            <p style="margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.5;">© {{ date('Y') }} Vanquish Therapies. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>