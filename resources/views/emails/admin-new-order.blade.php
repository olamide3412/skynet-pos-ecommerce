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
    <title>New Order Alert #{{ $order->tracking_number }}</title>
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
            max-width: 600px;
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
            padding: 30px 24px;
        }
        .badge {
            display: inline-block;
            background: #fff7ed;
            color: {{ $themeColor }};
            border: 1px solid {{ $themeColor }};
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 700;
            font-family: monospace;
        }
        .btn {
            display: inline-block;
            background-color: {{ $themeColor }};
            color: #ffffff !important;
            text-decoration: none;
            padding: 13px 28px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 18px 0;
        }
        .details-table td {
            padding: 9px 0;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }
        .details-table .label {
            color: #64748b;
            width: 40%;
            font-weight: 500;
        }
        .details-table .value {
            color: #0f172a;
            font-weight: 600;
        }
        .footer {
            padding: 22px 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
            background: #f8fafc;
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
            <h2 class="hero-title">New Store Order Received</h2>
            <p class="hero-subtitle">{{ $storeName }} Backoffice Notification</p>
        </div>

        <div class="content">
            <p style="margin-top: 0; font-size: 15px; color: #334155;">
                A new order has been received on the storefront and is awaiting fulfillment.
            </p>
            
            <table class="details-table" role="presentation">
                <tr>
                    <td class="label">Tracking Ref:</td>
                    <td class="value"><span class="badge">{{ $order->tracking_number }}</span></td>
                </tr>
                <tr>
                    <td class="label">Customer Name:</td>
                    <td class="value">{{ $order->billing_name }}</td>
                </tr>
                <tr>
                    <td class="label">Customer Email:</td>
                    <td class="value">{{ $order->billing_email ?? $order->guest_email }}</td>
                </tr>
                <tr>
                    <td class="label">Phone:</td>
                    <td class="value">{{ $order->billing_phone ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Total Amount:</td>
                    <td class="value" style="font-size: 16px; color: {{ $themeColor }}; font-weight: 800;">₦{{ number_format($order->total_amount, 2) }}</td>
                </tr>
                @if($order->coupon_code)
                    <tr>
                        <td class="label">Coupon Applied:</td>
                        <td class="value" style="color: #16a34a;">{{ $order->coupon_code }} (-₦{{ number_format($order->discount_amount, 2) }})</td>
                    </tr>
                @endif
                <tr>
                    <td class="label">Shipping Fee:</td>
                    <td class="value">{{ $order->shipping_fee > 0 ? '₦' . number_format($order->shipping_fee, 2) : ($order->delivery_method === 'pickup' ? 'Store Pickup (Free)' : 'Free Shipping') }}</td>
                </tr>
                <tr>
                    <td class="label">Delivery Method:</td>
                    <td class="value">{{ ucfirst($order->delivery_method ?? 'delivery') }}</td>
                </tr>
                <tr>
                    <td class="label">Payment:</td>
                    <td class="value">
                        {{ in_array(strtolower($order->payment_method ?? ''), ['cod', 'pod']) ? 'Payment on Delivery (POD)' : ($order->payment_method === 'bank_transfer' ? 'Direct Bank Transfer' : strtoupper($order->payment_method ?? 'card')) }}
                    </td>
                </tr>
                <tr>
                    <td class="label">Shipping Address:</td>
                    <td class="value" style="color: #475569; font-weight: 400;">{{ $order->billing_address ?? 'Store Pickup' }}</td>
                </tr>
            </table>

            <div style="text-align: center; margin-top: 26px;">
                <a href="{{ url('/admin/orders/' . $order->id) }}" class="btn" target="_blank">
                    Open &amp; Process in Admin &rarr;
                </a>
            </div>
        </div>

        <div class="footer">
            <p style="margin: 0 0 6px 0;">Automated Store Alert &bull; {{ $storeName }} Management System</p>
            <p style="margin: 0; font-size: 11px;">&copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
