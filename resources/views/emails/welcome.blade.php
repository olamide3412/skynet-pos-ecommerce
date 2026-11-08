@php
    $themeColor = $themeColor ?? \App\Models\StoreSetting::get('theme_primary_color', '#FF4B00');
    $storeUrl   = \App\Models\StoreSetting::getBaseUrl();
    $logoPath   = \App\Models\StoreSetting::getLogoPath();
    $logoUrl    = null;

    if (isset($message) && is_object($message) && method_exists($message, 'embed') && $logoPath && file_exists($logoPath)) {
        try {
            $logoUrl = $message->embed($logoPath);
        } catch (\Throwable $e) {
            $logoUrl = \App\Models\StoreSetting::getLogoUrl();
        }
    } else {
        $logoUrl = \App\Models\StoreSetting::getLogoUrl();
    }
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $storeName }}</title>
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
            padding: 24px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }
        .hero-title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
        }
        .hero-subtitle {
            margin: 6px 0 0;
            font-size: 13px;
            color: #64748b;
        }
        .content {
            padding: 32px 28px;
            line-height: 1.6;
            color: #334155;
            font-size: 15px;
        }
        .features-list {
            margin: 20px 0;
            padding: 0;
            list-style: none;
        }
        .features-list li {
            padding: 8px 0;
            font-size: 14px;
            color: #475569;
            display: flex;
            align-items: center;
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
        .footer {
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            background-color: #f8fafc;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand-header">
            @if(!empty($logoUrl))
                <a href="{{ $storeUrl }}" target="_blank" style="text-decoration: none; display: inline-block;">
                    <img src="{{ $logoUrl }}" alt="{{ $storeName }}" height="42" style="max-height: 42px; max-width: 200px; width: auto; border: 0; display: block; margin: 0 auto;" />
                </a>
            @else
                <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: {{ $themeColor }};">{{ $storeName }}</h1>
            @endif
        </div>

        <div class="hero-banner">
            <h2 class="hero-title">Welcome to {{ $storeName }}! 🎉</h2>
            <p class="hero-subtitle">Your storefront account is active and ready to go</p>
        </div>

        <div class="content">
            <p style="margin-top: 0;">Hello <strong>{{ $customer->name }}</strong>,</p>
            <p>We are delighted to have you with us! Your customer account has been created successfully.</p>
            
            <p style="font-weight: 600; color: #0f172a; margin-bottom: 8px;">Here is what you can do with your account:</p>
            <ul class="features-list">
                <li>✨ <strong>Express Checkout:</strong> Save multiple shipping addresses for fast orders.</li>
                <li>📦 <strong>Live Order Tracking:</strong> Track real-time status updates from payment to delivery.</li>
                <li>❤️ <strong>Wishlist:</strong> Save your favorite products for later purchase.</li>
                <li>📜 <strong>Order History:</strong> Download invoices and track past transactions at any time.</li>
            </ul>

            <div style="text-align: center; margin: 30px 0 10px 0;">
                <a href="{{ \App\Models\StoreSetting::getBaseUrl('shop') }}" class="btn" target="_blank">Start Shopping &rarr;</a>
            </div>
        </div>

        <div class="footer">
            <p style="margin: 0 0 6px 0; font-weight: 600; color: #475569;">{{ $storeName }}</p>
            <p style="margin: 0;">&copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
