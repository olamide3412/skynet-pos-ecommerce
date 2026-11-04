<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Welcome to {{ $storeName }}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 24px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: #7e22ce; color: #ffffff; padding: 28px 24px; text-align: center; }
        .content { padding: 24px; line-height: 1.6; }
        .btn { display: inline-block; background: #7e22ce; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: bold; margin-top: 16px; }
        .footer { padding: 20px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 style="margin: 0; font-size: 24px;">Welcome to {{ $storeName }}! 🎉</h1>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $customer->name }}</strong>,</p>
            <p>We're thrilled to have you with us! Your customer account has been created successfully.</p>
            <p>You can now save shipping addresses, track your order history in real-time, build your wishlist, and enjoy express checkout.</p>

            <div style="text-align: center; margin-top: 24px;">
                <a href="{{ url('/shop') }}" class="btn">Explore the Catalog</a>
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.
        </div>
    </div>
</body>
</html>

