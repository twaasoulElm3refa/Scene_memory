@component('mail::message')
# Account & Data Deletion Confirmation

You recently requested the deletion of your Scemory account and associated personal data.

**Reference:** `{{ $reference }}`

To confirm this request, enter this one-time confirmation code in your Scemory profile:

@component('mail::panel')
{{ $code }}
@endcomponent

This code expires in 15 minutes.

**Warning:** Confirming this request will permanently remove your personal profile, revoke your active sessions, and anonymize your identity in accordance with our data protection policies.

If you did not request to delete your account, please sign in to your Scemory account immediately and change your security settings.

Thanks,<br>
Scemory Support Team
@endcomponent
