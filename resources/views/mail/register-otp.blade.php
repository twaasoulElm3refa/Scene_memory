@component('mail::message')
# Verify your Scemory account

Your verification code is:

@component('mail::panel')
{{ $otp }}
@endcomponent

This code expires in 10 minutes.

If you did not request this registration, you can safely ignore this email.

Thanks,<br>
Scemory
@endcomponent
