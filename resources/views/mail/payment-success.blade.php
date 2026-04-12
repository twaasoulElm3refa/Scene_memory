<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>Payment Success</title>
</head>

<body style="margin:0; padding:0; font-family: Arial, sans-serif; background:#f4f6f9;">

    <div
        style="max-width:600px;margin:40px auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.08);">

        <!-- Header -->
        <div style="background:#22c55e;padding:20px;text-align:center;color:#fff;">
            <h2 style="margin:0;">Payment Successful 🎉</h2>
        </div>

        <!-- Body -->
        <div style="padding:30px;color:#333;">

            <h3 style="margin-bottom:10px;">Hello {{ $order->user->name }} 👋</h3>

            <p style="font-size:15px;line-height:1.6;">
                Your payment has been completed successfully.
            </p>

            <!-- Info Box -->
            <div style="background:#f9fafb;padding:15px;border-radius:10px;margin:20px 0;">
                <p style="margin:5px 0;"><strong>Amount Paid:</strong> ${{ $order->amount }}</p>
                <p style="margin:5px 0;"><strong>Payment Method:</strong> PayPal</p>
            </div>

            <p style="font-size:14px;color:#666;">
                Thank you for your purchase 🙏
            </p>

        </div>

        <!-- Footer -->
        <div style="background:#f1f5f9;padding:15px;text-align:center;font-size:12px;color:#888;">
            © {{ date('Y') }} SceMory Team. All rights reserved.
        </div>

    </div>

</body>

</html>
