<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Status Update #{{ $order->tracking_number }}</title>
    <style>
        body { margin: 0; padding: 24px; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .header { background: #0f172a; color: #ffffff; padding: 28px 24px; text-align: center; }
        .content { padding: 28px 24px; }
        .status-badge { display: inline-block; background: #e0f2fe; color: #0369a1; padding: 8px 16px; border-radius: 8px; font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin: 12px 0 16px 0; }
        .btn { display: inline-block; background: #0f172a; color: #ffffff !important; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; font-size: 14px; }
        .footer { padding: 20px 24px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; background: #f8fafc; }
        .footer a { color: #64748b; text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <h1 style="margin: 0; font-size: 20px; font-weight: 800;">{{ $storeName }}</h1>
            <p style="margin: 4px 0 0; opacity: 0.8; font-size: 13px;">Order Status Update</p>
        </div>
        <div class="content">
            <p style="margin-top: 0; font-size: 15px; color: #334155;">
                Hello <strong>{{ $order->billing_name }}</strong>,
            </p>
            <p style="font-size: 14px; color: #475569; line-height: 1.6;">
                There is a status update on your order <strong>#{{ $order->tracking_number }}</strong>:
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

            <div style="text-align: center; margin-top: 24px;">
                <a href="{{ url('/track/' . $order->tracking_number) }}" class="btn" target="_blank">Track Order Live Online &rarr;</a>
            </div>
        </div>
        <div class="footer">
            <p style="margin: 0 0 6px 0;">Need help? Reply directly to this email or reach us at <a href="mailto:{{ StoreSetting::get('company_email', config('mail.from.address')) }}">{{ StoreSetting::get('company_email', config('mail.from.address')) }}</a></p>
            <p style="margin: 0; font-size: 11px;">&copy; {{ date('Y') }} {{ $storeName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
