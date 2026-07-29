# Event Create Flow

1. `EventsRequest` (`app/Http/Requests/EventsRequest.php:25`) ← Laravel قبل `create()` → يتحقق من حقول الحدث/الصور/التاجز → DB: لا → Transaction: لا.
2. `EventUserCreateController::create()` (`app/Http/Controllers/api/home/EventUserCreateController.php:39`) ← route `/events/create/user` → يبدأ flow إنشاء الحدث → يستدعي `validateUserPhotoPayload()`.
3. `validateUserPhotoPayload()` (`EventUserCreateController.php:315`) ← `create():41` → يتحقق من الصور والوصف و`photo_tags_json` → يستدعي `uploadedMediaFiles()` و`PhotoQualityService::validate()` → DB: لا.
4. `PhotoQualityService::validate()` (`app/Services/PhotoQualityService.php:15`) ← `validateUserPhotoPayload():389` → يحلل جودة الصورة ويعيد metrics/errors → DB: لا.
5. `create()` (`EventUserCreateController.php:42`) ← بعد photo validation → يأخذ `$request->validated()` ثم `stripUploadOnlyData():43` → يجهز data فقط.
6. `DB::transaction()` (`EventUserCreateController.php:48`) ← `create()` → يبدأ كتابة الحدث وطلب المراجعة والتاجز وتجهيز jobs → Transaction: نعم.
7. `EventRepository::create()` (`app/Repositories/Eloquent/Events/EventRepository.php:13`) ← `create():57` → ينفذ `Events::create($data)` في جدول `events` → DB write: نعم.
8. `RequestRepository::createEventRequest()` (`app/Repositories/Eloquent/Requests/RequestRepository.php:43`) ← `create():58` → ينفذ `EventRequestCreate::create($data)` في `event_request_creates` → DB write: نعم.
9. `$event->update()` (`EventUserCreateController.php:59`) ← `create()` → يكتب slug للحدث → DB write: نعم.
10. `$event->translations()->create()` (`EventUserCreateController.php:60`) ← `create()` → ينشئ ترجمة عربية أولية للحدث → DB write: نعم.
11. `syncEventTags()` (`EventUserCreateController.php:619`) ← `create():65` → يعالج `tags_id/new_tags` ويربط الحدث → يستدعي `TagResolverService`.
12. `TagResolverService::resolve()` (`app/Services/TagResolverService.php:10`) ← `syncEventTags():656` → `firstOrCreate/restore` في `tags` → DB write: عند الحاجة.
13. `Event_Tags::create()/restore()` (`EventUserCreateController.php:668`) ← `syncEventTags()` → يربط `events` مع `tags` في `event__tags` → DB write: نعم.
14. `uploadedMediaFiles()` (`EventUserCreateController.php:439`) ← `create():66` → يرجع ملفات `urls` أو `photos` فقط → DB: لا.
15. `$file->store()` (`EventUserCreateController.php:90`) ← loop الصور → يخزن temp image في `public/images_temp` → Storage write: نعم.
16. `photoMetadataForIndex()` (`EventUserCreateController.php:458`) ← `create():104` → يجهز description/metrics/`tags_json` → يستدعي `normalizePhotoTagsPayload()` و`resolvePhotoTagIds()`.
17. `resolvePhotoTagIds()` (`EventUserCreateController.php:494`) ← `photoMetadataForIndex():462` → يتحقق من existing tags وينشئ new tags عبر `TagResolverService` → DB write: tags فقط عند الحاجة.
18. `new ProcessEventImageJob()` (`EventUserCreateController.php:115`) ← `create()` → يجهز job للصورة مع metadata → Queue لاحقًا، DB: لا الآن.
19. `new ProcessEventVideoJob()` (`EventUserCreateController.php:124`) ← `create()` عند video → يجهز job للفيديو → Queue لاحقًا، DB: لا الآن.
20. `dispatchPostCommitJobs()` (`EventUserCreateController.php:147,693`) ← بعد نجاح transaction → يرسل image batch/video jobs/AI job → Queue: نعم.
21. `ProcessEventImageJob::handle()` (`app/Jobs/ProcessEventImageJob.php:51`) ← Queue batch → يعالج الصورة، ينشئ `events_imges`, يحفظ `tags_json`, ويربط `images_tags` بـ`syncWithoutDetaching():167`.
22. `GenerateEventAiTagsJob::handle()` (`app/Jobs/GenerateEventAiTagsJob.php:53`) ← batch finally `dispatchPostCommitJobs():701` أو direct `:705` → moderation ثم AI tags → Queue: نعم.
23. `GenerateImageTagsService` (`app/Services/GenerateImageTagsService.php:27,88`) ← `GenerateEventAiTagsJob` → يرسل moderation ثم OpenRouter tags request ويفسر الرد → DB: لا.
24. `EventAiTagsPersistenceService::persist()` (`app/Services/EventAiTagsPersistenceService.php:21`) ← `GenerateEventAiTagsJob:154` → داخل transaction يحفظ AI event tags في `event__tags` وAI image tags في `images_tags`.
25. `TranslateEventJob::dispatch()` (`EventUserCreateController.php:148`) ← `create()` بعد jobs → Queue لترجمة الحدث؛ `handle()` يكتب `translations()->updateOrCreate()` في `app/Jobs/TranslateEventJob.php:46,68`.
26. `clearEventsCache()` (`EventUserCreateController.php:150,729`) ← `create()` → يمسح cache مفاتيح events/requests → DB: لا.
27. `ApiResponse::success()` (`app/Http/Controllers/concerns/ApiResponse.php:8`) ← `create():152` → يرجع JSON success مع `$event->load('translations','photos')` → Response.
28. Flow: `Request` → `EventsRequest` → `EventUserCreateController::create()` → `DB::transaction` → `EventRepository`/`RequestRepository`/translations/tags → temp media/jobs → `ProcessEventImageJob` → `GenerateEventAiTagsJob` → `GenerateImageTagsService` → `EventAiTagsPersistenceService` → `TranslateEventJob` → `clearEventsCache` → `success Response`.
