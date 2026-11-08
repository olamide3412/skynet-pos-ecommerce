@php
    $themeColor = $themeColor ?? \App\Models\StoreSetting::get('theme_primary_color', '#FF4B00');
    $rawLogo = $companyLogo ?? \App\Models\StoreSetting::get('company_logo', null);
    $logoUrl = null;
    if ($rawLogo) {
        if (\Illuminate\Support\Str::startsWith($rawLogo, ['http://', 'https://'])) {
            $logoUrl = $rawLogo;
        } elseif (\Illuminate\Support\Str::startsWith($rawLogo, '/storage/')) {
            $logoUrl = url($rawLogo);
        } elseif (\Illuminate\Support\Str::startsWith($rawLogo, 'storage/')) {
            $logoUrl = url($rawLogo);
        } else {
            $logoUrl = url('storage/' . $rawLogo);
        }
    } elseif (file_exists(public_path('images/logo.png'))) {
        $logoUrl = url('images/logo.png');
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Your Password - {{ $storeName }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            margin: 0;
            padding: 32px 16px;
            -webkit-font-smoothing: antialiased;
        }
        .card {
            max-width: 580px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .brand-header {
            background-color: #ffffff;
            padding: 28px 24px 20px 24px;
            text-align: center;
            border-bottom: 3px solid {{ $themeColor }};
        }
        .hero-banner {
            background-color: #f8fafc;
            padding: 20px 24px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }
        .hero-title {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .hero-subtitle {
            margin: 4px 0 0;
            font-size: 13px;
            color: #64748b;
        }
        .content {
            padding: 32px 28px;
            line-height: 1.6;
            color: #334155;
            font-size: 15px;
        }
        .btn {
            display: inline-block;
            background-color: {{ $themeColor }};
            color: #ffffff !important;
            text-decoration: none;
            padding: 13px 32px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid {{ $themeColor }};
            border-radius: 6px;
            padding: 14px 16px;
            margin: 24px 0;
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
        }
        .footer {
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            background-color: #f8fafc;
        }
        .footer a {
            color: #64748b;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand-header">
            @if(!empty($logoUrl))
                <a href="{{ url('/') }}" target="_blank" style="text-decoration: none; display: inline-block;">
                    <img src="{{ $logoUrl }}" alt="{{ $storeName }}" height="42" style="max-height: 42px; max-width: 200px; width: auto; border: 0; display: block; margin: 0 auto;" />
                </a>
            @else
                <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: {{ $themeColor }};">{{ $storeName }}</h1>
            @endif
        </div>

        <div class="hero-banner">
            <h2 class="hero-title">Reset Your Password</h2>
            <p class="hero-subtitle">{{ $storeName }} Account Security</p>
        </div>

        <div class="content">
            <p style="margin-top: 0;">Hello <strong>{{ $customer->name }}</strong>,</p>
            <p>We received a request to reset your password for your <strong>{{ $storeName }}</strong> account. Click the button below to choose a new password:</p>

            <div style="text-align: center; margin: 28px 0;">
                <a href="{{ $resetUrl }}" class="btn" target="_blank">Reset Password &rarr;</a>
            </div>

            <div class="info-box">
                <strong style="color: #334155;">Important Notice:</strong><br>
                This password reset link will expire in 60 minutes.<br>
                If you did not make this request, you can safely ignore this email — your account remains completely secure.
            </div>

            <p style="font-size: 12px; color: #94a3b8; word-break: break-all; margin-bottom: 0;">
                If you are having trouble clicking the button, copy and paste the URL below into your web browser:<br>
                <a href="{{ $resetUrl }}" style="color: {{ $themeColor }};">{{ $resetUrl }}</a>
            </p>
        </div>

        <div class="footer">
            <p style="margin: 0 0 6px 0; font-weight: 600; color: #475569;">{{ $storeName }}</p>
            <p style="margin: 0;">&copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
