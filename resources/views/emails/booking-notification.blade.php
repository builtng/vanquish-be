<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>New Booking Notification</title>
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
                        <td style="background: linear-gradient(135deg, #7c2d6f 0%, #9b3d8a 50%, #6f1d56 100%); padding: 40px; text-align: center;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;">New Booking Notification</h1>
                            <p style="margin: 12px 0 0 0; color: rgba(255, 255, 255, 0.95); font-size: 16px; font-weight: 400;">You have a new appointment request 📅</p>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 24px 0; color: #1f2937; font-size: 18px; line-height: 1.6; font-weight: 600;">Hello {{ $tcName }},</p>

                            <p style="margin: 0 0 32px 0; color: #4b5563; font-size: 16px; line-height: 1.7;">You have received a new {{ $bookingType === 'consultation' ? 'consultation' : 'session' }} booking. Please review the details below:</p>

                            <!-- Booking Details Card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 32px 0; background-color: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden;">
                                <tr>
                                    <td style="padding: 24px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Client Name</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">{{ $clientName }}</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 16px 0; border-bottom: 1px solid #e5e7eb;">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Booking Type</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">{{ ucfirst($bookingType) }}</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 16px 0; {{ (isset($details['notes']) && $details['notes']) ? 'border-bottom: 1px solid #e5e7eb;' : '' }}">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Scheduled Date & Time</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">
                                                        {{ \Carbon\Carbon::parse($scheduledAt)->format('l, F j, Y') }} at {{ \Carbon\Carbon::parse($scheduledAt)->format('g:i A') }}
                                                    </p>
                                                </td>
                                            </tr>
                                            @if(isset($details['notes']) && $details['notes'])
                                            <tr>
                                                <td style="padding-top: 16px;">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Client Notes</p>
                                                    <div style="margin: 0; color: #4b5563; font-size: 15px; font-style: italic; background-color: #ffffff; padding: 12px; border-radius: 6px; border: 1px dashed #d1d5db;">
                                                        "{{ $details['notes'] }}"
                                                    </div>
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Action Required Alert -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 32px 0; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 12px; border-left: 4px solid #3b82f6; overflow: hidden;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <div style="display: table; width: 100%;">
                                            <div style="display: table-cell; vertical-align: top; width: 24px;">
                                                <span style="font-size: 20px;">ℹ️</span>
                                            </div>
                                            <div style="display: table-cell; vertical-align: top; padding-left: 12px;">
                                                <p style="margin: 0; color: #1e40af; font-size: 15px; line-height: 1.6; font-weight: 500;">
                                                    Please log into your counsellor portal to confirm this booking and view complete session details.
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                <tr>
                                    <td align="center">
                                        <a href="{{ rtrim(config('app.frontend_url'), '/') }}/counsellor" target="_blank" style="display: inline-block; padding: 16px 36px; background-color: #6f1d56; color: #ffffff; text-decoration: none; font-weight: 600; font-size: 16px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(111, 29, 86, 0.2); transition: background-color 0.2s;">
                                            Access Counsellor Portal &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin: 40px 0 0 0; color: #6b7280; font-size: 14px; line-height: 1.6; text-align: center;">
                                If you have any questions about this booking, please contact the administration team.
                            </p>
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