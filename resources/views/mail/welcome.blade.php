@component('mail::message')
# 👋 أهلاً بيك يا {{ $user->name }}

احنا مبسوطين إنك انضميت لينا 🎉
دلوقتي تقدر تبدأ تستخدم كل مميزات المنصة بسهولة.

@component('mail::panel')
✨ حسابك اتعمل بنجاح
📧 {{ $user->email }}
@endcomponent

@component('mail::button', ['url' => config('app.url')])
ابدأ الآن 🚀
@endcomponent

لو محتاج أي مساعدة، احنا دايماً هنا ❤️

Thanks,<br>
{{ config('app.name') }}
@endcomponent
