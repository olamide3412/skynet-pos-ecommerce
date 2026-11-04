<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Reset Your Password</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #7e22ce; color: #ffffff; padding: 24px; text-align: center; }
        .content { padding: 24px; line-height: 1.6; }
        .btn { display: inline-block; background: #7e22ce; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; margin-top: 16px; }
        .footer { padding: 20px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 style="margin: 0; font-size: 22px;">{{ $storeName }}</h1>
            <p style="margin: 4px 0 0; opacity: 0.9;">Password Reset Request</p>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $customer->name }}</strong>,</p>
            <p>You recently requested to reset your password for your {{ $storeName }} account. Click the button below to choose a new password:</p>

            <div style="text-align: center; margin: 24px 0;">
                <a href="{{ $resetUrl }}" class="btn">Reset Password</a>
            </div>

            <p style="font-size: 13px; color: #64748b;">
                This password reset link will expire in 60 minutes.<br>
                If you did not request a password reset, no further action is required and your account remains secure.
            </p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.
        </div>
    </div>
</body>
</html>

