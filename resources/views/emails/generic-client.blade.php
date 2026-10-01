<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject }}</title>
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
                            <h1 style="margin: 0; color: #ffffff; font-size: 24px; font-weight: 700; letter-spacing: -0.5px; line-height: 1.2;">Vanquish Therapies</h1>
                        </td>
                    </tr>

                    <!-- Main Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="color: #6f1d56; margin: 0 0 24px 0; font-size: 22px; font-weight: 700; letter-spacing: -0.5px;">{{ $subject }}</h2>

                            <p style="margin: 0 0 24px 0; color: #1f2937; font-size: 16px; font-weight: 500;">Dear {{ $clientName }},</p>

                            <div style="background-color: #f9fafb; border-radius: 12px; padding: 24px; border: 1px solid #e5e7eb; color: #4b5563; font-size: 16px; line-height: 1.7; white-space: pre-wrap;">{{ $message }}</div>

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