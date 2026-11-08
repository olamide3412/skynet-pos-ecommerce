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
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Order Confirmation #{{ $order->tracking_number }}</title>
    <!--[if mso]>
    <style>
        * { font-family: sans-serif !important; }
    </style>
    <![endif]-->
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table {
            border-collapse: separate;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
            width: 100%;
        }
        td {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 14px;
            vertical-align: top;
        }
        .wrapper {
            background-color: #f1f5f9;
            width: 100%;
            padding: 30px 10px;
        }
        .main-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        .header {
            padding: 32px 30px 24px 30px;
            background-color: #ffffff;
            border-bottom: 3px solid {{ $themeColor }};
            text-align: center;
        }
        .header-title {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
        }
        .header-subtitle {
            margin: 6px 0 0 0;
            font-size: 13px;
            color: #64748b;
        }
        .content {
            padding: 30px;
        }
        .greeting {
            font-size: 15px;
            line-height: 1.6;
            color: #334155;
            margin: 0 0 20px 0;
        }
        .order-badge-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin-bottom: 24px;
        }
        .badge-table td {
            font-size: 13px;
            padding: 4px 0;
        }
        .badge-label {
            color: #64748b;
            font-weight: 500;
        }
        .badge-value {
            color: #0f172a;
            font-weight: 700;
            text-align: right;
            font-family: monospace, sans-serif;
        }
        .items-table {
            width: 100%;
            margin-bottom: 24px;
            border-collapse: collapse;
        }
        .items-table th {
            text-align: left;
            padding: 10px 12px;
            background-color: #f8fafc;
            color: #475569;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .items-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #1e293b;
        }
        .totals-table {
            width: 100%;
            margin-top: 10px;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 12px;
            font-size: 13px;
        }
        .totals-label {
            color: #64748b;
            text-align: right;
        }
        .totals-value {
            text-align: right;
            font-weight: 600;
            color: #1e293b;
            width: 140px;
        }
        .grand-total-row td {
            padding: 14px 12px 6px 12px;
            border-top: 2px solid #e2e8f0;
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
        }
        .btn-primary {
            display: inline-block;
            background-color: {{ $themeColor }};
            color: #ffffff !important;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            padding: 13px 28px;
            border-radius: 8px;
            text-align: center;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px 20px;
            margin: 24px 0;
            font-size: 13px;
            line-height: 1.6;
            color: #334155;
        }
        .footer {
            padding: 24px 30px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 12px;
            line-height: 1.6;
            color: #64748b;
        }
        .footer a {
            color: #475569;
            text-decoration: underline;
        }
        @media only screen and (max-width: 600px) {
            .content { padding: 20px 16px !important; }
            .header { padding: 24px 16px !important; }
            .main-container { width: 100% !important; border-radius: 0 !important; }
            .wrapper { padding: 0 !important; }
        }
    </style>
