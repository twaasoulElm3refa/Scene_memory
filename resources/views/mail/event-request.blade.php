<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Submission</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f6f9; font-family:Arial, sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
    <tr>
        <td align="center">

            <!-- Card -->
            <table width="600" cellpadding="0" cellspacing="0" 
                   style="background:#ffffff; border-radius:12px; padding:40px; box-shadow:0 5px 20px rgba(0,0,0,0.05);">

                <!-- Header -->
                <tr>
                    <td align="center" style="padding-bottom:25px;">
                        <h2 style="margin:0; color:#2d3748; font-size:24px;">
                            🎉 Event Submission Received
                        </h2>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="color:#4a5568; font-size:15px; line-height:1.8;">

                        <p>Dear <strong>{{ $event->user->name ?? 'User' }}</strong>,</p>

                        <p>
                            We’re pleased to inform you that your event request has been successfully submitted.
                        </p>

                        <!-- Event Box -->
                        <div style="background:#f7fafc; padding:15px 20px; border-radius:8px; margin:20px 0;">
                            <p style="margin:0;">
                                <strong>Event Title:</strong> {{ $event->title }}
                            </p>
                            <p style="margin:5px 0 0 0;">
                                <strong>Status:</strong> Pending Review
                            </p>
                        </div>

                        <p>
                            Our team will carefully review your submission.  
                            Once the review process is complete, you will receive a notification with the update.
                        </p>

                        <p>
                            Thank you for choosing our platform.
                        </p>

                        <p style="margin-top:30px;">
                            Best regards,<br>
                            <strong>Scene Memory Team</strong>
                        </p>

                    </td>
                </tr>

            </table>
            <!-- End Card -->

        </td>
    </tr>
</table>

</body>
</html>