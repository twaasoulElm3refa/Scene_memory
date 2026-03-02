<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Event Rejected</title>
</head>
<body style="margin:0; padding:0; font-family: Arial, sans-serif; background-color:#f4f6f9;">

    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff; border-radius:10px; padding:30px; box-shadow:0 5px 15px rgba(0,0,0,0.05);">
                    
                    <tr>
                        <td align="center" style="padding-bottom:20px;">
                            <h2 style="color:#dc2626; margin:0;">Event Request Not Approved</h2>
                        </td>
                    </tr>

                    <tr>
                        <td style="color:#4a5568; font-size:16px; line-height:1.6;">
                            <p>Hello <strong>{{ $event->user->name }}</strong>,</p>

                            <p>
                                Thank you for submitting your event 
                                "<strong>{{ $event->title }}</strong>".
                            </p>

                            <p>
                                After reviewing your request, we regret to inform you that it has 
                                <strong style="color:#dc2626;">not been approved</strong> at this time.
                            </p>

                            @if(isset($reason))
                                <p>
                                    <strong>Reason:</strong> {{ $reason }}
                                </p>
                            @endif

                            <p style="margin-top:20px;">
                                You are welcome to review the event details and submit a new request after making the necessary updates.
                            </p>

                            <p style="margin-top:30px;">
                                If you believe this was a mistake or need clarification, please contact our support team.
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