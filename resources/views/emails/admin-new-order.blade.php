<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Order Alert #{{ $order->tracking_number }}</title>
    <style>
        body { margin: 0; padding: 24px; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .header { background: #0f172a; color: #ffffff; padding: 28px 24px; text-align: center; }
        .content { padding: 28px 24px; }
        .badge { display: inline-block; background: #e0f2fe; color: #0369a1; padding: 4px 10px; border-radius: 6px; font-weight: 700; font-family: monospace; }
        .btn { display: inline-block; background: #0f172a; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; }
        .details-table { width: 100%; border-collapse: collapse; margin: 16px 0; }
        .details-table td { padding: 8px 0; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
        .details-table .label { color: #64748b; width: 40%; font-weight: 500; }
        .details-table .value { color: #0f172a; font-weight: 600; }
        .footer { padding: 20px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; background: #f8fafc; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 style="margin: 0; font-size: 20px; font-weight: 800;">New Store Order Placed</h1>
            <p style="margin: 4px 0 0; opacity: 0.8; font-size: 13px;">{{ $storeName }} Backoffice Notification</p>
        </div>
        <div class="content">
            <p style="margin-top: 0; font-size: 15px; color: #334155;">
                A new order has been received on the storefront and is awaiting fulfillment.
            </p>
            
            <table class="details-table" role="presentation">
                <tr>
                    <td class="label">Order Tracking Ref:</td>
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
                    <td class="value" style="font-size: 16px; color: #0f172a;">₦{{ number_format($order->total_amount, 2) }}</td>
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
                        ({{ ucfirst($order->status) }})
                    </td>
                </tr>
                <tr>
                    <td class="label">Items:</td>
                    <td class="value">{{ $order->items->count() }} item line(s)</td>
                </tr>
            </table>

            <div style="text-align: center; margin-top: 24px;">
                <a href="{{ url('/admin/orders/' . $order->id) }}" class="btn">View &amp; Manage Order in Admin</a>
            </div>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ $storeName }} Backoffice Management System
        </div>
    </div>
</body>
</html>
