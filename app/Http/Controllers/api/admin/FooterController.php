<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\footer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FooterController extends Controller
{
    use ApiResponse;

    private $cacheTime=600;
    public function all()
    {
        $cache='footer';
        $footer=Cache::remember($cache, $this->cacheTime, function () {
            return footer::find(1);
        });
        return $this->success($footer,'footer data');
    }

    public function update(Request $request)
    {
        dd($request->all());
        $footer=footer::find(1);
        $footer->update($request->all());
        $this->clearCache();
        return $this->success($footer,'footer updated');
    }

    private function clearCache()
    {
        Cache::forget('footer');
        Cache::flush();
    }
}
