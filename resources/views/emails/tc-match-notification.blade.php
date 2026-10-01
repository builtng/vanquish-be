<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You have a message in your portal to attend to</title>
</head>

<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #333333; max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f8fafc;">
    <!-- Email Container -->
    <div style="background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);">
        <!-- Header -->
        <div style="background: linear-gradient(135deg, #7c2d6f 0%, #9b3d8a 50%, #6f1d56 100%); color: #ffffff; padding: 36px 30px; text-align: center;">
            <h1 style="margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.5px;">Vanquish Therapies</h1>
            <p style="margin: 8px 0 0 0; font-size: 15px; opacity: 0.9;">Practitioner Portal</p>
        </div>

        <!-- Content -->
        <div style="background-color: #ffffff; padding: 40px 30px;">
            <p style="margin: 0 0 16px 0; color: #333333; font-size: 16px; line-height: 1.6;">Hi {{ $firstName ?? $tcName ?? 'there' }}.</p>

            <p style="margin: 0 0 24px 0; color: #333333; font-size: 16px; line-height: 1.6;">You have a message in your portal to attend to, kindly.</p>

            <!-- Login Button -->
            <div style="text-align: center; margin: 32px 0;">
                <a href="{{ $loginUrl ?? (rtrim(config('app.frontend_url', 'https://vqtmanagement.com'), '/') . '/counsellor-login') }}"
                    style="display: inline-block; background-color: #6f1d56; color: #ffffff; padding: 14px 36px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 16px; box-shadow: 0 2px 4px rgba(111, 29, 86, 0.25);">
                    Log in to Portal
                </a>
            </div>

            <p style="margin: 32px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;">Warm regards,<br>
                <strong style="color: #6f1d56;">The Vanquish Therapies Team</strong>
            </p>
        </div>

        <!-- Footer -->
        <div style="background-color: #f8fafc; padding: 20px 30px; text-align: center; border-top: 1px solid #e2e8f0;">
            <p style="margin: 0 0 6px 0; color: #64748b; font-size: 12px; line-height: 1.5;">This is an automated email. Please do not reply directly to this message.</p>
            <p style="margin: 0; color: #94a3b8; font-size: 12px; line-height: 1.5;">© {{ date('Y') }} Vanquish Therapies Ltd. All rights reserved.</p>
        </div>
    </div>
</body>

</html>