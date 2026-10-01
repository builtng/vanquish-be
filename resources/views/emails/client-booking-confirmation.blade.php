<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Booking Confirmation</title>
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
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;">Booking Confirmation</h1>
                            <p style="margin: 12px 0 0 0; color: rgba(255, 255, 255, 0.95); font-size: 16px; font-weight: 400;">Your appointment is confirmed! ✅</p>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <p style="margin: 0 0 24px 0; color: #1f2937; font-size: 18px; line-height: 1.6; font-weight: 600;">Dear {{ $clientName }},</p>

                            <p style="margin: 0 0 32px 0; color: #4b5563; font-size: 16px; line-height: 1.7;">Your {{ $bookingType === 'consultation' ? 'consultation' : 'therapy session' }} has been successfully confirmed. We look forward to seeing you!</p>

                            <!-- Booking Details Card -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 32px 0; background-color: #f9fafb; border-radius: 12px; border: 1px solid #e5e7eb; overflow: hidden;">
                                <tr>
                                    <td style="padding: 24px; border-bottom: 1px solid #e5e7eb; background-color: #f3f4f6;">
                                        <h3 style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 700;">🗓 Your Appointment Details</h3>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 24px;">
                                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                                            <tr>
                                                <td style="padding-bottom: 16px; border-bottom: 1px solid #e5e7eb;">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Type</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">{{ ucfirst($bookingType) }}</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 16px 0; border-bottom: 1px solid #e5e7eb;">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Counsellor</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">{{ $tcName }}</p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 16px 0; {{ (isset($location) && $location) || (isset($duration) && $duration) ? 'border-bottom: 1px solid #e5e7eb;' : '' }}">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Date & Time</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">
                                                        @if(is_array($scheduledAt))
                                                        @foreach($scheduledAt as $date)
                                                        {{ \Carbon\Carbon::parse($date)->format('l, F j, Y') }} at {{ \Carbon\Carbon::parse($date)->format('g:i A') }}<br>
                                                        @endforeach
                                                        @else
                                                        {{ \Carbon\Carbon::parse($scheduledAt)->format('l, F j, Y') }} at {{ \Carbon\Carbon::parse($scheduledAt)->format('g:i A') }}
                                                        @endif
                                                    </p>
                                                </td>
                                            </tr>
                                            @if(isset($location) && $location)
                                            <tr>
                                                <td style="padding: 16px 0; {{ (isset($duration) && $duration) ? 'border-bottom: 1px solid #e5e7eb;' : '' }}">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Location</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">{{ $location }}</p>
                                                </td>
                                            </tr>
                                            @endif
                                            @if(isset($duration) && $duration)
                                            <tr>
                                                <td style="padding-top: 16px;">
                                                    <p style="margin: 0 0 4px 0; color: #6b7280; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Duration</p>
                                                    <p style="margin: 0; color: #1f2937; font-size: 16px; font-weight: 500;">{{ $duration }} minutes</p>
                                                </td>
                                            </tr>
                                            @endif
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- Add to Calendar Button -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 32px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ $calendarLink ?? '#' }}" target="_blank" style="display: inline-block; padding: 16px 32px; background-color: #f3f4f6; color: #1f2937; text-decoration: none; font-weight: 600; font-size: 15px; border-radius: 8px; border: 1px solid #d1d5db; transition: background-color 0.2s;">
                                            📅 Add to Calendar
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <!-- Preparation Tips (Consultation Only) -->
                            @if($bookingType === 'consultation')
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 24px 0; background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-radius: 12px; border-left: 4px solid #3b82f6;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 8px 0; color: #1e40af; font-size: 16px; font-weight: 700;">💡 Preparing for Your First Consultation</p>
                                        <p style="margin: 0; color: #1e3a8a; font-size: 14px; line-height: 1.6;">
                                            This is an opportunity to discuss your goals, ask questions, and get to know your counsellor. Feel free to prepare any questions or topics you'd like to explore.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <!-- Important Information -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 24px 0; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-radius: 12px; border-left: 4px solid #f59e0b;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 12px 0; color: #92400e; font-size: 16px; font-weight: 700;">⏰ Important Reminders</p>
                                        <ul style="margin: 0; padding-left: 20px; color: #78350f; font-size: 14px; line-height: 1.7;">
                                            <li style="margin-bottom: 6px;">Please arrive 5 minutes before your scheduled time</li>
                                            <li style="margin-bottom: 6px;">Bring any relevant documents or notes</li>
                                            <li>You'll receive a reminder email 24 hours before your appointment</li>
                                        </ul>
                                    </td>
                                </tr>
                            </table>

                            <!-- Cancellation Policy -->
                            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin: 0 0 32px 0; background: linear-gradient(135deg, #fce7f3 0%, #fbcfe8 100%); border-radius: 12px; border-left: 4px solid #ec4899;">
                                <tr>
                                    <td style="padding: 20px 24px;">
                                        <p style="margin: 0 0 8px 0; color: #9d174d; font-size: 15px; font-weight: 700;">⚠️ Cancellation Policy</p>
                                        <p style="margin: 0; color: #831843; font-size: 14px; line-height: 1.6;">
                                            If you need to cancel or reschedule, please contact us at least 24 hours in advance. Late cancellations may be subject to a fee.
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <div style="margin: 40px 0 0 0; border-top: 1px solid #e5e7eb; padding-top: 24px;">
                                <p style="margin: 0 0 4px 0; color: #4b5563; font-size: 16px; line-height: 1.6;">Best regards,</p>
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