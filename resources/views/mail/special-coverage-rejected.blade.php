<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تعذر قبول طلب التغطية الخاصة</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f4f6f9;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; padding:30px; box-shadow:0 5px 15px rgba(0,0,0,0.05);">
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <h2 style="color:#dc2626; margin:0;">تعذر قبول طلب التغطية الخاصة</h2>
                        </td>
                    </tr>
                    <tr>
                        <td style="color:#4a5568; font-size:16px; line-height:1.8;">
                            <p>مرحباً <strong>{{ $request->user?->name }}</strong>،</p>
                            <p>تمت مراجعة طلب التغطية الخاصة:</p>
                            <p><strong>{{ $request->event_name }}</strong></p>
                            <p>وللأسف لم تتم الموافقة عليه.</p>
                            <p><strong>السبب:</strong></p>
                            <p>{{ $request->rejection_reason }}</p>
                            <p style="margin-top:30px;">
                                شكراً لتواصلك معنا،<br>
                                <strong>فريق {{ config('app.name') }}</strong>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