</head>
<body>
    <!-- Hidden preheader text for email clients -->
    <div style="display: none; max-height: 0px; overflow: hidden; mso-hide: all; font-size: 1px; line-height: 1px; color: #fff; opacity: 0;">
        Order #{{ $order->tracking_number }} confirmed: {{ $order->items->count() }} item(s) totalling ₦{{ number_format($order->total_amount, 2) }}.
    </div>

    <table class="wrapper" role="presentation" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table class="main-container" role="presentation" cellpadding="0" cellspacing="0">
                    <!-- Brand Header -->
                    <tr>
                        <td class="header">
                            @if(!empty($logoUrl))
                                <a href="{{ $storeUrl }}" target="_blank" style="text-decoration: none; display: inline-block;">
                                    <img src="{{ $logoUrl }}" alt="{{ $storeName }}" height="42" style="max-height: 42px; max-width: 200px; width: auto; border: 0; display: block; margin: 0 auto 8px auto;" />
                                </a>
                            @else
                                <h1 class="header-title" style="color: {{ $themeColor }};">{{ $storeName }}</h1>
                            @endif
                            <p class="header-subtitle">Official Order Confirmation &amp; Receipt</p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td class="content">
                            <p class="greeting">
                                Hi <strong>{{ $order->billing_name }}</strong>,<br>
                                Thank you for your order! We have received your purchase and our fulfillment team is currently processing your package.
                            </p>

                            <!-- Order Reference Card -->
                            <div class="order-badge-card">
                                <table class="badge-table" role="presentation">
                                    <tr>
                                        <td class="badge-label">Tracking Number:</td>
                                        <td class="badge-value" style="color: {{ $themeColor }};">{{ $order->tracking_number }}</td>
                                    </tr>
                                    <tr>
                                        <td class="badge-label">Order Date:</td>
                                        <td class="badge-value">{{ $order->created_at ? $order->created_at->format('M d, Y') : date('M d, Y') }}</td>
                                    </tr>
                                    <tr>
                                        <td class="badge-label">Payment Method:</td>
                                        <td class="badge-value" style="font-family: inherit;">
                                            {{ in_array(strtolower($order->payment_method ?? ''), ['cod', 'pod']) ? 'Payment on Delivery (POD)' : ($order->payment_method === 'bank_transfer' ? 'Direct Bank Transfer' : strtoupper($order->payment_method ?? 'Card')) }}
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Purchased Items Table -->
                            <table class="items-table" role="presentation">
                                <thead>
                                    <tr>
                                        <th>Item</th>
                                        <th style="text-align: center; width: 40px;">Qty</th>
                                        <th style="text-align: right; width: 90px;">Price</th>
                                        <th style="text-align: right; width: 100px;">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $computedSubtotal = 0;
                                    @endphp
                                    @foreach($order->items as $item)
                                        @php
                                            $lineTotal = $item->price * $item->quantity;
                                            $computedSubtotal += $lineTotal;
                                        @endphp
                                        <tr>
                                            <td>
                                                <strong style="color: #0f172a;">{{ $item->product?->name ?? 'Product Item' }}</strong>
                                                @if($item->unit_name)
                                                    <div style="font-size: 11px; color: #64748b; margin-top: 2px;">Unit: {{ $item->unit_name }}</div>
                                                @endif
                                                @if($item->variant)
                                                    <div style="font-size: 11px; color: #64748b;">Variant: {{ $item->variant }}</div>
                                                @endif
                                            </td>
                                            <td style="text-align: center; color: #475569;">{{ $item->quantity }}</td>
                                            <td style="text-align: right; color: #475569;">₦{{ number_format($item->price, 2) }}</td>
                                            <td style="text-align: right; font-weight: 600; color: #0f172a;">₦{{ number_format($lineTotal, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>

                            <!-- Financial Breakdown -->
                            <table class="totals-table" role="presentation">
                                <tr>
                                    <td class="totals-label">Subtotal:</td>
                                    <td class="totals-value">₦{{ number_format($order->subtotal > 0 ? $order->subtotal : $computedSubtotal, 2) }}</td>
                                </tr>
                                @if($order->discount_amount > 0)
                                    <tr>
                                        <td class="totals-label" style="color: #16a34a;">
                                            Coupon Discount {{ $order->coupon_code ? '(' . $order->coupon_code . ')' : '' }}:
                                        </td>
                                        <td class="totals-value" style="color: #16a34a;">-₦{{ number_format($order->discount_amount, 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="totals-label">
                                        Shipping ({{ ucfirst($order->delivery_method ?? 'delivery') }}):
                                    </td>
                                    <td class="totals-value">
                                        {{ $order->shipping_fee > 0 ? '₦' . number_format($order->shipping_fee, 2) : ($order->delivery_method === 'pickup' ? 'Free Pickup' : 'Free Shipping') }}
                                    </td>
                                </tr>
                                @if($order->tax_amount > 0)
                                    <tr>
                                        <td class="totals-label">Tax / VAT:</td>
                                        <td class="totals-value">₦{{ number_format($order->tax_amount, 2) }}</td>
                                    </tr>
                                @endif
                                @if($order->service_fee > 0)
                                    <tr>
                                        <td class="totals-label">Service Fee:</td>
                                        <td class="totals-value">₦{{ number_format($order->service_fee, 2) }}</td>
                                    </tr>
                                @endif
                                <tr class="grand-total-row">
                                    <td style="text-align: right;">Grand Total:</td>
                                    <td style="text-align: right; color: #0f172a;">₦{{ number_format($order->total_amount, 2) }}</td>
                                </tr>
                            </table>

                            <!-- Delivery Details -->
                            <div class="info-card">
                                <strong style="color: #0f172a; display: block; margin-bottom: 6px;">Fulfillment &amp; Delivery Details</strong>
                                <div><strong>Recipient:</strong> {{ $order->billing_name }}</div>
                                @if($order->billing_phone)
                                    <div><strong>Contact Phone:</strong> {{ $order->billing_phone }}</div>
                                @endif
                                <div><strong>Delivery Method:</strong> {{ ucfirst($order->delivery_method ?? 'standard delivery') }}</div>
                                @if($order->billing_address)
                                    <div><strong>Delivery Address:</strong> {{ $order->billing_address }}</div>
                                @endif
                            </div>

                            <!-- Live Tracking Call to Action -->
                            <table role="presentation" style="margin: 28px 0 10px 0; width: 100%;">
                                <tr>
                                    <td align="center">
                                        <a href="{{ \App\Models\StoreSetting::getBaseUrl('track/' . $order->tracking_number) }}" class="btn-primary" target="_blank">
                                            Track Order Status Online &rarr;
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CAN-SPAM Compliant Footer -->
                    <tr>
                        <td class="footer">
                            <p style="margin: 0 0 8px 0; font-weight: 600; color: #334155;">{{ $storeName }}</p>
                            @if(!empty($companyAddress))
                                <p style="margin: 0 0 6px 0;">{{ $companyAddress }}</p>
                            @endif
                            <p style="margin: 0 0 10px 0;">
                                Questions about this order? Reply to this email, write to <a href="mailto:{{ $companyEmail }}">{{ $companyEmail }}</a>@if(!empty($companyPhone)), or call {{ $companyPhone }}@endif.
                            </p>
                            <p style="margin: 0; font-size: 11px; color: #94a3b8;">
                                This receipt was sent to {{ $order->billing_email ?? $order->guest_email }} regarding order #{{ $order->tracking_number }}.<br>
                                &copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
