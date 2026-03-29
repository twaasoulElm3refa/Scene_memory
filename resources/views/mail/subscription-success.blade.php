<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Subscription Activated</title>
</head>
<body style="font-family: Arial, sans-serif; background: #f5f7fb; padding: 20px;">

    <div style="max-width:600px;margin:auto;background:#fff;padding:25px;border-radius:10px;border:1px solid #eee;">

        <h2 style="color:#4F46E5;">🎉 Congratulations, {{ $user->name }}!</h2>

        <p style="font-size:16px;color:#333;">
            Your subscription has been activated successfully.
        </p>

        <div style="background:#f1f5f9;padding:15px;border-radius:8px;margin-top:15px;">
            <p><strong>Plan:</strong> {{ $license->name ?? 'N/A' }}</p>
            <p><strong>Status:</strong> Active ✅</p>
        </div>

        <p style="margin-top:20px;color:#555;">
            You can now enjoy all premium features of your plan.
        </p>

        <hr style="margin:20px 0;">

        <p style="font-size:12px;color:#999;">
            If you didn’t request this, please contact support.
        </p>

    </div>

</body>
</html>
