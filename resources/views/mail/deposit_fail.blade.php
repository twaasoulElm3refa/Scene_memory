<!DOCTYPE html>
<html lang="ar">
<head>
    <meta charset="UTF-8">
    <title>فشل العملية</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fa;font-family:Arial">

    <div style="max-width:600px;margin:40px auto;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,0.05)">

        <!-- Header -->
        <div style="background:linear-gradient(135deg,#ef4444,#dc2626);padding:30px;text-align:center;color:white">
            <h1 style="margin:0;font-size:24px">❌ فشل العملية</h1>
        </div>

        <!-- Content -->
        <div style="padding:30px;text-align:center">

            <h2 style="margin-bottom:10px">مرحباً {{ $name }}</h2>

            <p style="color:#6b7280;font-size:16px">
                للأسف، لم يتم شحن رصيدك 😔
            </p>

            <div style="margin:25px 0;font-size:28px;font-weight:bold;color:#ef4444">
                ${{ $amount }}
            </div>

            <p style="color:#9ca3af">
                حاول مرة أخرى أو تواصل مع الدعم
            </p>

            <!-- Button -->
            <a href="{{ config('app.url') }}"
               style="display:inline-block;margin-top:20px;padding:14px 30px;background:#ef4444;color:white;text-decoration:none;border-radius:12px;font-weight:bold">
                إعادة المحاولة
            </a>

        </div>

        <!-- Footer -->
        <div style="padding:20px;text-align:center;color:#9ca3af;font-size:12px">
            © {{ date('Y') }} جميع الحقوق محفوظة
        </div>

    </div>

</body>
</html>
