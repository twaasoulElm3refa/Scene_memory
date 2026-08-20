<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event needs manual review</title>
</head>
<body style="margin:0; padding:0; font-family:Arial,sans-serif; background:#f4f6f9;">
    <table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:10px; padding:30px;">
                    <tr>
                        <td style="color:#374151; font-size:16px; line-height:1.6;">
                            <h2 style="margin-top:0; color:#92400e;">AI requires manual review</h2>
                            <p><strong>Event:</strong> #{{ $eventId }} — {{ $eventTitle }}</p>
                            <p><strong>Request:</strong> #{{ $requestId }}</p>
                            <p>
                                <strong>Owner:</strong>
                                {{ $ownerName ?: 'Unknown' }}
                                @if($ownerEmail)
                                    ({{ $ownerEmail }})
                                @endif
                            </p>
                            <p><strong>AI confidence:</strong> {{ $confidence }}</p>
                            <p><strong>AI reason:</strong> {{ $reason }}</p>
                            <p style="margin-top:28px;">
                                <a href="{{ $reviewUrl }}" style="display:inline-block; padding:12px 20px; border-radius:8px; background:#4f46e5; color:#fff; text-decoration:none;">
                                    Review event request
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
