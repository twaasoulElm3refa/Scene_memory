<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Repositories\Contracts\Footers\FooterRepositoryInterface;
use App\Rules\SafeMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FooterController extends Controller
{
    use ApiResponse;

    private $cacheTime = 600;

    public function __construct(private readonly FooterRepositoryInterface $footerRepository) {}

    public function all()
    {
        $cache = 'footer';
        $footer = Cache::remember($cache, $this->cacheTime, function () {
            return $this->footerRepository->first();
        });

        return $this->success($footer, 'footer data');
    }

    public function publicLinks()
    {
        $footer = Cache::remember('footer', $this->cacheTime, fn () => $this->footerRepository->first());

        return $this->success($footer?->only(['facebook', 'twitter', 'instagram', 'google_play', 'app_store']) ?? []);
    }

    public function update(Request $request)
    {
        $request->validate(['logo' => ['sometimes', 'nullable', 'image', 'max:5120', new SafeMedia]]);
        $footer = $this->footerRepository->findOrFail(1);
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
