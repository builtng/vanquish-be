<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Great News! You've Been Matched</title>
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
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;">Great News!</h1>
                            <p style="margin: 12px 0 0 0; color: rgba(255, 255, 255, 0.95); font-size: 16px; font-weight: 400;">You've been matched with a counsellor 🎉</p>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 24px 0; color: #1f2937; font-size: 18px; line-height: 1.6; font-weight: 600;">Dear {{ $clientName }},</p>

                            <p style="margin: 0 0 32px 0; color: #4b5563; font-size: 16px; line-height: 1.7;">We're pleased to inform you that you have been matched with a trainee counsellor who will be supporting you on your therapeutic journey.</p>

                            <!-- Counsellor Details Card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 32px 0; background-color: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden;">
                                <tr>
                                    <td style="padding: 24px; border-bottom: 1px solid #e5e7eb; background-color: #f3f4f6;">
                                        <h3 style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 700;">👤 Your Counsellor</h3>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 24px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Name</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">{{ $tcName }}</p>
                                                </td>
                                            </tr>
                                            @if(isset($modality) && $modality)
                                            <tr>
                                                <td style="padding: 16px 0; {{ (isset($matchScore) && $matchScore) ? 'border-bottom: 1px solid #e5e7eb;' : '' }}">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Modality</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">{{ $modality }}</p>
                                                </td>
                                            </tr>
                                            @endif
                                            @if(isset($matchScore) && $matchScore)
                                            <tr>
                                                <td style="padding-top: 16px;">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Match Score</p>
                                                    <div style="display: flex; align-items: center;">
                                                        <div style="width: 100%; height: 8px; background-color: #e5e7eb; border-radius: 4px; overflow: hidden; max-width: 150px; display: inline-block;">
                                                            <div style="width: {{ $matchScore }}%; height: 100%; background: linear-gradient(90deg, #6f1d56, #9b3d8a);"></div>
                                                        </div>
                                                        <span style="color: #6f1d56; font-weight: 700; margin-left: 12px;">{{ $matchScore }}%</span>
                                                    </div>
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Call to Action: Book Slot -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 32px 0; background-color: #fce7f3; border-radius: 12px; border-left: 4px solid #9d174d;">
                                <tr>
                                    <td style="padding: 24px; text-align: center;">
                                        <p style="margin: 0 0 16px 0; color: #9d174d; font-size: 16px; font-weight: 700;">
                                            📅 Choose Your Session Time
                                        </p>
                                        <p style="margin: 0 0 24px 0; color: #4b5563; font-size: 15px; line-height: 1.6;">
                                            Please select your preferred session time from your counsellor's availability to get started.
                                        </p>
                                        <a href="{{ config('app.frontend_url') }}/client-booking?uuid={{ $clientUuid }}" style="display: inline-block; background: linear-gradient(135deg, #6f1d56 0%, #9b3d8a 100%); color: #ffffff; font-family: 'Inter', sans-serif; font-size: 16px; font-weight: 600; text-decoration: none; padding: 14px 32px; border-radius: 8px; box-shadow: 0 4px 6px rgba(111, 29, 86, 0.2);">
                                            Book Your First Session
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <h2 style="color: #6f1d56; margin: 48px 0 24px 0; font-size: 24px; font-weight: 700; letter-spacing: -0.3px;">What Happens Next?</h2>

                            <!-- Next Steps Timeline -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color: #ffffff;">
                                <!-- Step 1 -->
                                <tr>
                                    <td style="width: 48px; vertical-align: top; padding-bottom: 32px;">
                                        <div style="width: 36px; height: 36px; background-color: #fce7f3; color: #9d174d; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px;">1</div>
                                    </td>
                                    <td style="vertical-align: top; padding-bottom: 32px;">
                                        <p style="margin: 0 0 8px 0; color: #1f2937; font-size: 17px; font-weight: 600;">Initial Contact</p>
                                        <p style="margin: 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Your counsellor will reach out to you shortly to introduce themselves and schedule your first session.</p>
                                    </td>
                                </tr>
                                <!-- Step 2 -->
                                <tr>
                                    <td style="width: 48px; vertical-align: top; padding-bottom: 32px;">
                                        <div style="width: 36px; height: 36px; background-color: #fce7f3; color: #9d174d; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px;">2</div>
                                    </td>
                                    <td style="vertical-align: top; padding-bottom: 32px;">
                                        <p style="margin: 0 0 8px 0; color: #1f2937; font-size: 17px; font-weight: 600;">First Session</p>
                                        <p style="margin: 0; color: #4b5563; font-size: 15px; line-height: 1.6;">During your first session, you'll discuss your goals, expectations, and create a plan for your therapeutic journey together.</p>
                                    </td>
                                </tr>
                                <!-- Step 3 -->
                                <tr>
                                    <td style="width: 48px; vertical-align: top;">
                                        <div style="width: 36px; height: 36px; background-color: #fce7f3; color: #9d174d; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px;">3</div>
                                    </td>
                                    <td style="vertical-align: top;">
                                        <p style="margin: 0 0 8px 0; color: #1f2937; font-size: 17px; font-weight: 600;">Ongoing Support</p>
                                        <p style="margin: 0; color: #4b5563; font-size: 15px; line-height: 1.6;">Regular sessions will be scheduled based on your availability and therapeutic needs.</p>
                                    </td>
                                </tr>
                            </table>

                            <!-- Important Information -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 40px 0 0 0; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; border-left: 4px solid #f59e0b;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <p style="margin: 0 0 12px 0; color: #92400e; font-size: 16px; font-weight: 700;">📌 Important Information</p>
                                        <ul style="margin: 0; padding-left: 20px; color: #78350f; font-size: 14px; line-height: 1.7;">
                                            <li style="margin-bottom: 6px;">All sessions are confidential and conducted in a safe, supportive environment</li>
                                            <li style="margin-bottom: 6px;">Your counsellor works under qualified supervision</li>
                                            <li>If you have any concerns, please don't hesitate to contact us</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 40px 0 0 0; color: #4b5563; font-size: 16px; line-height: 1.7;">We're excited for you to begin this journey and look forward to supporting your wellbeing.</p>

                            <div style="margin: 32px 0 0 0; border-top: 1px solid #e5e7eb; padding-top: 24px;">
                                <p style="margin: 0 0 4px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">Warm regards,</p>
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