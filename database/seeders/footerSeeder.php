<?php

namespace Database\Seeders;

use App\Models\footer;
use Illuminate\Database\Seeder;

class footerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       footer::create([
        "logo"=> "https://i.vimeocdn.com/video/1740246597-34ae317d74de8f1305f45420b5644a746e26a6dc10cb96abfbf2875d4f3c8546-d",
        "twitter"=> "x.com",
        'instagram'=>'insta.com',
        'facebook'=> 'facebook.com',
        'google_play'=> 'googlePlay.com',
        'app_store'=> 'appStore.com',
       ]);

    }
}
