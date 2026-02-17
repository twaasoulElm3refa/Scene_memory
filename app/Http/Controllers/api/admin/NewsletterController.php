<?php

namespace App\Http\Controllers\api\admin;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\newsRequest;
use App\Models\contactResponds;
use App\Models\newsletters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class NewsletterController extends Controller
{
    use ApiResponse;
    private $cacheKey = 600;
     public function all()
    {
        $page = request()->get('page', 1);
        $perPage = 5;
        $cacheKey = "categories:paginated:p{$page}:pp{$perPage}";
        $newsLetter = Cache::remember($cacheKey, $this->cacheTime, function () use ($perPage) {
            return newsletters::with('contactResponds')->paginate($perPage);
        });
        return $this->success($newsLetter);
    }

    public function create(newsRequest $request)
    {
        $data=$request->validated();
        $contact = newsletters::create($data);
        $this->clearCache(1,5);
        return $this->success($contact, 'Contact Created Successfully');
    }

    public function respond(Request $request)
    {
        $data=$request->all();
        $data['contact_id']=request('id');
        $respond=contactResponds::create($data);
          $this->clearCache(1,5);
        return $this->success($respond, 'Respond Created Successfully');
    }

    private function clearCache($page=1, $perPage=5)
    {
        Cache::forget( "categories:paginated:p{$page}:pp{$perPage}");
        Cache::flush();
    }
}
