<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\footer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FooterController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function all()
    {
        $cache = 'footer';
        $footer = Cache::remember($cache, $this->cacheTime, function () {
            return footer::find(1);
        });

        return $this->success($footer, 'footer data');
    }

    public function update(Request $request)
    {
        $footer = footer::findOrFail(1);
        $data = $request->except(['_token']);
        if ($request->hasFile('logo')) {
            if ($footer->logo && Storage::disk('public')->exists($footer->logo)) {
                Storage::disk('public')->delete($footer->logo);
            }
            $data['logo'] = $request->file('logo')->store('eventImages', 'public');
        }
        $footer->update($data);

        $this->clearCache();

        return $this->success($footer, 'Footer updated successfully');
    }

    private function clearCache()
    {
        Cache::forget('footer');
    }
}
