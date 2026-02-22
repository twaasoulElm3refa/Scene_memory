<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Event Approved</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f4f6f9;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; padding:30px; box-shadow:0 5px 15px rgba(0,0,0,0.05);">
                    
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <h2 style="color:#2d3748; margin:0;">🎉 Congratulations!</h2>
                        </td>
                    </tr>

                    <tr>
                        <td style="color:#4a5568; font-size:16px; line-height:1.6;">
                            <p>Hello <strong>{{ $event->user->name }}</strong>,</p>

                            <p>
                                We're happy to inform you that your event request has been 
                                <strong style="color:#16a34a;">approved successfully</strong>.
                            </p>

                            <p>
                                <strong>Event Title:</strong> {{ $event->title }} <br>
                                <strong>Status:</strong> Approved
                            </p>

                            <p>
                                Your event is now live on our platform and visible to users.
                            </p>

                            <p style="margin-top:25px;">
                                If you have any questions, feel free to contact our support team.
                            </p>

                            <p style="margin-top:30px;">
                                Best Regards,<br>
                                <strong>{{ config('app.name') }} Team</strong>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>