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
    <title>Order Status Update #{{ $order->tracking_number }}</title>
    <style>
        body {
            margin: 0;
            padding: 32px 16px;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
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
            padding: 22px 24px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }
        .hero-title {
            margin: 0;
            font-size: 20px;
            font-weight: 800;
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
        }
        .status-badge {
            display: inline-block;
            background: #fff7ed;
            color: {{ $themeColor }};
            border: 2px solid {{ $themeColor }};
            padding: 8px 18px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 14px 0 18px 0;
        }
        .btn {
            display: inline-block;
            background-color: {{ $themeColor }};
            color: #ffffff !important;
            text-decoration: none;
            padding: 13px 32px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .footer {
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
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
                <a href="{{ $storeUrl }}" target="_blank" style="text-decoration: none; display: inline-block;">
                    <img src="{{ $logoUrl }}" alt="{{ $storeName }}" height="42" style="max-height: 42px; max-width: 200px; width: auto; border: 0; display: block; margin: 0 auto;" />
                </a>
            @else
                <h1 style="margin: 0; font-size: 24px; font-weight: 800; color: {{ $themeColor }};">{{ $storeName }}</h1>
            @endif
        </div>

        <div class="hero-banner">
            <h2 class="hero-title">Order Status Update</h2>
            <p class="hero-subtitle">Tracking #{{ $order->tracking_number }}</p>
        </div>

        <div class="content">
            <p style="margin-top: 0; font-size: 15px; color: #334155;">
                Hello <strong>{{ $order->billing_name }}</strong>,
            </p>
            <p style="font-size: 14px; color: #475569; line-height: 1.6;">
                There is an update regarding your order <strong>#{{ $order->tracking_number }}</strong>:
            </p>

            <div style="text-align: center;">
                <div class="status-badge">
                    Current Status: {{ ucfirst($status) }}
                </div>
            </div>

            <p style="font-size: 14px; color: #475569; line-height: 1.6;">
                @if($status === 'processing')
                    Your order is confirmed and our fulfillment team is packaging your items for dispatch.
                @elseif($status === 'shipped')
                    Your package has been dispatched and is currently on its way to your delivery address.
                @elseif($status === 'completed')
                    Your order has been completed and delivered. Thank you for shopping with us!
                @elseif($status === 'cancelled')
                    This order has been cancelled. If you have any questions or require assistance, please reply to this email.
                @else
                    Your order status has been updated to {{ ucfirst($status) }}.
                @endif
            </p>

            <div style="text-align: center; margin-top: 28px;">
                <a href="{{ \App\Models\StoreSetting::getBaseUrl('track/' . $order->tracking_number) }}" class="btn" target="_blank">Track Order Live Online &rarr;</a>
            </div>
        </div>

        <div class="footer">
            <p style="margin: 0 0 6px 0;">Need help? Reply directly to this email or reach us at <a href="mailto:{{ $companyEmail }}">{{ $companyEmail }}</a></p>
            <p style="margin: 0; font-size: 11px;">&copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
