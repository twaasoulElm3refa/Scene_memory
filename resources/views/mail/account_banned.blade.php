<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Account Restriction Notice</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f4f6f9;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; padding:30px; box-shadow:0 5px 15px rgba(0,0,0,0.05);">
                    
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <h2 style="color:#dc2626; margin:0;">Account Restricted</h2>
                        </td>
                    </tr>

                    <tr>
                        <td style="color:#4a5568; font-size:16px; line-height:1.6;">
                            <p>Hello <strong>{{ $user->name ?? 'User' }}</strong>,</p>

                            <p>
                                Your account on <strong>{{ config('app.name') }}</strong> has been suspended due to a violation of platform content guidelines.
                            </p>

                            @if(!empty($reason))
                                <p style="background-color:#fee2e2; border-left:4px solid #ef4444; padding:12px; border-radius:4px;">
                                    <strong>Policy violation:</strong> {{ $reason }}
                                </p>
                            @endif

                            <p style="margin-top:20px;">
                                As a result, you are currently unable to submit new events or access restricted account features.
                            </p>

                            <p style="margin-top:20px;">
                                If you believe this restriction was placed in error or if you have questions, please reach out to our support team for assistance.
                            </p>

                            <p style="margin-top:30px;">
                                Best Regards,<br>
                                <strong>{{ config('app.name') }} Trust & Safety Team</strong>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>
