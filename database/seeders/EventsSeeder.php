<?php

namespace Database\Seeders;

use App\Jobs\TranslateEventJob;
use App\Models\Events;
use App\Models\EventTranslations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            [
                'user_id' => 1,
                'city_id' => 2, // القاهرة
                'sub_categorey_id' => 3, // سياحي
                'title' => 'رحلة نيلية ممتعة',
                'description' => 'جولة سياحية على النيل وزيارة أهم المعالم.',
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-03',
                'image' => null,
                'langitude' => '31.2357',
                'lattitude' => '30.0444',
            ],
            [
                'user_id' => 2,
                'city_id' => 1,
                'sub_categorey_id' => 2,
                'title' => 'معرض الفن الحديث',
                'description' => 'أحدث اللوحات الفنية من فنانين محليين ودوليين.',
                'start_date' => '2026-04-10',
                'end_date' => '2026-04-15',
                'image' => null,
                'langitude' => '46.6753',
                'lattitude' => '24.7136',
            ],
            [
                'user_id' => 3,
                'city_id' => 2, // القاهرة
                'sub_categorey_id' => 1, // رياضي
                'title' => 'بطولة كرة قدم محلية',
                'description' => 'بطولة بين الأندية المحلية للشباب.',
                'start_date' => '2026-05-05',
                'end_date' => '2026-05-08',
                'image' => null,
                'langitude' => '31.2357',
                'lattitude' => '30.0444',
            ],
            [
                'user_id' => 4,
                'city_id' => 1, // الرياض
                'sub_categorey_id' => 5, // تعليمي
                'title' => 'ورشة تعليمية لتعلم البرمجة',
                'description' => 'تعلم أساسيات البرمجة للأطفال والشباب.',
                'start_date' => '2026-06-12',
                'end_date' => '2026-06-14',
                'image' => null,
                'langitude' => '46.6753',
                'lattitude' => '24.7136',
            ],
            [
                'user_id' => 4,
                'city_id' => 2, // القاهرة
                'sub_categorey_id' => 3, // سياحي
                'title' => 'زيارة أهرامات الجيزة مع الغروب',
                'description' => 'جولة خاصة عند غروب الشمس مع مرشد سياحي ووجبة خفيفة.',
                'start_date' => '2026-11-05',
                'end_date' => '2026-11-05',
                'image' => null,
                'langitude' => '31.1342',
                'lattitude' => '29.9792',
            ],
            [
                'user_id' => 5,
                'city_id' => 1, // الرياض
                'sub_categorey_id' => 5, // تعليمي
                'title' => 'دورة تدريبية في الذكاء الاصطناعي',
                'description' => 'تعرف على أساسيات الـ AI والـ Machine Learning بطريقة عملية.',
                'start_date' => '2026-11-15',
                'end_date' => '2026-11-17',
                'image' => null,
                'langitude' => '46.6753',
                'lattitude' => '24.7136',
            ],
            [
                'user_id' => 1,
                'city_id' => 2, // القاهرة
                'sub_categorey_id' => 4, // ثقافي
                'title' => 'معرض الكتاب الدولي بالقاهرة',
                'description' => 'أكبر تجمع لدور النشر العربية والعالمية مع فعاليات ثقافية.',
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-10',
                'image' => null,
                'langitude' => '31.2357',
                'lattitude' => '30.0444',
            ],
            [
                'user_id' => 2,
                'city_id' => 1, // الرياض
                'sub_categorey_id' => 1, // رياضي
                'title' => 'تحدي اللياقة الشتوي',
                'description' => 'فعالية رياضية جماعية تشمل تمارين ومسابقات ممتعة.',
                'start_date' => '2026-12-20',
                'end_date' => '2026-12-20',
                'image' => null,
                'langitude' => '46.7220',
                'lattitude' => '24.7743',
            ],
            [
                'user_id' => 3,
                'city_id' => 2, // القاهرة
                'sub_categorey_id' => 2, // فني
                'title' => 'عرض مسرحي: "الليلة الكبيرة"',
                'description' => 'عرض مسرحي كوميدي موسيقي من إنتاج فرقة محلية مميزة.',
                'start_date' => '2027-01-08',
                'end_date' => '2027-01-15',
                'image' => null,
                'langitude' => '31.2357',
                'lattitude' => '30.0444',
            ],
            [
                'user_id' => 4,
                'city_id' => 1, // الرياض
                'sub_categorey_id' => 3, // سياحي
                'title' => 'رحلة إلى حافة العالم',
                'description' => 'زيارة الموقع السياحي الشهير مع نزهة وتصوير.',
                'start_date' => '2027-02-10',
                'end_date' => '2027-02-11',
                'image' => null,
                'langitude' => '46.1500',
                'lattitude' => '26.0667',
            ],
            [
                'user_id' => 5,
                'city_id' => 2, // القاهرة
                'sub_categorey_id' => 4, // ثقافي
                'title' => 'ليلة التراث المصري',
                'description' => 'أمسيات فنية تجمع بين الرقص الشرقي والموسيقى التقليدية.',
                'start_date' => '2027-03-05',
                'end_date' => '2027-03-05',
                'image' => null,
                'langitude' => '31.2357',
                'lattitude' => '30.0444',
            ],
            [
                'user_id' => 1,
                'city_id' => 1, // الرياض
                'sub_categorey_id' => 5, // تعليمي
                'title' => 'هاكاثون الابتكار التقني',
                'description' => 'مسابقة برمجة جماعية لتطوير حلول تقنية مبتكرة خلال 48 ساعة.',
                'start_date' => '2027-04-01',
                'end_date' => '2027-04-03',
                'image' => null,
                'langitude' => '46.6753',
                'lattitude' => '24.7136',
            ],
            [
                'user_id' => 2,
                'city_id' => 2, // القاهرة
                'sub_categorey_id' => 1, // رياضي
                'title' => 'بطولة التنس المفتوحة',
                'description' => 'منافسات تنس للمحترفين والهاويين على ملاعب النيل.',
                'start_date' => '2027-05-12',
                'end_date' => '2027-05-16',
                'image' => null,
                'langitude' => '31.2357',
                'lattitude' => '30.0444',
            ],
            [
                'user_id' => 3,
                'city_id' => 1, // الرياض
                'sub_categorey_id' => 2, // فني
                'title' => 'مهرجان الضوء والفنون',
                'description' => 'عروض ضوئية ثلاثية الأبعاد ومعارض فنية تفاعلية.',
                'start_date' => '2027-06-01',
                'end_date' => '2027-06-07',
                'image' => null,
                'langitude' => '46.6753',
                'lattitude' => '24.7136',
            ],
            [
                'user_id' => 5,
                'city_id' => 2, // القاهرة
                'sub_categorey_id' => 4, // ثقافي
                'title' => 'أمسية شعرية',
                'description' => 'قراءة ومناقشة الشعر العربي المعاصر.',
                'start_date' => '2026-07-20',
                'end_date' => '2026-07-20',
                'image' => null,
                'langitude' => '31.2357',
                'lattitude' => '30.0444',
            ],
            [
                'user_id' => 1,
                'city_id' => 1, // الرياض
                'sub_categorey_id' => 3, // سياحي
                'title' => 'جولة في الدرعية التاريخية',
                'description' => 'زيارة المواقع التاريخية واستكشاف التراث السعودي.',
                'start_date' => '2026-08-01',
                'end_date' => '2026-08-02',
                'image' => null,
                'langitude' => '46.6753',
                'lattitude' => '24.7136',
            ],
            [
                'user_id' => 2,
                'city_id' => 2, // القاهرة
                'sub_categorey_id' => 2, // فني
                'title' => 'مهرجان الموسيقى الكلاسيكية',
                'description' => 'حفلات موسيقية لأشهر الفرق المحلية والعالمية.',
                'start_date' => '2026-09-05',
                'end_date' => '2026-09-07',
                'image' => null,
                'langitude' => '31.2357',
                'lattitude' => '30.0444',
            ],
            [
                'user_id' => 3,
                'city_id' => 1, // الرياض
                'sub_categorey_id' => 1, // رياضي
                'title' => 'سباق الماراثون السنوي',
                'description' => 'ماراثون مفتوح لجميع الأعمار حول مدينة الرياض.',
                'start_date' => '2026-10-10',
                'end_date' => '2026-10-10',
                'image' => null,
                'langitude' => '46.6753',
                'lattitude' => '24.7136',
            ],
        ];

        foreach ($events as $event) {
            $event['slug'] = Str::slug($event['title']).'-'.Str::random(5).time();
           $event= Events::create($event);
           EventTranslations::create([
            'event_id'=> $event->id,
            'title'=> $event->title,
            'description'=> $event->description,
            'locale'=> 'ar',
           ]);
            TranslateEventJob::dispatch($event, $event->name, $event->description);
        }

    }
}
