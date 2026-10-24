@component('mail::message')
# Recover your Scemory account

Use this one-time code to securely reconnect Google to your existing Scemory account:

@component('mail::panel')
{{ $code }}
@endcomponent

This code expires in 10 minutes. If you did not try to sign in with Google, ignore this email.

Thanks,<br>
Scemory
@endcomponent
